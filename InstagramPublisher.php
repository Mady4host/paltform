<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * InstagramPublisher (v1.2)
 * - يدعم نشر Reel (Video)
 * - يدعم نشر Story (Image / Video) باستخدام media_type=STORIES مع Fallback is_story=true
 * - Poll لحالة المعالجة حتى FINISHED قبل media_publish
 * - Logging تفصيلي
 * - فحص وصول URL الملف قبل الإرسال
 */
class InstagramPublisher {

    protected $CI;
    protected $graphVersion = 'v23.0';
    protected $logFile = 'instagram_publish.log';

    // إعدادات Poll
    protected $pollMaxAttempts = 6;
    protected $pollDelaySeconds = 5;

    public function __construct() {
        $this->CI = &get_instance();
    }

    /* ================= واجهات عليا ================= */

    public function publishReel($igUserId, $localPath, $caption, $accessToken) {
        if (!$this->validateLocalFile($localPath, 'video')) {
            return $this->fail('file_not_found_or_unreadable');
        }
        $publicUrl = $this->makePublicUrl($localPath);
        if (!$publicUrl || !$this->checkUrlReachable($publicUrl)) {
            return $this->fail('public_url_unreachable', ['url'=>$publicUrl]);
        }

        $this->log("START_REEL ig={$igUserId} file={$localPath} url={$publicUrl}");

        $create = $this->createReelContainer($igUserId, $publicUrl, $caption, $accessToken);
        if(!$create['ok']) return $create;

        $poll = $this->pollProcessing($create['creation_id'], $accessToken, $igUserId);
        if(!$poll['ok']) {
            return $this->fail('processing_not_finished', $poll);
        }

        $publish = $this->publishContainer($igUserId, $create['creation_id'], $accessToken);
        if(!$publish['ok']) return $publish;

        $this->log("DONE_REEL ig={$igUserId} media_id={$publish['media_id']} creation_id={$create['creation_id']}");
        return [
            'ok'          => true,
            'media_id'    => $publish['media_id'],
            'creation_id' => $create['creation_id']
        ];
    }

    public function publishStory($igUserId, $localPath, $fileType, $accessToken) {
        if (!$this->validateLocalFile($localPath, $fileType)) {
            return $this->fail('file_not_found_or_unreadable');
        }
        $publicUrl = $this->makePublicUrl($localPath);
        if (!$publicUrl || !$this->checkUrlReachable($publicUrl)) {
            return $this->fail('public_url_unreachable', ['url'=>$publicUrl]);
        }

        $this->log("START_STORY ig={$igUserId} type={$fileType} file={$localPath} url={$publicUrl}");

        $create = $this->createStoryContainer($igUserId, $publicUrl, $fileType, $accessToken);
        if(!$create['ok']) return $create;

        // عادةً ال Stories سريعة، لكن نعمل Poll خفيف
        $poll = $this->pollProcessing($create['creation_id'], $accessToken, $igUserId, true);
        if(!$poll['ok']) {
            $this->log("STORY_PROCESSING_TIMEOUT creation_id={$create['creation_id']}");
        }

        $publish = $this->publishContainer($igUserId, $create['creation_id'], $accessToken);
        if(!$publish['ok']) return $publish;

        $this->log("DONE_STORY ig={$igUserId} media_id={$publish['media_id']} creation_id={$create['creation_id']}");
        return [
            'ok'          => true,
            'media_id'    => $publish['media_id'],
            'creation_id' => $create['creation_id']
        ];
    }

    /* ================= إنشاء Containers ================= */

    public function createReelContainer($igUserId, $videoUrl, $caption, $token) {
        $params = [
            'media_type' => 'REELS',
            'video_url'  => $videoUrl,
        ];
        if($caption) $params['caption'] = $caption;

        $res = $this->graphPost("/{$igUserId}/media", $params, $token, 'CREATE_REEL');
        if(empty($res['id'])) {
            return $this->fail('create_reel_failed', $res);
        }
        $this->log("CREATE_REEL_OK ig={$igUserId} creation_id={$res['id']}");
        return ['ok'=>true,'creation_id'=>$res['id']];
    }

    /**
     * Story container:
     * المحاولة الأولى: media_type=STORIES
     * Fallback: is_story=true (لبعض الإصدارات القديمة)
     */
    public function createStoryContainer($igUserId, $url, $fileType, $token) {
        // Attempt #1
        $params = [
            'media_type' => 'STORIES'
        ];
        if ($fileType === 'image') {
            $params['image_url'] = $url;
        } else {
            $params['video_url'] = $url;
        }
        $res = $this->graphPost("/{$igUserId}/media", $params, $token, 'CREATE_STORY_MT');
        if(!empty($res['id'])) {
            $this->log("CREATE_STORY_OK method=media_type ig={$igUserId} creation_id={$res['id']}");
            return ['ok'=>true,'creation_id'=>$res['id']];
        }

        // Fallback #2
        $this->log("CREATE_STORY_FALLBACK is_story ig={$igUserId}");
        $params2 = [
            'is_story' => 'true'
        ];
        if ($fileType === 'image') {
            $params2['image_url'] = $url;
        } else {
            $params2['video_url'] = $url;
        }
        $res2 = $this->graphPost("/{$igUserId}/media", $params2, $token, 'CREATE_STORY_IS');
        if(empty($res2['id'])) {
            return $this->fail('create_story_failed', ['first'=>$res,'second'=>$res2]);
        }
        $this->log("CREATE_STORY_OK fallback=is_story ig={$igUserId} creation_id={$res2['id']}");
        return ['ok'=>true,'creation_id'=>$res2['id']];
    }

    public function publishContainer($igUserId, $creationId, $token) {
        $res = $this->graphPost("/{$igUserId}/media_publish", [
            'creation_id' => $creationId
        ], $token, 'PUBLISH');

        if(empty($res['id'])) {
            return $this->fail('publish_failed', $res);
        }
        return ['ok'=>true,'media_id'=>$res['id']];
    }

    /* ================= Poll لمعالجة الفيديو ================= */

    protected function pollProcessing($creationId, $accessToken, $igUserId, $isStory=false) {
        // Stories غالباً لا تحتاج Poll، لكن نمنحه محاولتين خفيفتين
        $attempts = $isStory ? min(2,$this->pollMaxAttempts) : $this->pollMaxAttempts;

        for($i=1; $i <= $attempts; $i++){
            $status = $this->graphGet("/{$creationId}?fields=status_code", $accessToken, 'POLL');
            $code = $status['status_code'] ?? 'UNKNOWN';
            $this->log("POLL ig={$igUserId} creation_id={$creationId} attempt={$i} status_code={$code}");
            if(in_array($code,['FINISHED','READY','PUBLISHED'])) {
                return ['ok'=>true,'status_code'=>$code];
            }
            if(in_array($code,['ERROR','FAILED'])) {
                return ['ok'=>false,'status_code'=>$code];
            }
            sleep($this->pollDelaySeconds);
        }
        return ['ok'=>false,'status_code'=>'TIMEOUT'];
    }

    /* ================= Helpers: HTTP / Graph ================= */

    protected function graphPost($endpoint, $params, $accessToken, $tag='POST') {
        $base = "https://graph.facebook.com/{$this->graphVersion}";
        $url  = $base . $endpoint;
        $params['access_token'] = $accessToken;

        $this->log("REQ_{$tag} endpoint={$endpoint} params=".json_encode($this->sanitizeParams($params)));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($err){
            $this->log("ERR_{$tag} endpoint={$endpoint} curl_error={$err}");
            return ['error'=>$err,'code'=>$code];
        }
        $this->log("RES_{$tag} code={$code} body={$body}");
        $data = json_decode($body,true);
        return $data ?: [];
    }

    protected function graphGet($endpoint, $accessToken, $tag='GET') {
        $base = "https://graph.facebook.com/{$this->graphVersion}";
        $sep  = (strpos($endpoint,'?')===false)?'?':'&';
        $url  = $base.$endpoint.$sep.'access_token='.urlencode($accessToken);

        $this->log("REQ_{$tag} endpoint={$endpoint}");

        $ch = curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($err){
            $this->log("ERR_{$tag} endpoint={$endpoint} curl_error={$err}");
            return ['error'=>$err,'code'=>$code];
        }
        $this->log("RES_{$tag} code={$code} body={$body}");
        return json_decode($body,true) ?: [];
    }

    protected function sanitizeParams($params) {
        $copy = $params;
        if(isset($copy['access_token'])) $copy['access_token'] = substr($copy['access_token'],0,12).'***';
        return $copy;
    }

    protected function validateLocalFile($path, $expectedType) {
        if(!file_exists($path)) return false;
        if(!is_readable($path)) return false;
        // ممكن نضيف فحص امتداد مقابل $expectedType
        return true;
    }

    protected function makePublicUrl($localPath) {
        $root = FCPATH;
        if(strpos($localPath,$root)===0){
            $rel = ltrim(str_replace($root,'',$localPath),'/');
            return rtrim(base_url(),'/').'/'.$rel;
        }
        return null;
    }

    protected function checkUrlReachable($url) {
        if(!$url) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code >= 200 && $code < 400);
    }

    protected function fail($error, $raw=null) {
        $this->log("FAIL error={$error} raw=".json_encode($raw));
        return ['ok'=>false,'error'=>$error,'raw'=>$raw];
    }

    protected function log($line) {
        $dir = APPPATH.'logs/';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        @file_put_contents($dir.$this->logFile,'['.gmdate('Y-m-d H:i:s').'] '.$line.PHP_EOL,FILE_APPEND);
    }
}