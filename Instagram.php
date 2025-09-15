<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Instagram Controller (UTC Scheduling)
 * - الأوقات مخزنة UTC
 * - حفظ original_local_time / original_offset_minutes / original_timezone
 * - يعتمد بالكامل على جدول المنصّة facebook_rx_fb_page_info لمصادر حسابات إنستجرام
 * - يحافظ على نفس مفاتيح البيانات للواجهات (ig_user_id, ig_username, ig_profile_picture, page_name, access_token)
 */
class Instagram extends CI_Controller
{
    private const MAIN_SESSION_USER_KEY = 'user_id';
    private const MAX_FILE_SIZE_MB       = 120;
    private const CAPTION_MAX            = 2200;
    private const MAX_COMMENTS_PER_REEL  = 20;
    private const MAX_MULTI_ACCOUNTS     = 400;
    private const MAX_FILES_PER_BATCH    = 80;
    private const MAX_CRON_BATCH         = 25;
    private const MAX_CRON_ATTEMPTS      = 3;
    private const RETRY_DELAY_MINUTES    = 2;
    private const LISTING_PAGE_LIMIT     = 30;
    private const MIN_FUTURE_SECONDS     = 30;

    public function __construct(){
        parent::__construct();
        $this->load->database();
        $this->load->library(['session','InstagramPublisher']);
        $this->load->helper(['url','form','text','file']);
        $this->load->model('Instagram_reels_model');
    }

    private function requireLogin(){
        $uid=(int)$this->session->userdata(self::MAIN_SESSION_USER_KEY);
        if($uid<=0){
            redirect('home/login?redirect='.rawurlencode(current_url()));
            exit;
        }
        return $uid;
    }

    /*********** TIME HELPERS ***********/
    /**
     * Convert local time string (format Y-m-dTH:i) and offsetMinutes to UTC timestamp string.
     * Note: offsetMinutes is minutes to add to UTC to get local (i.e., local = UTC + offset).
     * Therefore UTC = local - offset.
     */
        private function localToUtc(?string $local,int $offsetMinutes){
        if(!$local) return null;
        $local=trim($local);
        if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$local)) return null;
        $ts=strtotime($local);
        if($ts===false) return null;
        return gmdate('Y-m-d H:i:s', $ts + ($offsetMinutes*60));
    }

    private function isFutureUtc(?string $utc,$min=self::MIN_FUTURE_SECONDS){
        if(!$utc) return false;
        try {
            $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            return ($dt->getTimestamp() > $now->getTimestamp() + (int)$min);
        } catch (Exception $e) {
            return false;
        }
    }

    /*********** HTTP REQUEST WITH RETRY (CURL) ***********/
    private function httpRequestWithRetry(string $method, string $url, array $opts = [], int $maxAttempts = 3, int $initialDelayMs = 500) {
        $attempt = 0;
        $delay = max(100, (int)$initialDelayMs);
        $method = strtoupper($method);
        while ($attempt < $maxAttempts) {
            $attempt++;
            $ch = curl_init();
            $headers = $opts['headers'] ?? [];
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, isset($opts['timeout']) ? (int)$opts['timeout'] : 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['post_fields'] ?? []);
            }
            if (!empty($headers) && is_array($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header_text = $header_size ? substr((string)$raw, 0, $header_size) : '';
            $body = $header_size ? substr((string)$raw, $header_size) : (string)$raw;
            curl_close($ch);

            // parse headers (last response block)
            $hdrs = [];
            if ($header_text) {
                $lines = preg_split("/\r\n|\n|\r/", $header_text);
                foreach ($lines as $line) {
                    if (strpos($line, ':') !== false) {
                        [$k,$v] = explode(':', $line, 2);
                        $hdrs[strtolower(trim($k))] = trim($v);
                    }
                }
            }

            if ($err) {
                if ($attempt < $maxAttempts) {
                    usleep($delay * 1000);
                    $delay = min(60000, (int)($delay * 1.8));
                    continue;
                }
                return ['ok'=>false,'error'=>'curl_error','curl_error'=>$err,'http_code'=>$code,'headers'=>$hdrs,'body'=>$body];
            }

            // Treat 429 and server 5xx as transient
            if (in_array($code, [429,500,502,503,504], true) && $attempt < $maxAttempts) {
                usleep($delay * 1000);
                $delay = min(60000, (int)($delay * 1.8));
                continue;
            }

            return ['ok'=>true,'http_code'=>$code,'headers'=>$hdrs,'body'=>$body];
        }

        return ['ok'=>false,'error'=>'max_attempts_reached'];
    }

    /*********** PLATFORM TABLE HELPERS (facebook_rx_fb_page_info) ***********/
    private function getPlatformAccounts(int $user_id, bool $requireLinked = false): array {
        if(!$this->db->table_exists('facebook_rx_fb_page_info')) return [];

        $this->db->select("
            user_id,
            instagram_business_account_id AS ig_user_id,
            insta_username AS ig_username,
            ig_profile_picture,
            page_id,
            COALESCE(page_name, username, page_id) AS page_name,
            page_access_token,
            ig_linked,
            has_instagram
        ", false)
        ->from('facebook_rx_fb_page_info')
        ->where('user_id', $user_id)
        ->where('has_instagram', '1')
        ->where("(instagram_business_account_id <> '')", null, false);

        if ($requireLinked) {
            $this->db->where('ig_linked', 1);
        }

        $this->db->order_by('COALESCE(page_name, username, page_id)', 'ASC', false);

        $rows = $this->db->get()->result_array();

        $out=[];
        foreach($rows as $r){
            $pic = (string)($r['ig_profile_picture'] ?? '');

            // إذا ما فيها صورة، جرب نحصلها لكن بهدوء لتجنب انفلات الطلبات
            if ($pic === '' && !empty($r['ig_user_id']) && !empty($r['page_access_token'])) {
                usleep(100000); // throttle
                $fetched = $this->fetchIgProfilePicture($r['ig_user_id'], $r['page_access_token']);
                if ($fetched !== '') {
                    $pic = $fetched;
                    $this->db->where('user_id', $user_id)
                             ->where('page_id', $r['page_id'])
                             ->update('facebook_rx_fb_page_info', ['ig_profile_picture' => $pic]);
                }
            }

            $out[]=[
                'id'                 => (string)($r['page_id'] ?? ''),
                'user_id'            => (int)$r['user_id'],
                'ig_user_id'         => (string)($r['ig_user_id']),
                'ig_username'        => (string)($r['ig_username'] ?? ''),
                'ig_profile_picture' => $pic,
                'page_name'          => (string)($r['page_name'] ?? ''),
                'access_token'       => (string)($r['page_access_token'] ?? ''),
                'ig_linked'          => (int)$r['ig_linked'],
            ];
        }
        return $out;
    }

    private function getPlatformAccountByIgUserId(int $user_id, string $ig_user_id, bool $requireLinked = false): ?array {
        if(!$this->db->table_exists('facebook_rx_fb_page_info')) return null;

        $this->db->select("
            user_id,
            instagram_business_account_id AS ig_user_id,
            insta_username AS ig_username,
            ig_profile_picture,
            page_id,
            COALESCE(page_name, username, page_id) AS page_name,
            page_access_token,
            ig_linked,
            has_instagram
        ", false)
        ->from('facebook_rx_fb_page_info')
        ->where('user_id', $user_id)
        ->where('has_instagram', '1')
        ->where('instagram_business_account_id', $ig_user_id);

        if ($requireLinked) {
            $this->db->where('ig_linked', 1);
        }

        $row = $this->db->limit(1)->get()->row_array();
        if(!$row) return null;

        if (empty($row['ig_profile_picture']) && !empty($row['ig_user_id']) && !empty($row['page_access_token'])) {
            usleep(100000);
            $fetched = $this->fetchIgProfilePicture($row['ig_user_id'], $row['page_access_token']);
            if ($fetched !== '') {
                $row['ig_profile_picture'] = $fetched;
                $this->db->where('user_id', $user_id)
                         ->where('instagram_business_account_id', $ig_user_id)
                         ->update('facebook_rx_fb_page_info', ['ig_profile_picture' => $fetched]);
            }
        }

        return $row;
    }

    private function fetchIgProfilePicture(string $ig_user_id, string $accessToken): string {
        if($ig_user_id === '' || $accessToken === '') return '';
        $apiVersion = 'v19.0';
        $url = 'https://graph.facebook.com/'.$apiVersion.'/'.rawurlencode($ig_user_id).'?fields=profile_picture_url&access_token='.rawurlencode($accessToken);

        $res = $this->httpRequestWithRetry('GET', $url, [], 3, 500);
        if(!$res['ok']){
            @file_put_contents(APPPATH.'logs/ig_fetch_profile_pic.log',"[".gmdate('Y-m-d H:i:s')."] fetch_error ig={$ig_user_id} err=".json_encode($res)."\n", FILE_APPEND);
            return '';
        }

        $code = $res['http_code'] ?? 0;
        $body = $res['body'] ?? '';
        $hdrs = $res['headers'] ?? [];

        if(!empty($hdrs['x-app-usage'])){
            @file_put_contents(APPPATH.'logs/ig_fetch_profile_pic.log',"[".gmdate('Y-m-d H:i:s')."] x-app-usage={$hdrs['x-app-usage']} for ig={$ig_user_id}\n", FILE_APPEND);
        }

        $data = json_decode($body, true);
        if($code===200 && is_array($data) && !empty($data['profile_picture_url'])){
            return (string)$data['profile_picture_url'];
        }
        return '';
    }

    public function upload(){
        $user_id=$this->requireLogin();
        $accounts = $this->getPlatformAccounts($user_id, false);
        $this->load->view('instagram_upload',['accounts'=>$accounts]);
    }

    public function publish(){
        $user_id=$this->requireLogin();
        if($_SERVER['REQUEST_METHOD']!=='POST'){ show_error('Method Not Allowed',405); }

        $debugMode   = (int)$this->input->get_post('debug');
        $clientCount = (int)$this->input->post('_client_file_count');
        $tzOffsetMin = (int)$this->input->post('_tz_offset');
        $tzName      = trim((string)$this->input->post('_tz_name')) ?: null;

        $primary_ig_user_id = trim($this->input->post('ig_user_id'));
        if($primary_ig_user_id===''){ return $this->respondError('اختر حساباً.'); }

        $multi = $this->input->post('ig_user_ids');
        $accounts=[];
        if(is_array($multi)){
            foreach($multi as $m){ $m=trim($m); if($m!=='') $accounts[]=$m; }
        }
        $accounts[]=$primary_ig_user_id;
        $accounts=array_values(array_unique($accounts));
        if(count($accounts)>self::MAX_MULTI_ACCOUNTS){
            $accounts=array_slice($accounts,0,self::MAX_MULTI_ACCOUNTS);
        }

        $media_kind = trim($this->input->post('media_kind'));
        if(!in_array($media_kind,['reel','story'],true)){
            return $this->respondError('نوع غير مدعوم.');
        }

        $mediaCfg = $this->input->post('media_cfg');
        if(!is_array($mediaCfg)) $mediaCfg=[];

        $files = $this->collectAllFiles($_FILES);

        if($debugMode===1){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'debug'=>true,
                'parsed_files_count'=>count($files),
                'client_reported_count'=>$clientCount,
                'tz_offset_minutes'=>$tzOffsetMin,
                'tz_name'=>$tzName,
                'server_now'=>gmdate('Y-m-d H:i:s').'Z',
                'media_kind'=>$media_kind,
                'accounts'=>$accounts
            ],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            return;
        }

        if(!$files){
            $this->logUploadIssue('EMPTY_AFTER_COLLECT client='.$clientCount,$_FILES);
            return $this->respondError('لا يوجد ملفات صالحة.');
        }
        if(count($files)>self::MAX_FILES_PER_BATCH){
            return $this->respondError('عدد الملفات كبير (الحد '.self::MAX_FILES_PER_BATCH.').');
        }

        $globalResults=[];
        $firstRedirect=null;

        // ========== FACEBOOK-COMPAT SCHEDULER ==========
        if (isset($_POST['schedule_times_fb']) && is_array($_POST['schedule_times_fb'])) {
            $tzOffsetMinFb = (int)($this->input->post('tz_offset_minutes') ?? 0);
            $schedLocalArr = $_POST['schedule_times_fb'];
            $descsFb       = $_POST['descriptions_fb'] ?? [];
            $commentsFb    = $_POST['comments_fb'] ?? [];
            $global_desc   = trim((string)$this->input->post('description_fb'));

            $scheduledFiles = [];
            $immediateFiles = [];

            foreach ($files as $i => $f) {
                $local = trim($schedLocalArr[$i] ?? '');
                if ($local === '') { $immediateFiles[] = $i; continue; }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $local)) { $immediateFiles[] = $i; continue; }
                $utc = $this->localToUtc($local, $tzOffsetMinFb);
                if (!$utc || !$this->isFutureUtc($utc, 30)) { $immediateFiles[] = $i; continue; }
                $scheduledFiles[] = [$i, $local, $utc];
            }

            foreach ($scheduledFiles as [$i, $local, $utc]) {
                $f   = $files[$i];
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $isVideo = ($ext === 'mp4');
                if (!$isVideo) { continue; }

                $saveMeta = $this->saveUploadedFile($f, $user_id);
                if (!$saveMeta['ok']) {
                    $globalResults[] = ['file'=>$f['name'],'status'=>'error','error'=>$saveMeta['error']];
                    continue;
                }
                $storedFileName = $saveMeta['new_name'];
                $storedFilePath = 'uploads/instagram/' . $storedFileName;

                $caption = trim($descsFb[$i] ?? '') ?: $global_desc ?: pathinfo($f['name'], PATHINFO_FILENAME);

                $cList = $commentsFb[$i] ?? [];
                $cListClean = [];
                if (is_array($cList)) {
                    foreach ($cList as $c) {
                        $c = trim($c);
                        if ($c !== '') $cListClean[] = $c;
                        if (count($cListClean) >= 20) break;
                    }
                }

                $slotRow = [
                    'media_kind'               => 'ig_reel',
                    'file_type'                => 'video',
                    'file_name'                => $storedFileName,
                    'file_path'                => $storedFilePath,
                    'description'              => $caption,
                    'status'                   => Instagram_reels_model::STATUS_SCHEDULED,
                    'publish_mode'             => 'scheduled',
                    'comments_count'           => count($cListClean),
                    'comments_json'            => $cListClean ? json_encode($cListClean, JSON_UNESCAPED_UNICODE) : null,
                    'original_offset_minutes'  => $tzOffsetMinFb,
                    'original_timezone'        => null,
                    'original_local_time'      => str_replace('T',' ',$local).':00'
                ];

                $ids = $this->Instagram_reels_model->create_scheduled_batch(
                    $user_id,
                    $slotRow,
                    $accounts,
                    $utc,
                    'none',
                    null,
                    time().rand(1000,9999),
                    1
                );

                $globalResults[] = ['file'=>$f['name'], 'status'=>'scheduled', 'records'=>$ids];
                if ($firstRedirect === null && $ids) { $firstRedirect = $ids[0]; }
            }

            if (!empty($scheduledFiles)) {
                $indicesDone = array_column($scheduledFiles, 0);
                foreach ($indicesDone as $di) {
                    $files[$di]['error'] = UPLOAD_ERR_NO_FILE;
                }
            }
        }
        // ========== END FACEBOOK-COMPAT SCHEDULER ==========

        foreach($files as $idx=>$fileArr){
            $cfg=$mediaCfg[$idx] ?? [];

            if($fileArr['error']!==UPLOAD_ERR_OK){
                $globalResults[]=['file'=>$fileArr['name']?:('#'.($idx+1)),'status'=>'error','error'=>'upload_error_'.$fileArr['error']];
                continue;
            }
            if($fileArr['size']>self::MAX_FILE_SIZE_MB*1024*1024){
                $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'file_too_large'];
                continue;
            }

            $ext=strtolower(pathinfo($fileArr['name'],PATHINFO_EXTENSION));
            $isImage=in_array($ext,['jpg','jpeg','png']);
            $isVideo=($ext==='mp4');

            $finalMediaKind=null; $fileType=null; $caption=null; $comments=[];

            if($media_kind==='reel'){
                if(!$isVideo){ $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'reel_requires_mp4']; continue; }
                $finalMediaKind='ig_reel';
                $fileType='video';
                $caption=isset($cfg['caption'])?trim($cfg['caption']):'';
                if(mb_strlen($caption)>self::CAPTION_MAX){
                    $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'caption_too_long']; continue;
                }
                if(!empty($cfg['comments']) && is_array($cfg['comments'])){
                    foreach($cfg['comments'] as $c){
                        $c=trim($c);
                        if($c==='') continue;
                        if(mb_strlen($c)>self::CAPTION_MAX){
                            $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'comment_too_long']; continue 2;
                        }
                        $comments[]=$c;
                        if(count($comments)>=self::MAX_COMMENTS_PER_REEL) break;
                    }
                }
            } else {
                if(!$isImage && !$isVideo){
                    $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'story_bad_type']; continue;
                }
                if($isImage){ $finalMediaKind='ig_story_image'; $fileType='image'; }
                else { $finalMediaKind='ig_story_video'; $fileType='video'; }
            }

            $saveMeta=$this->saveUploadedFile($fileArr,$user_id);
            if(!$saveMeta['ok']){
                $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>$saveMeta['error']];
                continue;
            }
            $storedFileName=$saveMeta['new_name'];
            $storedFilePath='uploads/instagram/'.$storedFileName;
            $fullPath=$saveMeta['full_path'];

            $publish_mode = (isset($cfg['publish_mode']) && $cfg['publish_mode']==='scheduled')?'scheduled':'immediate';

            if($publish_mode==='scheduled'){
                $schedule_count=(int)($cfg['schedule_count'] ?? 1);
                if($schedule_count<1) $schedule_count=1;
                if($schedule_count>10) $schedule_count=10;

                $schedules=$cfg['schedules'] ?? [];
                if(empty($schedules)){
                    $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'no_schedule_slots'];
                    continue;
                }

                $group_id=time().rand(1000,9999);
                $createdIds=[];
                for($s=1;$s<=$schedule_count;$s++){
                    $slot=$schedules[$s] ?? ($schedules[array_key_first($schedules)] ?? null);
                    if(!$slot){
                        $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'missing_slot_'.$s]; continue 2;
                    }
                    $rawTime=trim($slot['time'] ?? '');
                    if($rawTime===''){
                        $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'slot_time_empty_'.$s]; continue 2;
                    }
                    if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$rawTime)){
                        $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'slot_time_format_'.$s]; continue 2;
                    }
                    $utcTime = $this->localToUtc($rawTime,$tzOffsetMin);
                    if(!$utcTime || !$this->isFutureUtc($utcTime)){
                        $globalResults[]=['file'=>$fileArr['name'],'status'=>'error','error'=>'slot_time_past_'.$s]; continue 2;
                    }

                    $originalLocalTime = str_replace('T',' ',$rawTime).':00';

                    $recKind= $slot['recurrence_kind'] ?? 'none';
                    $recUntilRaw=trim($slot['recurrence_until'] ?? '');
                    $recUntil=null;
                    if($recKind!=='none' && $recUntilRaw!==''){
                        if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$recUntilRaw)){
                            $recUntilUtc = $this->localToUtc($recUntilRaw,$tzOffsetMin);
                            if($recUntilUtc && strtotime($recUntilUtc) > strtotime($utcTime)){
                                $recUntil = $recUntilUtc;
                            }
                        }
                    }

                    $slotRow=[
                        'media_kind'=>$finalMediaKind,
                        'file_type'=>$fileType,
                        'file_name'=>$storedFileName,
                        'file_path'=>$storedFilePath,
                        'description'=>$caption,
                        'status'=>Instagram_reels_model::STATUS_SCHEDULED,
                        'publish_mode'=>'scheduled',
                        'comments_count'=>count($comments),
                        'comments_json'=>!empty($comments)?json_encode($comments,JSON_UNESCAPED_UNICODE):null,
                        'original_offset_minutes'=>$tzOffsetMin,
                        'original_timezone'=>$tzName,
                        'original_local_time'=>$originalLocalTime
                    ];

                    $ids=$this->Instagram_reels_model->create_scheduled_batch(
                        $user_id,
                        $slotRow,
                        $accounts,
                        $utcTime,
                        $recKind,
                        $recUntil,
                        $group_id,
                        $s
                    );
                    $createdIds=array_merge($createdIds,$ids);
                }

                $globalResults[]=['file'=>$fileArr['name'],'status'=>'scheduled','records'=>$createdIds];
                if($firstRedirect===null && $createdIds){ $firstRedirect=$createdIds[0]; }
                continue;
            }

            // نشر فوري
            foreach($accounts as $ig_uid){
                $account = $this->getPlatformAccountByIgUserId($user_id, $ig_uid, false);
                if(!$account){
                    $globalResults[]=['file'=>$fileArr['name'],'ig_user_id'=>$ig_uid,'status'=>'error','error'=>'account_not_found'];
                    continue;
                }
                $token = $account['page_access_token'] ?? $this->session->userdata('fb_access_token');
                if(!$token){
                    $globalResults[]=['file'=>$fileArr['name'],'ig_user_id'=>$ig_uid,'status'=>'error','error'=>'no_token'];
                    continue;
                }

                $recordId=$this->Instagram_reels_model->insert_record([
                    'user_id'=>$user_id,
                    'ig_user_id'=>$ig_uid,
                    'media_kind'=>$finalMediaKind,
                    'file_type'=>$fileType,
                    'file_name'=>$storedFileName,
                    'file_path'=>$storedFilePath,
                    'description'=>$caption,
                    'status'=>'pending',
                    'publish_mode'=>'immediate',
                    'comments_count'=>count($comments),
                    'comments_json'=>!empty($comments)?json_encode($comments,JSON_UNESCAPED_UNICODE):null,
                    'created_at'=>gmdate('Y-m-d H:i:s')
                ]);
                if($firstRedirect===null){ $firstRedirect=$recordId; }

                if($finalMediaKind==='ig_reel'){
                    $res=$this->instagrampublisher->publishReel($ig_uid,$fullPath,$caption,$token);
                } else {
                    $type = $finalMediaKind==='ig_story_image' ? 'image' : 'video';
                    $res=$this->instagrampublisher->publishStory($ig_uid,$fullPath,$type,$token);
                }

                if(!$res['ok']){
                    $this->Instagram_reels_model->mark_failed($recordId,$res['error'] ?? 'unknown_error');
                    $globalResults[]=['file'=>$fileArr['name'],'ig_user_id'=>$ig_uid,'record_id'=>$recordId,'status'=>'error','error'=>$res['error'] ?? 'unknown_error'];
                    continue;
                }

                $this->Instagram_reels_model->mark_published($recordId,$res['media_id'],$res['creation_id'] ?? null);

                $comments_result=[];
                if($finalMediaKind==='ig_reel' && !empty($comments)){
                    $comments_result=$this->post_reel_comments($res['media_id'],$comments,$token);
                    $first_comment_id=null;
                    foreach($comments_result as $cr){
                        if($cr['status']==='ok'){ $first_comment_id=$cr['comment_id']; break; }
                    }
                    $this->db->where('id',$recordId)->update('instagram_reels',[
                        'first_comment_id'=>$first_comment_id,
                        'comments_publish_result_json'=>json_encode($comments_result,JSON_UNESCAPED_UNICODE),
                        'updated_at'=>gmdate('Y-m-d H:i:s')
                    ]);
                }

                $globalResults[]=[
                    'file'=>$fileArr['name'],
                    'ig_user_id'=>$ig_uid,
                    'record_id'=>$recordId,
                    'status'=>'ok',
                    'media_id'=>$res['media_id'],
                    'comments_result'=>$comments_result
                ];
            }
        }

        $redirectUrl=site_url('instagram/listing'.($firstRedirect?('?rid='.$firstRedirect):''));
        $allFailed=true;
        foreach($globalResults as $r){
            if(in_array($r['status'],['ok','scheduled'],true)){ $allFailed=false; break; }
        }

        if($this->isAjax()){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'=>$allFailed?'error':'ok',
                'message'=>$allFailed?'publish_failed':'batch_done',
                'results'=>$globalResults,
                'redirect_url'=>$redirectUrl
            ],JSON_UNESCAPED_UNICODE);
            return;
        }
        if($allFailed){
            $this->session->set_flashdata('ig_error','فشلت جميع الملفات.');
            redirect('instagram/upload');
        } else {
            $this->session->set_flashdata('ig_success','تم التنفيذ.');
            redirect($redirectUrl);
        }
    }

    public function listing(){
        $user_id=$this->requireLogin();

        $filter=[
            'ig_user_id'=>trim($this->input->get('ig_user_id')),
            'status'=>trim($this->input->get('status')),
            'media_kind'=>trim($this->input->get('media_kind')),
            'publish_mode'=>trim($this->input->get('publish_mode')),
            'recurrence_kind'=>trim($this->input->get('recurrence_kind')),
            'q'=>trim($this->input->get('q')),
            'date_from'=>trim($this->input->get('date_from')),
            'date_to'=>trim($this->input->get('date_to')),
        ];
        foreach($filter as $k=>$v){ if($v==='') unset($filter[$k]); }

        $page=max(1,(int)$this->input->get('page'));
        $limit=self::LISTING_PAGE_LIMIT;
        $offset=($page-1)*$limit;

        $items=$this->Instagram_reels_model->get_by_user($user_id,$filter,$limit,$offset,'id','DESC');
        $total=$this->Instagram_reels_model->count_by_user($user_id,$filter);
        $summary=$this->Instagram_reels_model->summary_counts($user_id);

        $accountsFull = $this->getPlatformAccounts($user_id, false);
        $accounts=[];
        foreach($accountsFull as $a){
            $accounts[]=[
                'ig_user_id'=>$a['ig_user_id'],
                'ig_username'=>$a['ig_username'],
                'page_name'=>$a['page_name'],
                'ig_profile_picture'=>$a['ig_profile_picture'],
            ];
        }

        $data=[
            'items'=>$items,'total'=>$total,'page'=>$page,'limit'=>$limit,
            'pages'=>ceil($total/$limit),'filter'=>$filter,'summary'=>$summary,
            'accounts'=>$accounts,'just_published_id'=>(int)$this->input->get('rid')
        ];

        if(!file_exists(APPPATH.'views/instagram_listing.php')){
            header('Content-Type:text/html; charset=utf-8');
            echo "<h3>Instagram Listing (Fallback)</h3><p>Total: {$total}</p><ul>";
            foreach($items as $it){
                echo "<li>#{$it['id']} | {$it['media_kind']} | {$it['status']} | ".
                     htmlspecialchars(mb_substr($it['description']??'',0,50))."</li>";
            }
            echo "</ul><p>أنشئ الملف: application/views/instagram_listing.php</p>";
            return;
        }
        $this->load->view('instagram_listing',$data);
    }

    public function cron_run(){
        $key_req=$this->input->get('key');
        $confKey=$this->config->item('ig_cron_key') ?: (defined('IG_CRON_KEY')?IG_CRON_KEY:null);
        if(php_sapi_name()!=='cli'){
            if(!$confKey || $key_req!==$confKey){ show_error('Forbidden',403); }
        }

        $due=$this->Instagram_reels_model->fetch_due_scheduled(self::MAX_CRON_BATCH,self::MAX_CRON_ATTEMPTS);
        if(!$due){ $this->logCron('NO_DUE at '.gmdate('Y-m-d H:i:s')); }
        else { $this->logCron('FOUND_DUE='.count($due)); }

        $processed=[];
        foreach($due as $row){
            $id=(int)$row['id'];

            $account = $this->getPlatformAccountByIgUserId((int)$row['user_id'], (string)$row['ig_user_id'], false);
            if(!$account){
                $this->Instagram_reels_model->mark_failed($id,'account_not_found');
                $processed[]=['id'=>$id,'status'=>'failed','reason'=>'account_not_found'];
                $this->logCron("ID $id account_not_found");
                continue;
            }

            $this->Instagram_reels_model->mark_publishing($id);

            $token=$account['page_access_token'] ?? null;
            if(!$token){
                if(($row['attempt_count']+1)<self::MAX_CRON_ATTEMPTS){
                    $this->Instagram_reels_model->reschedule_for_retry($id,self::RETRY_DELAY_MINUTES);
                    $this->logCron("ID $id retry no_token");
                } else {
                    $this->Instagram_reels_model->mark_failed($id,'no_token');
                    $this->logCron("ID $id failed no_token");
                }
                $processed[]=['id'=>$id,'status'=>'failed','reason'=>'no_token'];
                continue;
            }

            $finalPath=FCPATH.$row['file_path'];
            if(!is_file($finalPath)){
                $this->Instagram_reels_model->mark_failed($id,'file_missing');
                $processed[]=['id'=>$id,'status'=>'failed','reason'=>'file_missing'];
                $this->logCron("ID $id file_missing");
                continue;
            }

            // --- NEW: handle existing creation_id safely: poll then publish only if FINISHED
            $creationId = isset($row['creation_id']) ? trim($row['creation_id']) : '';
            if ($creationId !== '') {
                // poll creation status once
                $pollUrl = 'https://graph.facebook.com/v19.0/' . rawurlencode($creationId) . '?fields=status_code&access_token=' . rawurlencode($token);
                $pollRes = $this->httpRequestWithRetry('GET', $pollUrl, [], 2, 500);

                if (!$pollRes['ok']) {
                    // network/transient issue: reschedule
                    $this->logCron("ID $id poll_network_issue: " . json_encode($pollRes));
                    $this->Instagram_reels_model->reschedule_for_retry($id, self::RETRY_DELAY_MINUTES);
                    $processed[]=['id'=>$id,'status'=>'delayed','reason'=>'poll_network_issue'];
                    continue;
                }

                $pdata = json_decode($pollRes['body'] ?? '{}', true);
                $statusCode = $pdata['status_code'] ?? null;

                if ($statusCode === 'FINISHED') {
                    // safe to publish now
                    $pubUrl = 'https://graph.facebook.com/v19.0/' . rawurlencode($row['ig_user_id']) . '/media_publish';
                    $postFields = http_build_query(['creation_id' => $creationId, 'access_token' => $token]);
                    $pubRes = $this->httpRequestWithRetry('POST', $pubUrl, ['post_fields' => $postFields, 'timeout' => 60], 2, 500);

                    if ($pubRes['ok'] && ($pubRes['http_code'] ?? 0) === 200) {
                        $pubBody = json_decode($pubRes['body'] ?? '{}', true);
                        $mediaId = $pubBody['id'] ?? null;
                        if ($mediaId) {
                            $this->Instagram_reels_model->mark_published($id,$mediaId,$creationId);
                            $this->logCron("ID $id published media=".$mediaId);
                            $processed[]=['id'=>$id,'status'=>'published'];
                            // proceed to handle comments below after marking published
                        } else {
                            // unexpected: publish returned 200 but no id
                            $this->logCron("ID $id publish_no_media_id: " . json_encode($pubRes));
                            $this->db->where('id',$id)->update('instagram_reels',['creation_id'=>null,'status'=>'pending']);
                            $processed[]=['id'=>$id,'status'=>'failed','reason'=>'publish_no_media_id'];
                            continue;
                        }
                    } else {
                        // publish failed - common case "Media ID is not available" or other FB error
                        $this->logCron("ID $id publish_failed_raw: " . json_encode($pubRes));
                        // clear creation_id so next run will recreate container
                        $this->db->where('id',$id)->update('instagram_reels',['creation_id'=>null,'status'=>'pending']);
                        $this->Instagram_reels_model->reschedule_for_retry($id, self::RETRY_DELAY_MINUTES);
                        $processed[]=['id'=>$id,'status'=>'failed','reason'=>'publish_failed'];
                        continue;
                    }

                    // if published and comments exist, post them
                    $rowAfter = $this->Instagram_reels_model->get_by_id($id);
                    if ($rowAfter && $rowAfter['media_id'] && !empty($rowAfter['comments_json'])) {
                        $comments = json_decode($rowAfter['comments_json'], true);
                        if (is_array($comments) && $comments) {
                            $comments_result = $this->post_reel_comments($rowAfter['media_id'], $comments, $token);
                            $first_comment_id = null;
                            foreach($comments_result as $cr){ if($cr['status']==='ok'){ $first_comment_id=$cr['comment_id']; break; } }
                            $this->db->where('id',$id)->update('instagram_reels',[
                                'first_comment_id'=>$first_comment_id,
                                'comments_publish_result_json'=>json_encode($comments_result,JSON_UNESCAPED_UNICODE),
                                'updated_at'=>gmdate('Y-m-d H:i:s')
                            ]);
                        }
                    }
                    // finished processing this row (either published or handled)
                    continue;
                } elseif ($statusCode === 'IN_PROGRESS') {
    // not ready yet — use longer wait for story videos and reels (they need more processing time)
    $specialDelay = self::RETRY_DELAY_MINUTES;
    if (!empty($row['media_kind']) && in_array($row['media_kind'], ['ig_story_video','ig_reel'], true)) {
        // give video content more time (e.g. 5 minutes)
        $specialDelay = 5;
    }
    $this->Instagram_reels_model->reschedule_for_retry($id, $specialDelay);
    $this->logCron("ID $id still IN_PROGRESS, rescheduled for {$specialDelay} minutes");
    $processed[]=['id'=>$id,'status'=>'delayed','reason'=>'in_progress'];
    continue;
                } else {
                    // unknown or ERROR state — clear creation and set to pending to recreate
                    $this->logCron("ID $id creation_invalid_or_error: " . json_encode($pdata));
                    $this->db->where('id',$id)->update('instagram_reels',['creation_id'=>null,'status'=>'pending']);
                    $processed[]=['id'=>$id,'status'=>'failed','reason'=>'creation_invalid'];
                    continue;
                }
            }
             // --- end creation_id handling ---

            // If no creation_id, create/publish flow via InstagramPublisher (existing logic)
            if($row['media_kind']==='ig_reel'){
                $res=$this->instagrampublisher->publishReel($row['ig_user_id'],$finalPath,$row['description'],$token);
            } elseif(in_array($row['media_kind'],['ig_story_image','ig_story_video'],true)){
                $type = $row['media_kind']==='ig_story_image'?'image':'video';
                $res=$this->instagrampublisher->publishStory($row['ig_user_id'],$finalPath,$type,$token);
            } else {
                $res=['ok'=>false,'error'=>'unsupported_kind'];
            }

            // handle negative result from publisher
            if(!$res['ok']){
                if(($row['attempt_count']+1)<self::MAX_CRON_ATTEMPTS){
                    $this->Instagram_reels_model->reschedule_for_retry($id,self::RETRY_DELAY_MINUTES);
                    $this->logCron("ID $id retry error=".$res['error']);
                } else {
                    $this->Instagram_reels_model->mark_failed($id,$res['error'] ?? 'unknown_error');
                    $this->logCron("ID $id failed final error=".$res['error']);
                }
                $processed[]=['id'=>$id,'status'=>'failed','reason'=>$res['error'] ?? 'unknown_error'];
                continue;
            }

            // on success (instagrampublisher returned ok), handle three cases safely
            if (!empty($res['media_id'])) {
                // Final published — mark as published and continue (comments handled below)
                $this->Instagram_reels_model->mark_published($id, $res['media_id'], $res['creation_id'] ?? null);
                $this->logCron("ID $id published media=" . $res['media_id']);
            } elseif (!empty($res['creation_id'])) {
                // Container created but processing not finished — store creation_id and set status 'publishing'
                $this->db->where('id', $id)->update('instagram_reels', [
                    'creation_id' => $res['creation_id'],
                    'status' => 'publishing',
                    'updated_at' => gmdate('Y-m-d H:i:s')
                ]);
                $this->logCron("ID $id container_created creation_id=" . $res['creation_id'] . " (processing)");
                $processed[] = ['id' => $id, 'status' => 'delayed', 'reason' => 'processing'];
                continue;
            } else {
                // unexpected: ok==true but neither media_id nor creation_id — treat as transient failure
                $this->logCron("ID $id unexpected_publish_result: " . json_encode($res));
                $this->Instagram_reels_model->reschedule_for_retry($id, self::RETRY_DELAY_MINUTES);
                $processed[] = ['id' => $id, 'status' => 'failed', 'reason' => 'no_media_id_no_creation_id'];
                continue;
            }

            // if we reach here, the row is published (media_id available)
            if($row['media_kind']==='ig_reel' && !empty($row['comments_json'])){
                $comments=json_decode($row['comments_json'],true);
                if(is_array($comments) && $comments){
                    $comments_result=$this->post_reel_comments($res['media_id'],$comments,$token);
                    $first_comment_id=null;
                    foreach($comments_result as $cr){
                        if($cr['status']==='ok'){ $first_comment_id=$cr['comment_id']; break; }
                    }
                    $this->db->where('id',$id)->update('instagram_reels',[
                        'first_comment_id'=>$first_comment_id,
                        'comments_publish_result_json'=>json_encode($comments_result,JSON_UNESCAPED_UNICODE),
                        'updated_at'=>gmdate('Y-m-d H:i:s')
                    ]);
                }
            }

            if($row['recurrence_kind']!=='none'){
                $nextTime=$this->Instagram_reels_model->calculate_next_time($row['scheduled_time'],$row['recurrence_kind']);
                if($nextTime && (empty($row['recurrence_until']) || $nextTime <= $row['recurrence_until'])){
                    $this->Instagram_reels_model->clone_next_recurrence($row,$nextTime);
                    $this->logCron("ID $id cloned recurrence next=$nextTime");
                }
            }

            $processed[]=['id'=>$id,'status'=>'published'];
        }

        if(php_sapi_name()==='cli'){
            echo "Processed: ".json_encode($processed,JSON_UNESCAPED_UNICODE).PHP_EOL;
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status'=>'ok','processed'=>$processed],JSON_UNESCAPED_UNICODE);
        }
    }


    public function cron_debug(){
        $this->requireLogin();
        $now = gmdate('Y-m-d H:i:s');
        $rows=$this->db->query("
            SELECT id, status, scheduled_time, attempt_count
            FROM instagram_reels
            WHERE status='scheduled'
            ORDER BY scheduled_time ASC
            LIMIT 30
        ")->result_array();

        $out=[];
        foreach($rows as $r){
            $reason='due';
            if(strtotime($r['scheduled_time']) > time()) $reason='future';
            elseif((int)$r['attempt_count'] >= self::MAX_CRON_ATTEMPTS) $reason='attempts_exceeded';
            $out[]=[
                'id'=>$r['id'],
                'scheduled_time'=>$r['scheduled_time'],
                'attempt_count'=>$r['attempt_count'],
                'status'=>$r['status'],
                'evaluation'=>$reason
            ];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['now'=>$now,'max_attempts'=>self::MAX_CRON_ATTEMPTS,'items'=>$out],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    public function hashtags_trend(){
        $this->requireLogin();
        $tags=['reels','instagram','viral','trending','explore','follow','like','fashion','business','music',
               'travel','fitness','design','marketing','arabic','life','video','fun','daily','creative'];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status'=>'ok','tags'=>$tags,'count'=>count($tags)],JSON_UNESCAPED_UNICODE);
    }

    private function saveUploadedFile(array $f,$user_id){
        if($f['error']!==UPLOAD_ERR_OK) return ['ok'=>false,'error'=>'upload_error_'.$f['error']];
        if($f['size']> self::MAX_FILE_SIZE_MB*1024*1024) return ['ok'=>false,'error'=>'max_size'];
        $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['mp4','jpg','jpeg','png'])) return ['ok'=>false,'error'=>'bad_ext'];
        $destDir=FCPATH.'uploads/instagram/';
        if(!is_dir($destDir)) @mkdir($destDir,0775,true);
        $newName=date('Ymd_His').'_'.$user_id.'_'.substr(md5($f['name'].microtime(true).rand()),0,10).'.'.$ext;
        $full=$destDir.$newName;
        if(!move_uploaded_file($f['tmp_name'],$full)) return ['ok'=>false,'error'=>'move_failed'];
        return ['ok'=>true,'new_name'=>$newName,'full_path'=>$full,'ext'=>$ext];
    }

    private function collectAllFiles($FILES){
        $col=[];
        foreach($FILES as $f){
            if(!isset($f['name'])) continue;
            if(is_array($f['name'])){
                foreach($f['name'] as $i=>$nm){
                    if($nm==='') continue;
                    $col[]=[
                        'name'=>$nm,
                        'type'=>$f['type'][$i],
                        'tmp_name'=>$f['tmp_name'][$i],
                        'error'=>$f['error'][$i],
                        'size'=>$f['size'][$i]
                    ];
                }
            } else {
                if($f['name']!=='') $col[]=$f;
            }
        }
        return $col;
    }

    private function post_reel_comments($media_id,array $comments,$accessToken){
        $out=[];
        $base='https://graph.facebook.com/v19.0/';
        foreach($comments as $i=>$msg){
            $msg=trim($msg);
            if($msg==='') continue;
            $url=$base.$media_id.'/comments';
            $postFields = http_build_query([
                'message' => $msg,
                'access_token' => $accessToken
            ]);
            $attempt = 0;
            $maxAttempts = 4;
            $delayMs = 500;
            $savedResult = null;
            while ($attempt < $maxAttempts) {
                $attempt++;
                $res = $this->httpRequestWithRetry('POST', $url, ['post_fields' => $postFields, 'timeout' => 60], 1, $delayMs);
                if (!$res['ok']) {
                    if ($attempt < $maxAttempts) {
                        usleep($delayMs * 1000);
                        $delayMs = min(60000, (int)($delayMs * 1.8));
                        continue;
                    }
                    $savedResult = ['i'=>$i+1,'status'=>'error','error'=>$res['error']];
                    break;
                }
                $code = $res['http_code'] ?? 0;
                $body = $res['body'] ?? '';
                $hdrs = $res['headers'] ?? [];
                $data = json_decode($body, true);
                if(isset($data['id'])){
                    $savedResult = ['i'=>$i+1,'status'=>'ok','comment_id'=>$data['id']];
                    break;
                }
                if(isset($data['error'])){
                    $err = $data['error'];
                    $errCode = $err['code'] ?? null;
                    $errMsg = $err['message'] ?? json_encode($err);
                    if ($errCode === 4 || stripos($errMsg, 'rate') !== false || in_array($code, [429,500,502,503,504], true)) {
                        if ($attempt < $maxAttempts) {
                            usleep($delayMs * 1000);
                            $delayMs = min(60000, (int)($delayMs * 1.8));
                            continue;
                        }
                        $savedResult = ['i'=>$i+1,'status'=>'error','http_code'=>$code,'error'=>'rate_limit_or_server','raw'=>$data];
                        break;
                    }
                    $savedResult = ['i'=>$i+1,'status'=>'error','http_code'=>$code,'error'=>$errMsg,'raw'=>$data];
                    break;
                }
                $savedResult = ['i'=>$i+1,'status'=>'error','http_code'=>$code,'raw'=>$data];
                break;
            }
            if($savedResult===null){ $savedResult=['i'=>$i+1,'status'=>'error','error'=>'unknown']; }
            $out[]=$savedResult;
            usleep(150000); // small throttle
        }
        return $out;
    }

    private function logUploadIssue($label,$files){
        @file_put_contents(APPPATH.'logs/ig_upload_debug.log',"[".gmdate('Y-m-d H:i:s')."] IG_UPLOAD_$label :: ".print_r($files,true)."\n",FILE_APPEND);
    }
    private function logCron($msg){
        @file_put_contents(APPPATH.'logs/ig_cron_debug.log',"[".gmdate('Y-m-d H:i:s')."] $msg\n",FILE_APPEND);
    }

    private function isAjax(){
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest';
    }
    private function respondError($msg,$extra=[]){
        if($this->isAjax()){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array_merge(['status'=>'error','message'=>$msg],$extra),JSON_UNESCAPED_UNICODE);
            return;
        }
        $this->session->set_flashdata('ig_error',$msg);
        redirect('instagram/upload');
    }
}
