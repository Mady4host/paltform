<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Instagram Controller (UTC Scheduling)
 * - الأوقات مخزنة UTC
 * - حفظ original_local_time / original_offset_minutes / original_timezone
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
        $ts=strtotime($utc);
        return $ts!==false && $ts > time()+$min;
    }

    private function syncInstagramAccounts($user_id){
        $sql="INSERT INTO instagram_accounts
                (user_id, ig_user_id, ig_username, ig_profile_picture, page_id, page_name, access_token, ig_linked, created_at, updated_at)
              SELECT fp.user_id, fp.ig_user_id, fp.ig_username, fp.ig_profile_picture,
                     fp.fb_page_id, fp.page_name, fp.page_access_token, fp.ig_linked, NOW(), NOW()
              FROM facebook_pages fp
              WHERE fp.user_id=?
                AND fp.ig_linked=1
                AND fp.ig_user_id IS NOT NULL
              ON DUPLICATE KEY UPDATE
                ig_username=VALUES(ig_username),
                ig_profile_picture=COALESCE(NULLIF(VALUES(ig_profile_picture),''), instagram_accounts.ig_profile_picture),
                page_id=VALUES(page_id),
                page_name=VALUES(page_name),
                access_token=VALUES(access_token),
                ig_linked=VALUES(ig_linked),
                updated_at=NOW()";
        $this->db->query($sql,[$user_id]);
    }

    public function upload(){
        $user_id=$this->requireLogin();
        $cnt=$this->db->from('instagram_accounts')
                      ->where('user_id',$user_id)
                      ->where('ig_linked',1)
                      ->where('status','active')->count_all_results();
        if($cnt==0){ $this->syncInstagramAccounts($user_id); }
        $accounts=$this->db->select('id,user_id,ig_user_id,ig_username,ig_profile_picture,page_name,access_token')
                           ->from('instagram_accounts')
                           ->where('user_id',$user_id)
                           ->where('ig_linked',1)
                           ->where('ig_user_id IS NOT NULL',null,false)
                           ->where('status','active')
                           ->order_by('page_name','ASC')->get()->result_array();
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

        // ========== FACEBOOK-COMPAT SCHEDULER (مطابق لمنطق فيسبوك) ==========
        if (isset($_POST['schedule_times_fb']) && is_array($_POST['schedule_times_fb'])) {
            $tzOffsetMinFb = (int)($this->input->post('tz_offset_minutes') ?? 0);
            $schedLocalArr = $_POST['schedule_times_fb'];             // YYYY-MM-DDTHH:MM لكل ملف
            $descsFb       = $_POST['descriptions_fb'] ?? [];         // أوصاف لكل ملف (اختياري)
            $commentsFb    = $_POST['comments_fb'] ?? [];             // comments_fb[INDEX][] (اختياري)
            $global_desc   = trim((string)$this->input->post('description_fb')); // وصف عام (اختياري)

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
                if (!$isVideo) { continue; } // ريلز فقط كما في فيسبوك

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
                    $files[$di]['error'] = UPLOAD_ERR_NO_FILE; // منع إعادة المعالجة في اللوب الأصلي
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
                $account=$this->db->select('*')->from('instagram_accounts')
                                  ->where('user_id',$user_id)
                                  ->where('ig_user_id',$ig_uid)
                                  ->where('ig_linked',1)
                                  ->where('status','active')->get()->row_array();
                if(!$account){
                    $globalResults[]=['file'=>$fileArr['name'],'ig_user_id'=>$ig_uid,'status'=>'error','error'=>'account_not_found'];
                    continue;
                }
                $token=$account['access_token'] ?? $this->session->userdata('fb_access_token');
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
                    $res=$this->instagrampublisher->publishStory($ig_uid,$fullPath,$fileType,$token);
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
        $active=$this->db->from('instagram_accounts')
                         ->where('user_id',$user_id)
                         ->where('ig_linked',1)
                         ->where('status','active')->count_all_results();
        if($active==0){ $this->syncInstagramAccounts($user_id); }

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

        $accounts=$this->db->select('ig_user_id,ig_username,page_name,ig_profile_picture')
                           ->from('instagram_accounts')
                           ->where('user_id',$user_id)
                           ->where('ig_linked',1)
                           ->where('ig_user_id IS NOT NULL',null,false)
                           ->where('status','active')
                           ->order_by('page_name','ASC')->get()->result_array();

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
            $account=$this->db->select('*')->from('instagram_accounts')
                              ->where('user_id',$row['user_id'])
                              ->where('ig_user_id',$row['ig_user_id'])
                              ->where('ig_linked',1)
                              ->where('status','active')->get()->row_array();
            if(!$account){
                $this->Instagram_reels_model->mark_failed($id,'account_not_found');
                $processed[]=['id'=>$id,'status'=>'failed','reason'=>'account_not_found'];
                $this->logCron("ID $id account_not_found");
                continue;
            }

            $this->Instagram_reels_model->mark_publishing($id);

            $token=$account['access_token'];
            if(!$token){
                if(($row['attempt_count']+1)<self::MAX_CRON_ATTEMPTS){
                    $this->Instagram_reels_model->reschedule_for_retry($id,self::RETRY_DELAY_MINUTES);
                    // FIXED: تم تصحيح المسافة هنا
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

            if($row['media_kind']==='ig_reel'){
                $res=$this->instagrampublisher->publishReel($row['ig_user_id'],$finalPath,$row['description'],$token);
            } elseif(in_array($row['media_kind'],['ig_story_image','ig_story_video'],true)){
                $type = $row['media_kind']==='ig_story_image'?'image':'video';
                $res=$this->instagrampublisher->publishStory($row['ig_user_id'],$finalPath,$type,$token);
            } else {
                $res=['ok'=>false,'error'=>'unsupported_kind'];
            }

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

            $this->Instagram_reels_model->mark_published($id,$res['media_id'],$res['creation_id'] ?? null);
            $this->logCron("ID $id published media=".$res['media_id']);

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
            $postFields=http_build_query([
                'message'=>$msg,
                'access_token'=>$accessToken
            ]);
            $ch=curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postFields
            ]);
            $body=curl_exec($ch);
            $err=curl_error($ch);
            $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($err){
                $out[]=['i'=>$i+1,'status'=>'error','error'=>$err];
            } else {
                $data=json_decode($body,true);
                if(isset($data['id'])){
                    $out[]=['i'=>$i+1,'status'=>'ok','comment_id'=>$data['id']];
                } else {
                    $out[]=['i'=>$i+1,'status'=>'error','http_code'=>$code,'raw'=>$data];
                }
            }
            usleep(250000);
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