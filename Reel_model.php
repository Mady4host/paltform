<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reel_model extends CI_Model
{
    private $video_exts = ['mp4','mov','mkv','m4v'];
    private $image_exts = ['jpg','jpeg','png','webp'];

    /* إعدادات التعليقات المجدولة */
    const COMMENT_MAX_ATTEMPTS        = 8;
    const COMMENT_RETRY_DELAY_SEC     = 120;
    const COMMENT_READY_TIMEOUT_TRIES = 5;

    /* القصص */
    const STORY_EXPIRE_SECONDS        = 86400;
    private $story_image_exts         = ['jpg','jpeg','png','gif','bmp','tiff','webp'];
    const FEATURE_STORIES             = true;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /* ================= صفحات =================
       الآن جميع عمليات الجلب للصفحات تعتمد على جدول المنصة facebook_rx_fb_page_info
       ونستخدم الحقول الموجودة في بنية جدولكم (page_id, page_name, page_profile/page_cover, page_access_token, username)
    */
    public function get_user_pages($user_id)
    {
        if (!$this->db->table_exists('facebook_rx_fb_page_info')) {
            // إذا لم يكن جدول المنصة موجودًا نرجع مصفوفة فارغة لتفادي الأخطاء
            return [];
        }

        $this->db->select("
            page_id AS fb_page_id,
            COALESCE(page_name, username, page_id) AS page_name,
            COALESCE(page_profile, page_cover, '') AS page_picture,
            COALESCE(page_access_token, '') AS page_access_token,
            id AS rx_id
        ", false);
        $this->db->from('facebook_rx_fb_page_info');
        $this->db->where('user_id', $user_id);
        $this->db->order_by('COALESCE(page_name, username, page_id)', 'ASC', false);
        $rows = $this->db->get()->result_array();

        foreach ($rows as $k => $p) {
            $fallback = 'https://graph.facebook.com/' . $p['fb_page_id'] . '/picture?type=normal';
            $chosen   = !empty($p['page_picture']) ? $p['page_picture'] : $fallback;
            $sep = (strpos($chosen,'?')===false)?'?':'&';
            $rows[$k]['_img'] = $chosen . $sep . 'v=' . substr(md5($p['fb_page_id'].time()),0,6);
            // بجعل fb_page_id نص لتفادي المقارنات الصارمة
            $rows[$k]['fb_page_id'] = (string)$rows[$k]['fb_page_id'];
        }

        return $rows;
    }

    /* ================= ريلز المستخدم ================= */
    public function get_user_reels($user_id)
    {
        return $this->db->where('user_id',$user_id)
                        ->order_by('id','DESC')
                        ->get('reels')->result_array();
    }

    /* ================= هاشتاجات ================= */
    public function get_trending_hashtags()
    {
        if ($this->db->table_exists('trending_hashtags')) {
            $today = gmdate('Y-m-d');
            $rows = $this->db->where('created_at',$today)
                             ->order_by('score','DESC')
                             ->limit(40)->get('trending_hashtags')->result_array();
            if ($rows) return array_column($rows,'tag');
            $this->regenerate_static_hashtags();
            $rows2 = $this->db->where('created_at',$today)->get('trending_hashtags')->result_array();
            if ($rows2) return array_column($rows2,'tag');
        }
        return [
            'viral','explore','foryou','funny','instagood','love','motivation',
            'summer2025','fashion','travel','trending','life','music','sport',
            'gaming','beauty','reels','fyp','art','food','happy','success'
        ];
    }
    public function regenerate_static_hashtags()
    {
        if (!$this->db->table_exists('trending_hashtags')) return;
        $today = gmdate('Y-m-d');
        $static = [
            'viral','explore','foryou','funny','instagood','love','motivation',
            'summer2025','fashion','travel','trending','life','music','sport',
            'gaming','beauty','reels','fyp','art','food','happy','success'
        ];
        foreach($static as $i=>$tag){
            $exists = $this->db->where('tag',$tag)->where('created_at',$today)
                               ->count_all_results('trending_hashtags');
            if(!$exists){
                $this->db->insert('trending_hashtags',[
                    'tag'=>$tag,'source'=>'static',
                    'score'=>(count($static)-$i)*10,
                    'created_at'=>$today
                ]);
            }
        }
    }

    /* ================= Helpers ================= */
    private function localToUtc(?string $local,$offset)
    {
        if(!$local) return null;
        if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$local)) return null;
        if(!is_numeric($offset)) $offset=0;
        $ts=strtotime($local);
        if($ts===false) return null;
        return gmdate('Y-m-d H:i:s',$ts + ((int)$offset*60));
    }
    private function isFutureUtc(?string $utc,$min=30)
    {
        if(!$utc) return false;
        $ts=strtotime($utc);
        return $ts!==false && $ts > time()+$min;
    }
    private function normalizeSelectedTags($raw)
    {
        $raw = trim($raw);
        if($raw==='') return '';
        $parts = preg_split('/\s+/u',$raw);
        $clean=[];
        foreach($parts as $p){
            $p=trim($p);
            if($p==='') continue;
            if($p[0] !== '#') $p = '#'.$p;
            $p = preg_replace('/[^#\p{L}\p{N}_]/u','',$p);
            if($p==='#') continue;
            $clean[strtolower($p)] = $p;
        }
        return implode(' ',array_values($clean));
    }
    private function writeLog($file,$line)
    {
        $dir=FCPATH.'application/logs/';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        @file_put_contents($dir.$file,'['.gmdate('Y-m-d H:i:s').'] '.$line.PHP_EOL,FILE_APPEND);
    }
    private function apiLog($phase,$job,$res)
    {
        $this->writeLog('reels_api.log',"$phase page={$job['fb_page_id']} file={$job['filename']} => ".(is_scalar($res)?$res:json_encode($res,JSON_UNESCAPED_UNICODE)));
    }
    private function apiLogScheduled($phase,$pageId,$fileName,$res)
    {
        $this->writeLog('reels_api_scheduled.log',"$phase page=$pageId file=$fileName => ".(is_scalar($res)?$res:json_encode($res,JSON_UNESCAPED_UNICODE)));
    }
    private function commentLog($phase,$data)
    {
        $this->writeLog('reels_comments.log',"$phase ".(is_scalar($data)?$data:json_encode($data,JSON_UNESCAPED_UNICODE)));
    }

    /**
     * تحسين findPage ليقبل صفوف من جدول المنصة أو الجدول القديم (توافق)
     */
    private function findPage($pages,$id){
        foreach($pages as $p){
            if(!is_array($p)) continue;
            if((isset($p['fb_page_id']) && (string)$p['fb_page_id'] === (string)$id) ||
               (isset($p['page_id']) && (string)$p['page_id'] === (string)$id) ){
                return $p;
            }
        }
        return null;
    }

    private function runMulti($mh)
    {
        $running=null;
        do{
            curl_multi_exec($mh,$running);
            curl_multi_select($mh);
        }while($running>0);
    }
    private function getVideoStatus($video_id,$access_token)
    {
        $url="https://graph.facebook.com/v23.0/{$video_id}?fields=status&access_token=".urlencode($access_token);
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
        $res=curl_exec($ch); curl_close($ch);
        $j=json_decode($res,true);
        return $j['status']['video_status'] ?? null;
    }
    private function columnExists($table,$column){
        static $cache=[];
        $key=$table.':'.$column;
        if(isset($cache[$key])) return $cache[$key];
        $exists=false;
        if($this->db->table_exists($table)){
            $fields=$this->db->list_fields($table);
            $exists=in_array($column,$fields);
        }
        $cache[$key]=$exists;
        return $exists;
    }
    private function graphVersion(){ return 'v23.0'; }

    /* =========================================================
       رفع الريلز (كما كان مع إضافة media_type=reel عند الإدخال)
       لم أغير أي منطق الرفع/الانتهاء/الجدولة — التغيير فقط حول مصدر الصفحات عند الحاجة للـ access token
       ========================================================= */
    public function upload_reels($user_id,$pages,$post,$files)
{
    // نسخة محسنة من الدالة الأصلية مع تسجيل أكبر ومعالجة استجابات cURL غير الصالحة
    $this->writeLog('reels_api.log','UPLOAD_REELS_CALL user='.$user_id.' files='.(@json_encode($files['video_files']['name']??[])));
    $responses=[];
    $fb_page_ids    = $post['fb_page_ids']    ?? [];
    $descriptions   = $post['descriptions']   ?? [];
    $schedule_times = $post['schedule_times'] ?? [];
    $comments       = $post['comments']       ?? [];
    $video_files    = $files['video_files']   ?? null;
    $tz_offset      = $post['tz_offset_minutes'] ?? 0;
    $tz_name        = $post['tz_name'] ?? null;
    $global_desc    = trim($post['description'] ?? '');
    $selected_tags  = $this->normalizeSelectedTags($post['selected_hashtags'] ?? '');

    if(!$video_files || empty($video_files['name'])){
        $this->writeLog('reels_api.log','UPLOAD_REELS_NOFILES user='.$user_id);
        return [['type'=>'error','msg'=>'لا توجد ملفات']];
    }

    $covers_uploaded = $_FILES['cover_uploaded'] ?? null;
    $covers_captured = $_FILES['cover_captured'] ?? null;

    $jobs=[];
    $cnt=count($video_files['name']);
    for($i=0;$i<$cnt;$i++){
        $fname  = $video_files['name'][$i];
        $tmp    = $video_files['tmp_name'][$i];
        $err    = $video_files['error'][$i];
        if($err!==UPLOAD_ERR_OK || !is_file($tmp)){
            $responses[]=['type'=>'error','msg'=>"فشل الملف: $fname"];
            $this->writeLog('reels_api.log',"UPLOAD_REELS_FILE_INVALID name=$fname err=$err tmp=$tmp");
            continue;
        }
        $ext=strtolower(pathinfo($fname,PATHINFO_EXTENSION));
        if(!in_array($ext,$this->video_exts)){
            $responses[]=['type'=>'error','msg'=>"امتداد غير مدعوم: $fname"];
            $this->writeLog('reels_api.log',"UPLOAD_REELS_BAD_EXT name=$fname ext=$ext");
            continue;
        }
        $size=filesize($tmp);
        $file_desc=trim($descriptions[$i] ?? '');
        $baseName = pathinfo($fname,PATHINFO_FILENAME);

        if($file_desc!=='')      $caption=$file_desc;
        elseif($global_desc!=='')$caption=$global_desc;
        else                     $caption=$baseName;

        if($selected_tags!==''){
            foreach(explode(' ',$selected_tags) as $tg){
                if($tg==='' ) continue;
                if(stripos($caption,$tg)===false){
                    $caption.=' '.$tg;
                }
            }
        }

        $local_sched = $schedule_times[$i] ?? '';
        $utc_sched   = $this->localToUtc($local_sched,$tz_offset);

        foreach($fb_page_ids as $pid){
            $page=$this->findPage($pages,$pid);
            if(!$page){
                $responses[]=['type'=>'error','msg'=>"صفحة غير موجودة: $pid"];
                $this->writeLog('reels_api.log',"UPLOAD_REELS_PAGE_NOT_FOUND pid=$pid file=$fname");
                continue;
            }
            if(empty($page['page_access_token'])){
                $responses[]=['type'=>'error','msg'=>"توكن مفقود للصفحة: $pid"];
                $this->writeLog('reels_api.log',"UPLOAD_REELS_MISSING_TOKEN pid=$pid file=$fname page=".json_encode($page));
                continue;
            }
            $jobs[]=[
                'fb_page_id'=>$pid,
                'page_access_token'=>$page['page_access_token'],
                'tmp_name'=>$tmp,
                'file_size'=>$size,
                'filename'=>$fname,
                'final_caption'=>$caption,
                'local_schedule'=>$local_sched,
                'utc_schedule'=>$utc_sched,
                'tz_offset_minutes'=>$tz_offset,
                'tz_name'=>$tz_name,
                'index'=>$i,
                'raw_comments'=>$comments[$i] ?? []
            ];
        }
    }

    if(!$jobs) {
        $this->writeLog('reels_api.log','UPLOAD_REELS_NO_JOBS user='.$user_id);
        return $responses ?: [['type'=>'error','msg'=>'لا وظائف صالحة']];
    }

    $mh = curl_multi_init();
    $handles = [];

    try {
        /* START */
        foreach($jobs as $idx=>$job){
            $url="https://graph.facebook.com/{$this->graphVersion()}/{$job['fb_page_id']}/video_reels";
            $data=['upload_phase'=>'start','access_token'=>$job['page_access_token']];
            $ch=curl_init($url);
            curl_setopt_array($ch,[
                CURLOPT_POST=>1,
                CURLOPT_POSTFIELDS=>json_encode($data),
                CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER=>1,
                CURLOPT_SSL_VERIFYPEER=>false
            ]);
            $handles[$idx]=['job'=>$job,'start_ch'=>$ch];
            curl_multi_add_handle($mh,$ch);
        }
        $this->runMulti($mh);
        foreach($handles as $idx=>&$h){
            $raw = @curl_multi_getcontent($h['start_ch']);
            if($raw === false || $raw === null) {
                $this->writeLog('reels_api.log',"UPLOAD_REELS_START_EMPTY page={$h['job']['fb_page_id']} file={$h['job']['filename']}");
                $res = null;
            } else {
                $this->writeLog('reels_api.log',"UPLOAD_REELS_START_RESP page={$h['job']['fb_page_id']} file={$h['job']['filename']} resp_preview=".substr($raw,0,1000));
                $res = json_decode($raw, true);
                if($res === null && json_last_error() !== JSON_ERROR_NONE){
                    // سجّل الاستجابة الخام إذا لم تُفكّك JSON
                    $this->writeLog('reels_api.log',"UPLOAD_REELS_START_JSON_FAIL page={$h['job']['fb_page_id']} raw_preview=".substr($raw,0,2000));
                }
            }
            $this->apiLog('START',$h['job'],$res);
            @curl_multi_remove_handle($mh,$h['start_ch']); @curl_close($h['start_ch']);
            unset($h['start_ch']);
            if(empty($res['video_id'])){
                $h['error']=true;
                $responses[]=['type'=>'error','msg'=>"فشل بدء الرفع: {$h['job']['filename']} ({$h['job']['fb_page_id']})"];
                $this->writeLog('reels_api.log',"UPLOAD_REELS_START_FAIL page={$h['job']['fb_page_id']} file={$h['job']['filename']} res=".(@$raw?:'empty'));
            } else {
                $h['video_id']=$res['video_id'];
                $this->writeLog('reels_api.log',"UPLOAD_REELS_START_OK page={$h['job']['fb_page_id']} video_id=".$h['video_id']);
            }
        } unset($h);

        /* UPLOAD */
        foreach($handles as $idx=>&$h){
            if(!empty($h['error'])) continue;
            $url="https://rupload.facebook.com/video-upload/{$this->graphVersion()}/{$h['video_id']}";
            $ch=curl_init($url);

            // استخدم نفس طريقة سابقة (POSTFIELDS) لكن سجل الاستجابة بحذر
            curl_setopt_array($ch,[
                CURLOPT_HTTPHEADER=>[
                    "Authorization: OAuth {$h['job']['page_access_token']}",
                    "offset: 0",
                    "file_size: {$h['job']['file_size']}"
                ],
                CURLOPT_POST=>1,
                CURLOPT_POSTFIELDS=>file_get_contents($h['job']['tmp_name']),
                CURLOPT_RETURNTRANSFER=>1,
                CURLOPT_SSL_VERIFYPEER=>false
            ]);
            $h['upload_ch']=$ch;
            curl_multi_add_handle($mh,$ch);
        } unset($h);
        $this->runMulti($mh);
        foreach($handles as $idx=>&$h){
            if(!empty($h['error'])) continue;
            $raw = @curl_multi_getcontent($h['upload_ch']);
            if($raw === false || $raw === null){
                $this->writeLog('reels_api.log',"UPLOAD_REELS_UPLOAD_EMPTY page={$h['job']['fb_page_id']} file={$h['job']['filename']}");
                $res = null;
            } else {
                $this->writeLog('reels_api.log',"UPLOAD_REELS_UPLOAD_RESP page={$h['job']['fb_page_id']} file={$h['job']['filename']} resp_preview=".substr($raw,0,1000));
                $res = json_decode($raw,true);
                if($res === null && json_last_error() !== JSON_ERROR_NONE){
                    $this->writeLog('reels_api.log',"UPLOAD_REELS_UPLOAD_JSON_FAIL page={$h['job']['fb_page_id']} raw_preview=".substr($raw,0,2000));
                    // محاولة كشف success string شائعة
                    if(strpos($raw,'"success"')!==false || strpos($raw,'Upload Successful')!==false){
                        $res = ['success'=>true,'message'=>'ok'];
                    }
                }
            }
            $this->apiLog('UPLOAD',$h['job'],$res);
            @curl_multi_remove_handle($mh,$h['upload_ch']); @curl_close($h['upload_ch']);
            unset($h['upload_ch']);
            if(isset($res['error'])){
                $h['error']=true;
                $responses[]=['type'=>'error','msg'=>"فشل رفع البيانات: {$h['job']['filename']} ({$h['job']['fb_page_id']})"];
                $this->writeLog('reels_api.log',"UPLOAD_REELS_UPLOAD_FAIL page={$h['job']['fb_page_id']} file={$h['job']['filename']} res=".(@$raw?:'empty'));
            } else {
                $this->writeLog('reels_api.log',"UPLOAD_REELS_UPLOAD_OK page={$h['job']['fb_page_id']} file={$h['job']['filename']}");
            }
        } unset($h);

        /* FINISH */
        $coverDir=FCPATH.'uploads/reels_covers/';
        if(!is_dir($coverDir)) @mkdir($coverDir,0775,true);

        foreach($handles as $idx=>&$h){
            if(!empty($h['error'])) continue;
            $scheduled=false;
            $utc=$h['job']['utc_schedule'];
            if($utc && $this->isFutureUtc($utc,60)){
                $scheduled=true;
                $ts=strtotime($utc.' UTC');
            }
            $finishData=[
                'access_token'=>$h['job']['page_access_token'],
                'video_id'=>$h['video_id'],
                'upload_phase'=>'finish',
                'description'=>$h['job']['final_caption']
            ];
            if($scheduled){
                $finishData['scheduled_publish_time']=$ts;
                $finishData['published']='0';
            } else {
                $finishData['video_state']='PUBLISHED';
            }
            $ch=curl_init("https://graph.facebook.com/{$this->graphVersion()}/{$h['job']['fb_page_id']}/video_reels");
            curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>http_build_query($finishData),CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
            $h['finish_ch']=$ch;
            $h['scheduled']=$scheduled;
            curl_multi_add_handle($mh,$ch);
        } unset($h);
        $this->runMulti($mh);
        foreach($handles as $idx=>&$h){
            if(!empty($h['error'])) continue;
            $raw = @curl_multi_getcontent($h['finish_ch']);
            if($raw === false || $raw === null){
                $this->writeLog('reels_api.log',"UPLOAD_REELS_FINISH_EMPTY page={$h['job']['fb_page_id']} file={$h['job']['filename']}");
                $res=null;
            } else {
                $this->writeLog('reels_api.log',"UPLOAD_REELS_FINISH_RESP page={$h['job']['fb_page_id']} file={$h['job']['filename']} resp_preview=".substr($raw,0,1000));
                $res=json_decode($raw,true);
                if($res===null && json_last_error()!==JSON_ERROR_NONE){
                    $this->writeLog('reels_api.log',"UPLOAD_REELS_FINISH_JSON_FAIL page={$h['job']['fb_page_id']} raw_preview=".substr($raw,0,2000));
                }
            }
            $this->apiLog('FINISH',$h['job'],$res);
            @curl_multi_remove_handle($mh,$h['finish_ch']); @curl_close($h['finish_ch']);
            unset($h['finish_ch']);
            if(isset($res['error'])){
                $responses[]=['type'=>'error','msg'=>"فشل إنهاء الرفع: {$h['job']['filename']} ({$h['job']['fb_page_id']})"];
                $this->writeLog('reels_api.log',"UPLOAD_REELS_FINISH_FAIL page={$h['job']['fb_page_id']} file={$h['job']['filename']} res=".(@$raw?:'empty'));
                continue;
            }

            // أغلفة
            $idxJob = $h['job']['index'];
            $cover_path=null; $cover_source=null;

            if(isset($covers_uploaded['name'][$idxJob]) && $covers_uploaded['name'][$idxJob]!==''){
                $cErr=$covers_uploaded['error'][$idxJob];
                $cTmp=$covers_uploaded['tmp_name'][$idxJob];
                $cName=$covers_uploaded['name'][$idxJob];
                if($cErr===UPLOAD_ERR_OK && is_file($cTmp)){
                    $ext=strtolower(pathinfo($cName,PATHINFO_EXTENSION));
                    if(in_array($ext,$this->image_exts)){
                        $stored='cover_up_'.time().'_'.$idxJob.'_'.mt_rand(1000,9999).'.'.$ext;
                        if(move_uploaded_file($cTmp,$coverDir.$stored)){
                            $cover_path='uploads/reels_covers/'.$stored;
                            $cover_source='uploaded';
                        }
                    }
                }
            } elseif(isset($covers_captured['name'][$idxJob]) && $covers_captured['name'][$idxJob]!==''){
                $cErr=$covers_captured['error'][$idxJob];
                $cTmp=$covers_captured['tmp_name'][$idxJob];
                if($cErr===UPLOAD_ERR_OK && is_file($cTmp)){
                    $stored='cover_cap_'.time().'_'.$idxJob.'_'.mt_rand(1000,9999).'.png';
                    if(move_uploaded_file($cTmp,$coverDir.$stored)){
                        $cover_path='uploads/reels_covers/'.$stored;
                        $cover_source='captured';
                    }
                }
            }

            $original_local_time = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$h['job']['local_schedule'])
                ? str_replace('T',' ',$h['job']['local_schedule']).':00'
                : null;

            $insertData = [
                'user_id'                =>$user_id,
                'fb_page_id'             =>$h['job']['fb_page_id'],
                'video_id'               =>$h['video_id'],
                'file_name'              =>$h['job']['filename'],
                'file_path'              =>NULL,
                'cover_path'             =>$cover_path,
                'cover_source'           =>$cover_source,
                'description'            =>$h['job']['final_caption'],
                'scheduled_at'           =>$h['scheduled'] ? $h['job']['utc_schedule'] : null,
                'original_local_time'    =>$original_local_time,
                'original_offset_minutes'=>$h['job']['tz_offset_minutes'],
                'original_timezone'      =>$h['job']['tz_name'],
                'status'                 =>$h['scheduled'] ? 'pending':'published',
                'created_at'             =>gmdate('Y-m-d H:i:s')
            ];
            if($this->columnExists('reels','media_type')) $insertData['media_type']='reel';

            // إدراج مع التحقق من أخطاء القاعدة
            try {
                $this->db->insert('reels',$insertData);
                $dberr=$this->db->error();
                if(!empty($dberr['code'])){
                    $this->writeLog('reels_api.log',"UPLOAD_REELS_DB_INSERT_FAIL page={$h['job']['fb_page_id']} file={$h['job']['filename']} db=".json_encode($dberr));
                    $responses[]=['type'=>'error','msg'=>"فشل إدراج قاعدة البيانات: {$h['job']['filename']} ({$h['job']['fb_page_id']})"];
                } else {
                    $this->writeLog('reels_api.log',"UPLOAD_REELS_DB_INSERT_OK page={$h['job']['fb_page_id']} file={$h['job']['filename']}");
                }
            } catch(\Throwable $e){
                $this->writeLog('reels_api.log',"UPLOAD_REELS_DB_EXCEPTION page={$h['job']['fb_page_id']} file={$h['job']['filename']} ex=".$e->getMessage());
                $responses[]=['type'=>'error','msg'=>"DB exception: {$h['job']['filename']} ({$h['job']['fb_page_id']})"];
            }

            $this->handle_video_comments(
                $user_id,
                $h['job']['fb_page_id'],
                $h['video_id'],
                $h['job']['raw_comments'],
                $h['job']['tz_offset_minutes'],
                $h['scheduled'],
                $h['job']['utc_schedule'],
                $h['job']['page_access_token']
            );

            $responses[]=['type'=>'success','msg'=>"تم رفع {$h['job']['filename']} على {$h['job']['fb_page_id']}".($h['scheduled']?' (مجدول)':'')];
        }
        unset($h);

        curl_multi_close($mh);
        return $responses;

    } catch(\Throwable $ex) {
        $this->writeLog('reels_api.log','UPLOAD_REELS_UNCAUGHT ex='.$ex->getMessage().' trace='.substr($ex->getTraceAsString(),0,2000));
        // حاول تنظيف المقابض المفتوحة
        if(!empty($handles) && isset($mh) && is_resource($mh)){
            foreach($handles as $h){
                foreach(['start_ch','upload_ch','finish_ch'] as $k){
                    if(!empty($h[$k]) && is_resource($h[$k])){
                        @curl_multi_remove_handle($mh,$h[$k]);
                        @curl_close($h[$k]);
                    }
                }
            }
            @curl_multi_close($mh);
        } elseif(isset($mh) && is_resource($mh)){
            @curl_multi_close($mh);
        }
        // أحبط المحاولة بعرض خطأ عام
        return [['type'=>'error','msg'=>'خطأ داخلي أثناء الرفع']];
    }
}

    /* التعليقات كما هي */
    private function handle_video_comments($user_id,$fb_page_id,$video_id,$raw_comments,$tz_offset,$video_scheduled,$video_utc_schedule,$page_token)
    {
        if(!$raw_comments) return;
        if(!$this->db->table_exists('scheduled_comments')) return;
        $now=time();
        foreach($raw_comments as $c){
            $text=trim($c['text'] ?? '');
            $local=trim($c['schedule'] ?? '');
            if($text==='') continue;
            $utc = $local ? $this->localToUtc($local,$tz_offset) : null;

            if($video_scheduled){
                $schedule_time = $utc ?: $video_utc_schedule;
                $vts = strtotime($video_utc_schedule);
                $cts = strtotime($schedule_time);
                if($cts <= $vts) $schedule_time = gmdate('Y-m-d H:i:s',$vts+300);
                $this->db->insert('scheduled_comments',[
                    'scheduled_reel_id'=>NULL,'user_id'=>$user_id,'fb_page_id'=>$fb_page_id,'video_id'=>$video_id,
                    'comment_text'=>$text,'scheduled_time'=>$schedule_time,'status'=>'pending','attempt_count'=>0,
                    'last_error'=>NULL,'created_at'=>gmdate('Y-m-d H:i:s')
                ]);
                continue;
            }

            if($utc && strtotime($utc) > $now+15){
                $this->db->insert('scheduled_comments',[
                    'scheduled_reel_id'=>NULL,'user_id'=>$user_id,'fb_page_id'=>$fb_page_id,'video_id'=>$video_id,
                    'comment_text'=>$text,'scheduled_time'=>$utc,'status'=>'pending','attempt_count'=>0,
                    'last_error'=>NULL,'created_at'=>gmdate('Y-m-d H:i:s')
                ]);
            } else {
                $res=$this->post_comment_now($video_id,$page_token,$text);
                $j=json_decode($res,true);
                if(isset($j['error'])){
                    $this->commentLog('IMMEDIATE_FAIL',['video_id'=>$video_id,'error'=>$j['error']]);
                    $this->db->insert('scheduled_comments',[
                        'scheduled_reel_id'=>NULL,'user_id'=>$user_id,'fb_page_id'=>$fb_page_id,'video_id'=>$video_id,
                        'comment_text'=>$text,'scheduled_time'=>gmdate('Y-m-d H:i:s'),'status'=>'failed',
                        'attempt_count'=>1,'last_error'=>substr(json_encode($j['error'],JSON_UNESCAPED_UNICODE),0,500),
                        'created_at'=>gmdate('Y-m-d H:i:s')
                    ]);
                } else {
                    $this->commentLog('IMMEDIATE_OK',['video_id'=>$video_id]);
                    $this->db->insert('scheduled_comments',[
                        'scheduled_reel_id'=>NULL,'user_id'=>$user_id,'fb_page_id'=>$fb_page_id,'video_id'=>$video_id,
                        'comment_text'=>$text,'scheduled_time'=>gmdate('Y-m-d H:i:s'),'status'=>'posted',
                        'attempt_count'=>1,'last_error'=>NULL,'posted_time'=>gmdate('Y-m-d H:i:s'),
                        'created_at'=>gmdate('Y-m-d H:i:s')
                    ]);
                }
            }
        }
    }
    private function post_comment_now($video_id,$access_token,$message)
    {
        $url="https://graph.facebook.com/v23.0/{$video_id}/comments";
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>http_build_query(['access_token'=>$access_token,'message'=>$message]),CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
        $r=curl_exec($ch); curl_close($ch);
        return $r;
    }

    /* ================= جدولة محلية ================= */
public function get_due_scheduled_reels(int $limit = 40): array
{
    $now = gmdate('Y-m-d H:i:s');

    $this->db->from('scheduled_reels');
    $this->db->where('processing', 0);

    // اسحب pending و scheduled وأي حالة فاضية اتسجلت بالخطأ
    $this->db->group_start();
        $this->db->where('status', 'pending');
        $this->db->or_where('status', 'scheduled');
        $this->db->or_where('status', '');
        $this->db->or_where('status IS NULL', null, false);
    $this->db->group_end();

    // مستحق حتى الآن
    $this->db->where('scheduled_time <=', $now);
    $this->db->order_by('scheduled_time', 'ASC');
    $this->db->limit(max(1,$limit));

    $rows = $this->db->get()->result_array();
    return $rows ?: [];
}

public function process_scheduled_reel($row)
{
    try {
        // سجل بداية المعالجة
        $this->writeLog('reels_api_scheduled.log','PROCESS_SCHEDULED_START id='.$row['id'].' file='.$row['video_path'].' page='.$row['fb_page_id']);

        // (ابقى على نفس جلب الصفحة كما كان - لتفادي كسر توافق)
        $page = $this->db->get_where('facebook_rx_fb_page_info',[
            'page_id'=>$row['fb_page_id'],
            'user_id'=>$row['user_id']
        ])->row_array();

        $attempt = (int)$row['attempt_count'] + 1;
        $this->db->where('id',$row['id'])->update('scheduled_reels',[
            'attempt_count'=>$attempt,
            'last_attempt_at'=>gmdate('Y-m-d H:i:s')
        ]);

        // تأكد من مسار الملف بشكل آمن
        $abs = FCPATH . ltrim($row['video_path'], '/');
        if (!is_file($abs)) {
            $this->writeLog('reels_api_scheduled.log','PROCESS_FAIL missing_file id='.$row['id'].' abs='.$abs);
            $this->failScheduled($row,$attempt,'الملف غير موجود');
            return;
        }

        if (!$page || empty($page['page_access_token'])) {
            $this->writeLog('reels_api_scheduled.log','PROCESS_FAIL missing_token id='.$row['id'].' page_lookup='.json_encode($page));
            $this->failScheduled($row,$attempt,'توكن الصفحة مفقود');
            return;
        }

        $pageToken = $page['page_access_token'];
        $fileName = basename($row['video_path']);

        // START
        $sUrl = "https://graph.facebook.com/{$this->graphVersion()}/{$row['fb_page_id']}/video_reels";
        $sData = ['upload_phase'=>'start','access_token'=>$pageToken];
        $sRes = $this->curlJson($sUrl,$sData);
        $sJson = json_decode($sRes,true);
        $this->apiLogScheduled('START',$row['fb_page_id'],$fileName,$sJson);
        if (empty($sJson['video_id'])) {
            $this->writeLog('reels_api_scheduled.log','PROCESS_FAIL start_no_video_id id='.$row['id'].' resp='.($sRes?:json_encode($sJson)));
            $this->failScheduled($row,$attempt,'فشل START');
            return;
        }
        $video_id = $sJson['video_id'];

        // UPLOAD
        $uUrl = "https://rupload.facebook.com/video-upload/{$this->graphVersion()}/{$video_id}";
        $uHeaders = [
            "Authorization: OAuth {$pageToken}",
            "offset: 0",
            "file_size: " . filesize($abs)
        ];
        $uRes = $this->curlBinary($uUrl,$abs,$uHeaders);

        // تحقق من استجابة الرفع (قد تكون false/null عند فشل cURL أو عند فشل فتح الملف)
        if ($uRes === false || $uRes === null) {
            $this->writeLog('reels_api_scheduled.log','PROCESS_FAIL upload_no_response id='.$row['id'].' file='.$abs);
            $this->failScheduled($row,$attempt,'فشل UPLOAD (no response)');
            return;
        }

        $uJson = json_decode($uRes,true);
        $this->apiLogScheduled('UPLOAD',$row['fb_page_id'],$fileName,$uJson);
        if (isset($uJson['error'])) {
            $this->writeLog('reels_api_scheduled.log','PROCESS_FAIL upload_error id='.$row['id'].' err='.json_encode($uJson));
            $this->failScheduled($row,$attempt,'فشل UPLOAD');
            return;
        }

        // FINISH
        $fUrl = "https://graph.facebook.com/{$this->graphVersion()}/{$row['fb_page_id']}/video_reels";
        $fData = [
            'access_token'=>$pageToken,
            'video_id'=>$video_id,
            'upload_phase'=>'finish',
            'description'=>$row['description'],
            'video_state'=>'PUBLISHED'
        ];
        $fRes = $this->curlForm($fUrl,$fData);
        $fJson = json_decode($fRes,true);
        $this->apiLogScheduled('FINISH',$row['fb_page_id'],$fileName,$fJson);
        if (isset($fJson['error'])) {
            $this->writeLog('reels_api_scheduled.log','PROCESS_FAIL finish_error id='.$row['id'].' err='.json_encode($fJson));
            $this->failScheduled($row,$attempt,'فشل FINISH');
            return;
        }

        // حدّث حالة scheduled_reels مبكراً (كما كان)
        $this->db->where('id',$row['id'])->update('scheduled_reels',[
            'status'=>'uploaded','fb_response'=>$video_id,'published_time'=>gmdate('Y-m-d H:i:s'),
            'processing'=>0,'last_error'=>NULL
        ]);
        $dbErrPre = $this->db->error();
        if (!empty($dbErrPre['code'])) {
            $this->writeLog('reels_api_scheduled.log','PROCESS_WARN scheduled_update_db_error id='.$row['id'].' dbErr='.json_encode($dbErrPre));
            // لا ننهِ هنا لأننا نريد محاولة إدخال سجل الـ reels أيضاً، لكن نسجل التحذير.
        }

        // تحضير إدخال الـ reels مع مراعاة الأعمدة الاختيارية
        $insertData = [
            'user_id'                =>$row['user_id'],
            'fb_page_id'             =>$row['fb_page_id'],
            'video_id'               =>$video_id,
            'file_name'              =>$fileName,
            'file_path'              =>$row['video_path'],
            'cover_path'             =>$row['cover_path'] ?? null,
            'cover_source'           =>$row['cover_source'] ?? null,
            'description'            =>$row['description'],
            'scheduled_at'           =>$row['scheduled_time'],
            'original_local_time'    =>$row['original_local_time'],
            'original_offset_minutes'=>$row['original_offset_minutes'],
            'original_timezone'      =>$row['original_timezone'],
            'status'                 =>'published',
            'created_at'             =>gmdate('Y-m-d H:i:s')
        ];
        if($this->columnExists('reels','media_type')) $insertData['media_type']='reel';

        // إدراج داخل transaction واختبار أخطاء DB مباشرة بعد الإدراج
        $this->db->trans_start();
        $this->db->insert('reels',$insertData);
        $this->db->trans_complete();

        $dbErr = $this->db->error();
        if (!empty($dbErr['code'])) {
            $this->writeLog('reels_api_scheduled.log','PROCESS_FAIL db_error id='.$row['id'].' code='.$dbErr['code'].' message='.$dbErr['message'].' insertData='.json_encode($insertData));
            // رجّع الحالة إلى pending أو failed حسب المحاولات
            $this->failScheduled($row,$attempt,'DB error: '.$dbErr['message']);
            return;
        }

        // تحديث التعليقات المجدولة المرتبطة (إن وُجدت)
        if($this->db->table_exists('scheduled_comments')){
            $this->db->where('video_id IS NULL',NULL,false)
                     ->where('scheduled_reel_id',$row['id'])
                     ->update('scheduled_comments',['video_id'=>$video_id]);
            $dbErr2 = $this->db->error();
            if (!empty($dbErr2['code'])) {
                $this->writeLog('reels_api_scheduled.log','PROCESS_WARN scheduled_comments_update_failed id='.$row['id'].' db_err='.json_encode($dbErr2));
            }
        }

        // سجل نجاح ونهاية
        $this->logSched($row,$attempt,'success','تم نشر مجدول video_id='.$video_id);
        $this->writeLog('reels_api_scheduled.log','PROCESS_SCHEDULED_COMPLETE id='.$row['id'].' video_id='.$video_id);
        return;

    } catch (\Throwable $ex) {
        // أي استثناء مفاجئ: سجّله وعلّمه في scheduled_reels كي لا يعطي 500 صامت
        $this->writeLog('reels_api_scheduled.log','UNCAUGHT_EXCEPTION id='.$row['id'].' msg='.$ex->getMessage().' trace='.$ex->getTraceAsString());
        $attempt = (int)($row['attempt_count'] ?? 0) + 1;
        $this->failScheduled($row,$attempt,'Unhandled exception: '.$ex->getMessage());
        return;
    }
}

    private function failScheduled($row,$attempt,$msg)
    {
        $status=($attempt>=5)?'failed':'pending';
        $this->db->where('id',$row['id'])->update('scheduled_reels',[
            'status'=>$status,'last_error'=>$msg,'processing'=>0
        ]);
        $this->logSched($row,$attempt,'failed',$msg);
    }
    private function logSched($row,$attempt,$status,$message)
    {
        $data=[
            'scheduled_reel_id'=>$row['id'],'user_id'=>$row['user_id'],'fb_page_id'=>$row['fb_page_id'],
            'attempt_number'=>$attempt,'status'=>$status,'message'=>$message,'created_at'=>gmdate('Y-m-d H:i:s')
        ];
        if($this->columnExists('scheduled_reels_logs','media_type') && isset($row['media_type'])){
            $data['media_type']=$row['media_type'];
        }
        $this->db->insert('scheduled_reels_logs',$data);
    }
    public function get_scheduled_logs($user_id,$scheduled_id)
    {
        return $this->db->where('user_id',$user_id)
                        ->where('scheduled_reel_id',$scheduled_id)
                        ->order_by('id','DESC')
                        ->get('scheduled_reels_logs')->result_array();
    }

    /* ================= التعليقات المجدولة ================= */
    public function get_due_scheduled_comments($limit=60)
    {
        if(!$this->db->table_exists('scheduled_comments')) return [];
        $rows=$this->db->query("
            SELECT * FROM scheduled_comments
            WHERE status='pending'
              AND video_id IS NOT NULL
              AND scheduled_time <= UTC_TIMESTAMP()
            ORDER BY scheduled_time ASC
            LIMIT ?
        ",[$limit])->result_array();
        if($rows){
            $ids=array_column($rows,'id');
            $this->db->where_in('id',$ids)->update('scheduled_comments',['status'=>'processing']);
        }
        return $rows;
    }

    public function process_scheduled_comment($row)
    {
        // اقرأ بيانات الصفحة من جدول المنصة بدلاً من الجدول القديم
        $page = $this->db->get_where('facebook_rx_fb_page_info',[
            'page_id'=>$row['fb_page_id'],
            'user_id'=>$row['user_id']
        ])->row_array();

        if(!$page || empty($page['page_access_token'])){
            $this->failComment($row,'توكن مفقود'); return;
        }
        if(empty($row['video_id'])){
            $this->failComment($row,'video_id فارغ'); return;
        }
        $status = $this->getVideoStatus($row['video_id'],$page['page_access_token']);
        if($status && $status!=='ready'){
            $this->rescheduleComment($row,'video not ready: '.$status); return;
        }
        $res=$this->post_comment_now($row['video_id'],$page['page_access_token'],$row['comment_text']);
        $j=json_decode($res,true);
        if(isset($j['error'])){
            $err = $j['error'];
            $code = $err['code'] ?? null;
            $sub  = $err['error_subcode'] ?? null;
            if($code==100 && $sub==33 && $row['attempt_count'] < self::COMMENT_READY_TIMEOUT_TRIES){
                $this->rescheduleComment($row,'retry Graph 100/33'); return;
            }
            $this->failComment($row,substr(json_encode($err,JSON_UNESCAPED_UNICODE),0,500)); return;
        }
        $this->db->where('id',$row['id'])->update('scheduled_comments',[
            'status'=>'posted','posted_time'=>gmdate('Y-m-d H:i:s'),
            'attempt_count'=>$row['attempt_count']+1,'last_error'=>NULL
        ]);
        $this->commentLog('SCHEDULED_OK',['id'=>$row['id'],'video_id'=>$row['video_id']]);
    }
    private function rescheduleComment($row,$reason)
    {
        $next = gmdate('Y-m-d H:i:s', time()+ self::COMMENT_RETRY_DELAY_SEC);
        $this->db->where('id',$row['id'])->update('scheduled_comments',[
            'status'=>'pending','scheduled_time'=>$next,
            'attempt_count'=>$row['attempt_count']+1,'last_error'=>$reason
        ]);
        $this->commentLog('RETRY_DELAYED',['id'=>$row['id'],'reason'=>$reason,'next'=>$next]);
    }
    private function failComment($row,$msg)
    {
        $attempt=$row['attempt_count']+1;
        $status=($attempt>=self::COMMENT_MAX_ATTEMPTS)?'failed':'pending';
        $this->db->where('id',$row['id'])->update('scheduled_comments',[
            'status'=>$status,'last_error'=>$msg,'attempt_count'=>$attempt
        ]);
        $this->commentLog('SCHEDULED_FAIL',['id'=>$row['id'],'msg'=>$msg,'attempt'=>$attempt,'final'=>($status==='failed')]);
    }

    /* ================= CURL Helpers ================= */
    private function curlJson($url,$data){
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode($data),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
        $res=curl_exec($ch); curl_close($ch); return $res;
    }
    private function curlBinary($url,$file,$headers){
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>file_get_contents($file),CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
        $res=curl_exec($ch); curl_close($ch); return $res;
    }
    private function curlForm($url,$data){
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>http_build_query($data),CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
        $res=curl_exec($ch); curl_close($ch); return $res;
    }

    /* =========================================================
       ========= دعم القصص (فيديو/صورة) – مضاف/معدل ==========
       عند نشر / نشر مجدول نقرأ توكن الصفحة من جدول المنصة بدلاً من facebook_pages
    */

   /*********** Helpers للّوج والطلبات ***********/
private function storyLog(string $label, array $ctx = []): void
{
    $line = $label.(empty($ctx)?'':' '.json_encode($ctx, JSON_UNESCAPED_UNICODE));
    $this->writeLog('stories_api.log', $line);
}

private function httpHeadPublic(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
        CURLOPT_HEADER         => true,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return [$code, $finalUrl, $err];
}

// POST x-www-form-urlencoded
private function curlPostForm(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 25,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body, $err];
}

// POST multipart (لازم للرفع بـ source=CURLFile)
private function curlPostMultipart(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 25,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body, $err];
}

/*********** نشر ستوري صوري (فوري) مع لوج تفصيلي + Fallback ***********/
public function upload_story_photo($user_id,$pagesTokens,$post,$files)
{
    if (!self::FEATURE_STORIES) return [['type'=>'error','msg'=>'القصص معطلة']];
    $fb_page_ids = $post['fb_page_ids'] ?? [];
    if (empty($fb_page_ids)) return [['type'=>'error','msg'=>'اختر صفحات']];
    if (empty($files['story_photo_file']['name'])) return [['type'=>'error','msg'=>'اختر صورة']];

    $fname = $files['story_photo_file']['name'];
    $tmp   = $files['story_photo_file']['tmp_name'];
    $err   = $files['story_photo_file']['error'];
    $size  = (int)($files['story_photo_file']['size'] ?? 0);
    $ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

    $this->storyLog('PHOTO_ENTRY', ['name'=>$fname,'tmp'=>$tmp,'err'=>$err,'size'=>$size,'ext'=>$ext,'pages'=>count($fb_page_ids)]);

    if ($err !== UPLOAD_ERR_OK || !is_file($tmp)) return [['type'=>'error','msg'=>'فشل رفع الصورة']];
    if (!in_array($ext, $this->story_image_exts)) return [['type'=>'error','msg'=>'امتداد غير مدعوم']];
    if ($size < 1024) return [['type'=>'error','msg'=>'ملف الصورة صغير جداً']];
    if ($size > 10*1024*1024) return [['type'=>'error','msg'=>'الحجم أكبر من 10MB']];

    // حفظ في مسار عام
    $pubDir = FCPATH.'uploads/scheduled/';
    if (!is_dir($pubDir)) @mkdir($pubDir, 0775, true);
    $safe   = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', $fname);
    $stored = 'story_photo_'.time().'_'.mt_rand(1000,9999).'_'.$safe;
    $abs    = $pubDir.$stored;

    $moved = @move_uploaded_file($tmp, $abs);
    $this->storyLog('PHOTO_MOVE', ['dest'=>$abs, 'ok'=>$moved, 'perm'=>@substr(sprintf('%o', fileperms($abs)), -4)]);
    if (!$moved) return [['type'=>'error','msg'=>'فشل حفظ الصورة على الخادم']];
    @chmod($abs, 0664);

    // URL عام
    $CI = &get_instance();
    $CI->load->helper('url');
    $publicUrl = base_url('uploads/scheduled/'.$stored);
    [$hcode, $finalUrl, $hErr] = $this->httpHeadPublic($publicUrl);
    $this->storyLog('PHOTO_PUBLIC_HEAD', ['url'=>$publicUrl,'http'=>$hcode,'final'=>$finalUrl,'err'=>$hErr]);

    $desc       = trim($post['description'] ?? '');
    $tz_offset  = (int)($post['tz_offset_minutes'] ?? 0);
    $tz_name    = (string)($post['tz_name'] ?? '');
    $version    = $this->graphVersion();

    $responses = [];
    foreach ($fb_page_ids as $pid) {
        $page  = $this->findPage($pagesTokens, $pid);
        $token = $page['page_access_token'] ?? '';
        if (!$token) {
            $responses[] = ['type'=>'error','msg'=>"توكن مفقود للصفحة $pid"];
            $this->storyLog('PHOTO_SKIP_NO_TOKEN', ['page'=>$pid]);
            continue;
        }

        // 1) جرّب رفع بالـ URL أولاً
        $url1 = "https://graph.facebook.com/{$version}/{$pid}/photos";
        $payloadUrl = [
            'published'    => 'false',
            'url'          => $publicUrl,
            'caption'      => $desc,
            'access_token' => $token
        ];
        [$code1, $body1, $err1] = $this->curlPostForm($url1, $payloadUrl);
        $j1 = @json_decode($body1, true);
        $this->storyLog('PHOTO_UPLOAD_URL', ['page'=>$pid,'http'=>$code1,'curl_err'=>$err1,'resp'=>$j1]);

        $photo_id = $j1['id'] ?? null;

        // 2) لو فشل رفع URL، اعمل Fallback رفع ملف مباشرة (source)
        if (!$photo_id) {
            $cfile = new CURLFile($abs, mime_content_type($abs) ?: 'image/jpeg', basename($abs));
            $payloadSrc = [
                'published'    => 'false',
                'source'       => $cfile,
                'caption'      => $desc,
                'access_token' => $token
            ];
            [$code1b, $body1b, $err1b] = $this->curlPostMultipart($url1, $payloadSrc);
            $j1b = @json_decode($body1b, true);
            $this->storyLog('PHOTO_UPLOAD_SOURCE', ['page'=>$pid,'http'=>$code1b,'curl_err'=>$err1b,'resp'=>$j1b]);
            $photo_id = $j1b['id'] ?? null;
        }

        if (!$photo_id) {
            $msg = 'فشل رفع الصورة (تحقّق من وصول فيسبوك للصورة عبر HTTPS أو جرّب صورة أصغر/امتداد مختلف).';
            if (!empty($j1['error']['message'])) $msg .= ' - '.$j1['error']['message'];
            $responses[] = ['type'=>'error','msg'=>"{$msg} (صفحة $pid)"];
            continue;
        }

        // 3) نشر كقصة
        $url2 = "https://graph.facebook.com/{$version}/{$pid}/photo_stories";
        [$code2, $body2, $err2] = $this->curlPostForm($url2, [
            'photo_id'     => $photo_id,
            'access_token' => $token
        ]);
        $j2 = @json_decode($body2, true);
        $this->storyLog('PHOTO_STORY_PUBLISH', ['page'=>$pid,'http'=>$code2,'curl_err'=>$err2,'resp'=>$j2]);

        if ($code2 < 200 || $code2 >= 300 || empty($j2['success'])) {
            $msg = 'فشل نشر القصة';
            if (!empty($j2['error']['message'])) $msg .= ' - '.$j2['error']['message'];
            $responses[] = ['type'=>'error','msg'=>"$msg (صفحة $pid)"];
            continue;
        }

        // 4) سجل النجاح
        $ins = [
            'user_id'                 => $user_id,
            'fb_page_id'              => $pid,
            'video_id'                => NULL,
            'file_name'               => $fname,
            'file_path'               => 'uploads/scheduled/'.$stored,
            'cover_path'              => NULL,
            'cover_source'            => NULL,
            'description'             => $desc,
            'scheduled_at'            => NULL,
            'original_local_time'     => NULL,
            'original_offset_minutes' => $tz_offset,
            'original_timezone'       => $tz_name,
            'status'                  => 'published',
            'created_at'              => gmdate('Y-m-d H:i:s')
        ];
        if ($this->columnExists('reels','media_type')) $ins['media_type']='story_photo';
        if ($this->columnExists('reels','post_id'))    $ins['post_id']=$j2['post_id'] ?? null;
        if ($this->columnExists('reels','expires_at')) $ins['expires_at']=gmdate('Y-m-d H:i:s', time()+self::STORY_EXPIRE_SECONDS);

        $this->db->insert('reels', $ins);
        $responses[] = ['type'=>'success','msg'=>"تم نشر ستوري الصورة على صفحة $pid"];
    }

    return $responses;
}
    /* رفع Story Video (نفس السابق) */
    public function upload_story_video($user_id,$pages,$post,$files)
    {
        if(!self::FEATURE_STORIES) return [['type'=>'error','msg'=>'القصص معطلة']];
        $fb_page_ids    = $post['fb_page_ids'] ?? [];
        $schedule_times = $post['schedule_times'] ?? [];
        $descriptions   = $post['descriptions'] ?? [];
        $video_files    = $files['video_files'] ?? null;
        $tz_offset      = (int)($post['tz_offset_minutes'] ?? 0);
        $tz_name        = $post['tz_name'] ?? '';
        $global_desc    = trim($post['description'] ?? '');

        if(!$video_files || empty($video_files['name'])) return [['type'=>'error','msg'=>'لا توجد ملفات فيديو']];
        if(empty($fb_page_ids)) return [['type'=>'error','msg'=>'اختر صفحات']];

        $responses=[];
        $cnt=count($video_files['name']);
        $immediateJobs=[];
        $version=$this->graphVersion();

        for($i=0;$i<$cnt;$i++){
            $fname=$video_files['name'][$i];
            $tmp=$video_files['tmp_name'][$i];
            $err=$video_files['error'][$i];
            if($err !== UPLOAD_ERR_OK || !is_file($tmp)){ $responses[]=['type'=>'error','msg'=>"فشل الملف $fname"]; continue; }
            $ext=strtolower(pathinfo($fname,PATHINFO_EXTENSION));
            if(!in_array($ext,$this->video_exts)){ $responses[]=['type'=>'error','msg'=>"امتداد غير مدعوم: $fname"]; continue; }
            $file_desc=trim($descriptions[$i] ?? '');
            $base=pathinfo($fname,PATHINFO_FILENAME);
            $caption = $file_desc ?: ($global_desc ?: $base);
            $local_sched=$schedule_times[$i] ?? '';
            $utc_sched=$this->localToUtc($local_sched,$tz_offset);
            $is_future = $utc_sched && $this->isFutureUtc($utc_sched,60);

            foreach($fb_page_ids as $pid){
                $page=$this->findPage($pages,$pid);
                if(!$page || empty($page['page_access_token'])){
                    $responses[]=['type'=>'error','msg'=>"توكن مفقود للصفحة $pid"]; continue;
                }
                if($is_future){
                    $dir=FCPATH.'uploads/scheduled/';
                    if(!is_dir($dir)) @mkdir($dir,0775,true);
                    $safe=preg_replace('/[^a-zA-Z0-9_\-\.]/','_',$fname);
                    $stored='story_sched_'.time().'_'.$i.'_'.mt_rand(1000,9999).'_'.$safe;
                    if(!move_uploaded_file($tmp,$dir.$stored)){
                        $responses[]=['type'=>'error','msg'=>"تعذر تخزين مجدول $fname"]; continue;
                    }
                    $orig_local = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$local_sched)
                        ? str_replace('T',' ',$local_sched).':00' : null;
                    $ins=[
                        'user_id'=>$user_id,'fb_page_id'=>$pid,'video_path'=>'uploads/scheduled/'.$stored,
                        'description'=>$caption,'scheduled_time'=>$utc_sched,'original_local_time'=>$orig_local,
                        'original_offset_minutes'=>$tz_offset,'original_timezone'=>$tz_name,'status'=>'pending',
                        'attempt_count'=>0,'processing'=>0,'created_at'=>gmdate('Y-m-d H:i:s')
                    ];
                    if($this->columnExists('scheduled_reels','media_type')) $ins['media_type']='story_video';
                    if($this->columnExists('scheduled_reels','expires_at'))
                        $ins['expires_at']=gmdate('Y-m-d H:i:s',strtotime($utc_sched)+self::STORY_EXPIRE_SECONDS);
                    $this->db->insert('scheduled_reels',$ins);
                    $responses[]=['type'=>'success','msg'=>"تمت جدولة Story Video على الصفحة $pid"];
                } else {
                    $immediateJobs[]=[
                        'fb_page_id'=>$pid,'page_access_token'=>$page['page_access_token'],
                        'tmp_name'=>$tmp,'file_size'=>filesize($tmp),'filename'=>$fname,
                        'caption'=>$caption,'tz_offset'=>$tz_offset,'tz_name'=>$tz_name
                    ];
                }
            }
        }

        if(!$immediateJobs) return $responses;

        /* رفع فوري (مشابه للنسخة السابقة) */
        $mh=curl_multi_init(); $handles=[];
        foreach($immediateJobs as $k=>$job){
            $url="https://graph.facebook.com/{$version}/{$job['fb_page_id']}/video_stories";
            $payload=['upload_phase'=>'start','access_token'=>$job['page_access_token']];
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
            $handles[$k]=['job'=>$job,'start_ch'=>$ch];
            curl_multi_add_handle($mh,$ch);
        }
        $this->runMulti($mh);
        foreach($handles as &$h){
            $res=json_decode(curl_multi_getcontent($h['start_ch']),true);
            $this->writeLog('stories_api.log','START page='.$h['job']['fb_page_id'].' res='.json_encode($res,JSON_UNESCAPED_UNICODE));
            curl_multi_remove_handle($mh,$h['start_ch']); curl_close($h['start_ch']);
            if(empty($res['video_id'])){
                $h['error']=true;
                $responses[]=['type'=>'error','msg'=>"فشل START (صفحة {$h['job']['fb_page_id']})"];
            } else {
                $h['video_id']=$res['video_id'];
            }
        } unset($h);

        foreach($handles as &$h){
            if(!empty($h['error'])) continue;
            $url="https://rupload.facebook.com/video-upload/{$version}/{$h['video_id']}";
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>[
                "Authorization: OAuth {$h['job']['page_access_token']}",
                "offset: 0","file_size: {$h['job']['file_size']}"
            ],CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>file_get_contents($h['job']['tmp_name']),CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
            $h['upload_ch']=$ch;
            curl_multi_add_handle($mh,$ch);
        } unset($h);
        $this->runMulti($mh);
        foreach($handles as &$h){
            if(!empty($h['error'])) continue;
            $res=json_decode(curl_multi_getcontent($h['upload_ch']),true);
            $this->writeLog('stories_api.log','UPLOAD page='.$h['job']['fb_page_id'].' res='.json_encode($res,JSON_UNESCAPED_UNICODE));
            curl_multi_remove_handle($mh,$h['upload_ch']); curl_close($h['upload_ch']);
            if(isset($res['error'])){
                $h['error']=true;
                $responses[]=['type'=>'error','msg'=>"فشل UPLOAD (صفحة {$h['job']['fb_page_id']})"];
            }
        } unset($h);

        foreach($handles as &$h){
            if(!empty($h['error'])) continue;
            $url="https://graph.facebook.com/{$version}/{$h['job']['fb_page_id']}/video_stories";
            $payload=['access_token'=>$h['job']['page_access_token'],'video_id'=>$h['video_id'],'upload_phase'=>'finish'];
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>http_build_query($payload),CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
            $h['finish_ch']=$ch;
            curl_multi_add_handle($mh,$ch);
        } unset($h);
        $this->runMulti($mh);
        foreach($handles as &$h){
            if(!empty($h['error'])) continue;
            $res=json_decode(curl_multi_getcontent($h['finish_ch']),true);
            $this->writeLog('stories_api.log','FINISH page='.$h['job']['fb_page_id'].' res='.json_encode($res,JSON_UNESCAPED_UNICODE));
            curl_multi_remove_handle($mh,$h['finish_ch']); curl_close($h['finish_ch']);
            if(isset($res['error'])){
                $responses[]=['type'=>'error','msg'=>"فشل FINISH (صفحة {$h['job']['fb_page_id']})"];
                continue;
            }
            $post_id=$res['post_id'] ?? null;
            $ins=[
                'user_id'=>$user_id,'fb_page_id'=>$h['job']['fb_page_id'],'video_id'=>$h['video_id'],
                'file_name'=>$h['job']['filename'],'file_path'=>NULL,'cover_path'=>NULL,'cover_source'=>NULL,
                'description'=>$h['job']['caption'],'scheduled_at'=>NULL,'original_local_time'=>NULL,
                'original_offset_minutes'=>$h['job']['tz_offset'],'original_timezone'=>$h['job']['tz_name'],
                'status'=>'published','created_at'=>gmdate('Y-m-d H:i:s')
            ];
            if($this->columnExists('reels','media_type')) $ins['media_type']='story_video';
            if($this->columnExists('reels','post_id')) $ins['post_id']=$post_id;
            if($this->columnExists('reels','expires_at')) $ins['expires_at']=gmdate('Y-m-d H:i:s',time()+self::STORY_EXPIRE_SECONDS);
            $this->db->insert('reels',$ins);
            $responses[]=['type'=>'success','msg'=>"تم نشر Story Video على الصفحة {$h['job']['fb_page_id']}"];
        }
        unset($h);
        curl_multi_close($mh);
        return $responses;
    }

    /* نشر Story Video مجدول */
    public function publish_scheduled_story_video($row)
    {
        if(!self::FEATURE_STORIES){ $this->failScheduled($row,$row['attempt_count']+1,'Stories disabled'); return; }

        // اقرأ بيانات الصفحة من جدول المنصة
        $page=$this->db->get_where('facebook_rx_fb_page_info',[
            'page_id'=>$row['fb_page_id'],
            'user_id'=>$row['user_id']
        ])->row_array();

        $attempt=$row['attempt_count']+1;
        $this->db->where('id',$row['id'])->update('scheduled_reels',[
            'attempt_count'=>$attempt,'last_attempt_at'=>gmdate('Y-m-d H:i:s')
        ]);
        $abs=FCPATH.$row['video_path'];
        if(!is_file($abs) || !$page || empty($page['page_access_token'])){
            $this->failScheduled($row,$attempt,'ملف/توكن مفقود'); return;
        }
        $version=$this->graphVersion();
        $start="https://graph.facebook.com/{$version}/{$row['fb_page_id']}/video_stories";
        $sRes=$this->curlJson($start,['upload_phase'=>'start','access_token'=>$page['page_access_token']]);
        $sJson=json_decode($sRes,true);
        $this->writeLog('stories_api.log','SCHED_START id='.$row['id'].' res='.json_encode($sJson,JSON_UNESCAPED_UNICODE));
        if(empty($sJson['video_id'])){ $this->failScheduled($row,$attempt,'فشل START'); return; }
        $video_id=$sJson['video_id'];

        $upl="https://rupload.facebook.com/video-upload/{$version}/{$video_id}";
        $uCh=curl_init($upl);
        curl_setopt_array($uCh,[CURLOPT_HTTPHEADER=>[
            "Authorization: OAuth {$page['page_access_token']}",
            "offset: 0","file_size: ".filesize($abs)
        ],CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>file_get_contents($abs),CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>false]);
        $uRes=curl_exec($uCh); curl_close($uCh);
        $uJson=json_decode($uRes,true);
        $this->writeLog('stories_api.log','SCHED_UPLOAD id='.$row['id'].' res='.json_encode($uJson,JSON_UNESCAPED_UNICODE));
        if(isset($uJson['error'])){ $this->failScheduled($row,$attempt,'فشل UPLOAD'); return; }

        $fin="https://graph.facebook.com/{$version}/{$row['fb_page_id']}/video_stories";
        $fRes=$this->curlForm($fin,['access_token'=>$page['page_access_token'],'video_id'=>$video_id,'upload_phase'=>'finish']);
        $fJson=json_decode($fRes,true);
        $this->writeLog('stories_api.log','SCHED_FINISH id='.$row['id'].' res='.json_encode($fJson,JSON_UNESCAPED_UNICODE));
        if(isset($fJson['error'])){ $this->failScheduled($row,$attempt,'فشل FINISH'); return; }
        $post_id=$fJson['post_id'] ?? null;

        $this->db->where('id',$row['id'])->update('scheduled_reels',[
            'status'=>'uploaded','fb_response'=>$video_id,'published_time'=>gmdate('Y-m-d H:i:s'),
            'processing'=>0,'last_error'=>NULL
        ]);
        $ins=[
            'user_id'=>$row['user_id'],'fb_page_id'=>$row['fb_page_id'],'video_id'=>$video_id,
            'file_name'=>basename($row['video_path']),'file_path'=>$row['video_path'],
            'cover_path'=>$row['cover_path'] ?? null,'cover_source'=>$row['cover_source'] ?? null,
            'description'=>$row['description'],'scheduled_at'=>$row['scheduled_time'],
            'original_local_time'=>$row['original_local_time'],'original_offset_minutes'=>$row['original_offset_minutes'],
            'original_timezone'=>$row['original_timezone'],'status'=>'published','created_at'=>gmdate('Y-m-d H:i:s')
        ];
        if($this->columnExists('reels','media_type')) $ins['media_type']='story_video';
        if($this->columnExists('reels','post_id')) $ins['post_id']=$post_id;
        if($this->columnExists('reels','expires_at')) $ins['expires_at']=gmdate('Y-m-d H:i:s',time()+self::STORY_EXPIRE_SECONDS);
        $this->db->insert('reels',$ins);
        $this->logSched($row,$attempt,'success','Story video published');
    }

    /* نشر Story Photo مجدول */
   public function publish_scheduled_story_photo($row)
{
    if (!self::FEATURE_STORIES) { $this->failScheduled($row, $row['attempt_count']+1, 'Stories disabled'); return; }

    $page = $this->db->get_where('facebook_rx_fb_page_info',[
        'page_id'=>$row['fb_page_id'],
        'user_id'=>$row['user_id']
    ])->row_array();

    $attempt = $row['attempt_count']+1;
    $this->db->where('id',$row['id'])->update('scheduled_reels',[
        'attempt_count'=>$attempt,'last_attempt_at'=>gmdate('Y-m-d H:i:s')
    ]);

    $absPath = FCPATH . ltrim((string)$row['video_path'],'/');
    if (!is_file($absPath) || !$page || empty($page['page_access_token'])) {
        $this->failScheduled($row, $attempt, 'ملف/توكن مفقود'); return;
    }

    $CI=&get_instance(); $CI->load->helper('url');
    $publicUrl = base_url(ltrim((string)$row['video_path'],'/'));
    [$hcode, $finalUrl, $hErr] = $this->httpHeadPublic($publicUrl);
    $this->storyLog('PHOTO_SCHED_HEAD', ['id'=>$row['id'],'url'=>$publicUrl,'http'=>$hcode,'final'=>$finalUrl,'err'=>$hErr]);

    $version = $this->graphVersion();
    $desc    = (string)($row['description'] ?? '');
    $pid     = (string)$row['fb_page_id'];
    $token   = $page['page_access_token'];

    // 1) جرّب بالـ URL أولاً
    $url1 = "https://graph.facebook.com/{$version}/{$pid}/photos";
    $payloadUrl = [
        'published'    => 'false',
        'url'          => $publicUrl,
        'caption'      => $desc,
        'access_token' => $token
    ];
    [$code1, $body1, $err1] = $this->curlPostForm($url1, $payloadUrl);
    $j1 = @json_decode($body1,true);
    $this->storyLog('PHOTO_SCHED_UPLOAD_URL', ['id'=>$row['id'],'http'=>$code1,'curl_err'=>$err1,'resp'=>$j1]);
    $photo_id = $j1['id'] ?? null;

    // 2) لو فشل، جرّب رفع الملف مباشرة
    if (!$photo_id) {
        $cfile = new CURLFile($absPath, mime_content_type($absPath) ?: 'image/jpeg', basename($absPath));
        $payloadSrc = [
            'published'    => 'false',
            'source'       => $cfile,
            'caption'      => $desc,
            'access_token' => $token
        ];
        [$code1b, $body1b, $err1b] = $this->curlPostMultipart($url1, $payloadSrc);
        $j1b = @json_decode($body1b,true);
        $this->storyLog('PHOTO_SCHED_UPLOAD_SOURCE', ['id'=>$row['id'],'http'=>$code1b,'curl_err'=>$err1b,'resp'=>$j1b]);
        $photo_id = $j1b['id'] ?? null;
    }

    if (!$photo_id) { $this->failScheduled($row, $attempt, 'فشل رفع الصورة'); return; }

    // 3) نشر story
    $url2 = "https://graph.facebook.com/{$version}/{$pid}/photo_stories";
    [$code2, $body2, $err2] = $this->curlPostForm($url2, [
        'photo_id'     => $photo_id,
        'access_token' => $token
    ]);
    $j2 = @json_decode($body2,true);
    $this->storyLog('PHOTO_SCHED_PUBLISH', ['id'=>$row['id'],'http'=>$code2,'curl_err'=>$err2,'resp'=>$j2]);

    if ($code2 < 200 || $code2 >= 300 || empty($j2['success'])) {
        $this->failScheduled($row, $attempt, !empty($j2['error']['message'])?$j2['error']['message']:'فشل نشر القصة');
        return;
    }

    // 4) تحديث الجداول
    $this->db->where('id',$row['id'])->update('scheduled_reels',[
        'status'=>'uploaded','fb_response'=>$photo_id,'published_time'=>gmdate('Y-m-d H:i:s'),
        'processing'=>0,'last_error'=>NULL
    ]);

    $ins=[
        'user_id'=>$row['user_id'],'fb_page_id'=>$row['fb_page_id'],'video_id'=>NULL,
        'file_name'=>basename((string)$row['video_path']),'file_path'=>$row['video_path'],
        'cover_path'=>NULL,'cover_source'=>NULL,
        'description'=>$desc,'scheduled_at'=>$row['scheduled_time'],
        'original_local_time'=>$row['original_local_time'],'original_offset_minutes'=>$row['original_offset_minutes'],
        'original_timezone'=>$row['original_timezone'],'status'=>'published',
        'created_at'=>gmdate('Y-m-d H:i:s')
    ];
    if ($this->columnExists('reels','media_type')) $ins['media_type']='story_photo';
    if ($this->columnExists('reels','post_id'))    $ins['post_id']=$j2['post_id'] ?? null;
    if ($this->columnExists('reels','expires_at')) $ins['expires_at']=gmdate('Y-m-d H:i:s', time()+self::STORY_EXPIRE_SECONDS);

    $this->db->insert('reels',$ins);
    $this->logSched($row,$attempt,'success','Story photo published');
   }
}
