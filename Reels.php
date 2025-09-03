<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reels Controller
 * - إدارة رفع الريلز والستوري + الجدولة
 * - إدارة صفحات فيسبوك (عرض / مفضلة / مزامنة / حذف ربط)
 * - يعتمد على:
 *   Reel_model
 *   Facebook_pages_model  (موديل الصفحات الجديد)
 */
class Reels extends CI_Controller
{
    /* إعدادات عامة */
    const CRON_TOKEN             = 'RlsCron_2025_StrongX';
    const SCHEDULE_DIR           = 'uploads/scheduled/';
    const MIN_FUTURE_SECONDS     = 30;
    const ALLOWED_EXTENSIONS     = ['mp4','mov','mkv','m4v'];
    const MIN_FILE_SIZE_BYTES    = 50*1024;
    const MAX_SCHEDULE_FILES     = 40;
    const DEBUG_LOG              = true;
    const FEATURE_STORIES        = true;
    const IMAGE_FALLBACK_QUERY_TOKEN = true; // إضافة التوكن للصورة لو متاح

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Reel_model');
        $this->load->model('Facebook_pages_model','pagesModel');
        $this->load->library(['session']);
        $this->load->helper(['url','form','security']);
        $this->load->database();
    }

    /* ======================== Utilities ======================== */

    private function require_login()
    {
        if(!$this->session->userdata('user_id')){
            $redir = rawurlencode(current_url());
            redirect('home/login?redirect='.$redir);
            exit;
        }
    }

    private function dbg($label,$data)
    {
        if(!self::DEBUG_LOG) return;
        $dir=FCPATH.'application/logs/';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        @file_put_contents(
            $dir.'reels_debug.log',
            '['.gmdate('Y-m-d H:i:s')."] $label: ".(is_scalar($data)?$data:json_encode($data,JSON_UNESCAPED_UNICODE)).PHP_EOL,
            FILE_APPEND
        );
    }

    private function send_json($arr, int $code=200)
    {
        $this->output->set_status_header($code);
        $this->output->set_content_type('application/json','utf-8');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        return;
    }

    private function localToUtc(?string $local,int $offset)
    {
        if(!$local)return null;
        if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$local)) return null;
        $ts=strtotime($local);
        if($ts===false)return null;
        return gmdate('Y-m-d H:i:s',$ts + ($offset*60));
    }

    private function isFutureUtc(?string $utc,$min=self::MIN_FUTURE_SECONDS)
    {
        if(!$utc)return false;
        $ts=strtotime($utc);
        return $ts!==false && $ts>time()+$min;
    }

    private function subsetFiles($orig,$field,$indices)
    {
        if(!isset($orig[$field])) return $orig;
        $o=$orig;
        foreach(['name','type','tmp_name','error','size'] as $k){
            if(!isset($orig[$field][$k])) continue;
            $o[$field][$k]=[];
            foreach($indices as $i){ $o[$field][$k][]=$orig[$field][$k][$i]; }
        }
        return $o;
    }

    /* ======================== Upload Form ======================== */

    public function upload()
    {
        $this->require_login();
        $uid=(int)$this->session->userdata('user_id');

        $pages=$this->pagesModel->get_pages_by_user($uid);
        if(!$pages){
            $this->session->set_flashdata('msg','لا يوجد صفحات، قم بالربط أولاً.');
            redirect('reels/pages'); return;
        }

        foreach($pages as $k=>$p){
            if(empty($pages[$k]['_img'])){
                $fallback = 'https://graph.facebook.com/'.$p['fb_page_id'].'/picture?type=normal';
                if(self::IMAGE_FALLBACK_QUERY_TOKEN && !empty($p['page_access_token'])){
                    $fallback .= '&access_token='.urlencode($p['page_access_token']);
                }
                $pages[$k]['_img'] = !empty($p['page_picture']) ? $p['page_picture'] : $fallback;
            }
        }

        // NEW: التقاط الصفحات المراد تمييزها مسبقاً من ?page=1,2,3
        $preParam = trim((string)$this->input->get('page', true));
        $preselected_pages = [];
        if($preParam !== ''){
            foreach(explode(',', $preParam) as $pid){
                $pid = preg_replace('/\D+/','',$pid);
                if($pid!=='') $preselected_pages[]=$pid;
            }
            $preselected_pages = array_values(array_unique($preselected_pages));
        }
        $valid_fb_ids = array_column($pages,'fb_page_id');
        $preselected_pages = array_values(array_intersect($preselected_pages,$valid_fb_ids));

        $data['pages']=$pages;
        $data['preselected_pages']=$preselected_pages;
        $data['trending_hashtags']=$this->Reel_model->get_trending_hashtags();
        $this->load->view('reels_upload',$data);
    }

    /* ======================== Processing Upload ======================== */

    public function process_upload()
    {
        $this->require_login();
        $uid=(int)$this->session->userdata('user_id');

        $pages=$this->pagesModel->get_pages_by_user($uid);
        if(!$pages){
            $this->session->set_flashdata('msg','لا يوجد صفحات.');
            redirect('reels/upload'); return;
        }

        $media_type = $this->input->post('media_type') ?: 'reel';
        $fb_page_ids=$this->input->post('fb_page_ids');
        if(empty($fb_page_ids)){
            $this->session->set_flashdata('msg','اختر صفحة واحدة على الأقل.');
            redirect('reels/upload'); return;
        }

        /* Story Photo */
        if($media_type==='story_photo' && self::FEATURE_STORIES){
            if(empty($_FILES['story_photo_file']['name'])){
                $this->session->set_flashdata('msg','اختر صورة للستوري.');
                redirect('reels/upload'); return;
            }
            $responses = $this->Reel_model->upload_story_photo($uid,$pages,$_POST,$_FILES);
            $success=[];$error=[];
            foreach($responses as $r){ if($r['type']==='success') $success[]=$r['msg']; else $error[]=$r['msg']; }
            if($success) $this->session->set_flashdata('msg_success',implode('<br>',$success));
            if($error)   $this->session->set_flashdata('msg',implode('<br>',$error));
            if($this->input->is_ajax_request()){
                $this->send_json([
                    'success'=>true,
                    'messages'=>array_merge(
                        array_map(fn($s)=>['type'=>'success','msg'=>$s],$success),
                        array_map(fn($e)=>['type'=>'error','msg'=>$e],$error)
                    )
                ]);
                return;
            }
            redirect('reels/list'); return;
        }

        /* Story Video */
        if($media_type==='story_video' && self::FEATURE_STORIES){
            if(empty($_FILES['video_files']['name'][0])){
                $this->session->set_flashdata('msg','اختر ملفات فيديو (ستوري).');
                redirect('reels/upload'); return;
            }
            $responses = $this->Reel_model->upload_story_video($uid,$pages,$_POST,$_FILES);
            $success=[];$error=[];
            foreach($responses as $r){ if($r['type']==='success') $success[]=$r['msg']; else $error[]=$r['msg']; }
            if($success) $this->session->set_flashdata('msg_success',implode('<br>',$success));
            if($error)   $this->session->set_flashdata('msg',implode('<br>',$error));
            if($this->input->is_ajax_request()){
                $this->send_json([
                    'success'=>true,
                    'messages'=>array_merge(
                        array_map(fn($s)=>['type'=>'success','msg'=>$s],$success),
                        array_map(fn($e)=>['type'=>'error','msg'=>$e],$error)
                    )
                ]);
                return;
            }
            redirect('reels/list'); return;
        }

        /* ريلز */
        if(empty($_FILES['video_files']['name'][0])){
            $this->session->set_flashdata('msg','اختر ملفات فيديو.');
            redirect('reels/upload'); return;
        }

        $global_desc = trim(xss_clean((string)$this->input->post('description')));
        $descs       = $this->input->post('descriptions') ?: [];
        $sched_local = $this->input->post('schedule_times') ?: [];
        $comments    = $this->input->post('comments') ?: [];
        $tz_offset   = (int)($this->input->post('tz_offset_minutes') ?? 0);
        $tz_name     = trim((string)$this->input->post('tz_name'));

        $names=$_FILES['video_files']['name'];
        $tmps =$_FILES['video_files']['tmp_name'];
        $sizes=$_FILES['video_files']['size'];
        $errs =$_FILES['video_files']['error'];
        $count=count($names);

        if($count>self::MAX_SCHEDULE_FILES){
            $this->session->set_flashdata('msg','عدد الملفات كبير.');
            redirect('reels/upload'); return;
        }

        $scheduled=[]; $immediate=[];
        for($i=0;$i<$count;$i++){
            $local=$sched_local[$i] ?? '';
            if($local===''){ $immediate[]=$i; continue; }
            $utc=$this->localToUtc($local,$tz_offset);
            if($this->isFutureUtc($utc)) $scheduled[]=$i; else $immediate[]=$i;
        }
        $this->dbg('classification',['scheduled'=>$scheduled,'immediate'=>$immediate]);

        $scheduled_msg='';
        if($scheduled){
            $scheduled_msg=$this->scheduleBatch(
                $uid,$fb_page_ids,$scheduled,
                $names,$tmps,$sizes,$errs,
                $descs,$global_desc,$sched_local,
                $tz_offset,$tz_name,$comments
            );
        }

        $immediate_msgs=[];
        if($immediate){
            $subsetFiles=$this->subsetFiles($_FILES,'video_files',$immediate);
            $subsetPost=[
                'fb_page_ids'=>$fb_page_ids,'descriptions'=>[],
                'schedule_times'=>[],'comments'=>[],
                'tz_offset_minutes'=>$tz_offset,'tz_name'=>$tz_name,
                'description'=>$global_desc,'selected_hashtags'=>$this->input->post('selected_hashtags')
            ];
            $newIdx=0;
            foreach($immediate as $orig){
                $subsetPost['descriptions'][$newIdx]=$descs[$orig] ?? '';
                $subsetPost['schedule_times'][$newIdx]=$sched_local[$orig] ?? '';
                $subsetPost['comments'][$newIdx]=$comments[$orig] ?? [];
                $newIdx++;
            }
            $immediate_msgs=$this->Reel_model->upload_reels($uid,$pages,$subsetPost,$subsetFiles);
        }

        $success=[]; $error=[];
        if($scheduled_msg) $success[]=$scheduled_msg;
        foreach($immediate_msgs as $m){
            if($m['type']==='success') $success[]=$m['msg']; else $error[]=$m['msg'];
        }

        if($success) $this->session->set_flashdata('msg_success',implode('<br>',$success));
        if($error)   $this->session->set_flashdata('msg',implode('<br>',$error));

        if($this->input->is_ajax_request()){
            $this->send_json([
                'success'=>true,
                'messages'=>array_merge(
                    array_map(fn($s)=>['type'=>'success','msg'=>$s],$success),
                    array_map(fn($e)=>['type'=>'error','msg'=>$e],$error)
                )
            ]);
            return;
        }
        redirect('reels/list');
    }

    private function scheduleBatch(
        int $uid,array $page_ids,array $file_indices,
        array $names,array $tmps,array $sizes,array $errs,
        array $descs,string $global_desc,array $sched_local,
        int $tz_offset,string $tz_name,array $comments_raw
    ): string
    {
        $absDir=FCPATH.self::SCHEDULE_DIR;
        if(!is_dir($absDir)) mkdir($absDir,0775,true);

        $selected_tags = trim((string)$this->input->post('selected_hashtags'));
        $rows=[]; $now=gmdate('Y-m-d H:i:s'); $saved=0; $mapIdxToPages=[];
        foreach($file_indices as $i){
            if(!isset($names[$i])||$names[$i]==='') continue;
            if(!empty($errs[$i]) && $errs[$i] != UPLOAD_ERR_OK) continue;
            $tmp=$tmps[$i];
            if(!is_file($tmp)) continue;
            $ext=strtolower(pathinfo($names[$i],PATHINFO_EXTENSION));
            $size=(int)$sizes[$i];
            if(!in_array($ext,self::ALLOWED_EXTENSIONS) || $size < self::MIN_FILE_SIZE_BYTES) continue;

            $local=$sched_local[$i] ?? '';
            $utc  =$this->localToUtc($local,$tz_offset);
            if(!$this->isFutureUtc($utc)) continue;

            $file_desc=trim($descs[$i] ?? '');
            $base=pathinfo($names[$i],PATHINFO_FILENAME);
            if($file_desc!=='') $desc=$file_desc;
            elseif($global_desc!=='') $desc=$global_desc;
            else $desc=$base;
            if($selected_tags!==''){
                foreach(preg_split('/\s+/u',$selected_tags) as $tg){
                    if($tg==='') continue;
                    if(stripos($desc,$tg)===false) $desc.=' '.$tg;
                }
            }

            $safe=preg_replace('/[^a-zA-Z0-9_\-\.]/','_',$names[$i]);
            $fname='reel_'.time().'_'.$i.'_'.mt_rand(1000,9999).'_'.$safe;
            if(!move_uploaded_file($tmp,$absDir.$fname)) continue;

            $saved++;
            $original_local_time = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$local)
                ? str_replace('T',' ',$local).':00'
                : null;

            foreach($page_ids as $pid){
                $rows[]=[
                    'user_id'=>$uid,'fb_page_id'=>$pid,'video_path'=>self::SCHEDULE_DIR.$fname,
                    'description'=>$desc,'scheduled_time'=>$utc,'original_local_time'=>$original_local_time,
                    'original_offset_minutes'=>$tz_offset,'original_timezone'=>$tz_name,
                    'status'=>'pending','attempt_count'=>0,'processing'=>0,'created_at'=>$now
                ];
            }
            $mapIdxToPages[$i]=$page_ids;
        }

        if(!$rows) return 'لم يتم جدولة أي ملف.';

        $this->db->insert_batch('scheduled_reels',$rows);

        // تحديث إحصائيات الصفحات
        foreach($page_ids as $pid){
            $this->db->set('last_scheduled_at', $now, false)
                     ->set('scheduled_count','scheduled_count+'.$saved,false)
                     ->where('user_id',$uid)->where('fb_page_id',$pid)
                     ->update('facebook_pages');
        }

        if($comments_raw && $this->db->table_exists('scheduled_comments')){
            $added=count($rows);
            $rowsInserted=$this->db->order_by('id','DESC')->limit($added)->get('scheduled_reels')->result_array();
            usort($rowsInserted, fn($a,$b)=>$a['id'] <=> $b['id']);
            $cursor=0; $fileMap=[];
            foreach($file_indices as $ix){
                foreach($mapIdxToPages[$ix] as $pid){
                    if(!isset($rowsInserted[$cursor])) break;
                    $fileMap[$ix][$pid]=$rowsInserted[$cursor]['id'];
                    $cursor++;
                }
            }
            $nowUTC=gmdate('Y-m-d H:i:s');
            $insertC=[];
            foreach($comments_raw as $fileIndex=>$cRows){
                if(!isset($fileMap[$fileIndex])||!is_array($cRows)) continue;
                foreach($cRows as $cRow){
                    $text=trim($cRow['text'] ?? '');
                    $local=trim($cRow['schedule'] ?? '');
                    if($text==='') continue;
                    $schedUTC = $local ? $this->localToUtc($local,$tz_offset) : $nowUTC;
                    foreach($fileMap[$fileIndex] as $pid=>$schedReelId){
                        $insertC[]=[
                            'scheduled_reel_id'=>$schedReelId,'user_id'=>$uid,'fb_page_id'=>$pid,
                            'video_id'=>NULL,'comment_text'=>$text,'scheduled_time'=>$schedUTC,
                            'status'=>'pending','attempt_count'=>0,'last_error'=>NULL,'created_at'=>$nowUTC
                        ];
                    }
                }
            }
            if($insertC) $this->db->insert_batch('scheduled_comments',$insertC);
        }
        return 'تمت جدولة '.$saved.' ملف/ملفات.';
    }

    /* ======================== Listing ======================== */

    public function list()
    {
        $this->require_login();
        $uid=(int)$this->session->userdata('user_id');
        $reels    = $this->Reel_model->get_user_reels($uid);
        $scheduled= $this->db->where('user_id',$uid)->order_by('scheduled_time','DESC')->get('scheduled_reels')->result_array();
        $pages    = $this->pagesModel->get_pages_by_user($uid);

        $pageMap=[];
        foreach($pages as $p){
            $fallback = 'https://graph.facebook.com/'.$p['fb_page_id'].'/picture?type=normal';
            if(self::IMAGE_FALLBACK_QUERY_TOKEN && !empty($p['page_access_token'])){
                $fallback .= '&access_token='.urlencode($p['page_access_token']);
            }
            $img = !empty($p['page_picture']) ? $p['page_picture'] : $fallback;
            $pageMap[$p['fb_page_id']]=[
                'name'=>$p['page_name'] ?? $p['fb_page_id'],
                'pic'=>$img,
                'link'=>'https://facebook.com/'.$p['fb_page_id']
            ];
        }
        $data['reels']=$reels;
        $data['scheduled_reels']=$scheduled;
        $data['pages_map']=$pageMap;
        $this->load->view('reels_list',$data);
    }

    /* ======================== Edit Scheduled ======================== */

    public function edit_scheduled($id)
    {
        $this->require_login();
        $uid=(int)$this->session->userdata('user_id');
        $row=$this->db->where('id',(int)$id)->where('user_id',$uid)->get('scheduled_reels')->row_array();
        if(!$row){ $this->session->set_flashdata('msg','غير موجود.'); redirect('reels/list'); return; }
        if($row['status']!=='pending'){ $this->session->set_flashdata('msg','لا يمكن تعديل هذه الحالة.'); redirect('reels/list'); return; }
        $data['scheduled']=$row;
        $this->load->view('reels/edit_scheduled_reel',$data);
    }

    public function update_scheduled()
    {
        $this->require_login();
        $uid=(int)$this->session->userdata('user_id');
        $id=(int)$this->input->post('id');
        $row=$this->db->where('id',$id)->where('user_id',$uid)->get('scheduled_reels')->row_array();
        if(!$row){ $this->session->set_flashdata('msg','غير موجود.'); redirect('reels/list'); return; }
        if($row['status']!=='pending'){ $this->session->set_flashdata('msg','لا يمكن تعديل.'); redirect('reels/list'); return; }

        $desc=trim(xss_clean($this->input->post('description')));
        $local=trim((string)$this->input->post('scheduled_local'));
        $tz_offset=(int)($this->input->post('tz_offset_minutes') ?? 0);
        $tz_name=trim((string)$this->input->post('tz_name'));
        $update=['description'=>$desc];

        if($local){
            $utc=$this->localToUtc($local,$tz_offset);
            if($this->isFutureUtc($utc)){
                $update['scheduled_time']=$utc;
                if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$local)){
                    $update['original_local_time']=str_replace('T',' ',$local).':00';
                    $update['original_offset_minutes']=$tz_offset;
                    $update['original_timezone']=$tz_name;
                }
            } else {
                $this->session->set_flashdata('msg','وقت غير مستقبلي كافٍ.');
                redirect('reels/edit_scheduled/'.$id); return;
            }
        }

        if(!empty($_FILES['reel_video']['name'])){
            $tmp=$_FILES['reel_video']['tmp_name'];
            $err=$_FILES['reel_video']['error'];
            $size=(int)$_FILES['reel_video']['size'];
            $ext=strtolower(pathinfo($_FILES['reel_video']['name'],PATHINFO_EXTENSION));
            if($err===UPLOAD_ERR_OK && is_file($tmp) && in_array($ext,self::ALLOWED_EXTENSIONS) && $size>=self::MIN_FILE_SIZE_BYTES){
                $dir = FCPATH . self::SCHEDULE_DIR;
                if(!is_dir($dir)) mkdir($dir,0775,true);
                $safe=preg_replace('/[^a-zA-Z0-9_\-\.]/','_',$_FILES['reel_video']['name']);
                $fname='reel_edit_'.time().'_'.$id.'_'.mt_rand(1000,9999).'_'.$safe;
                if(move_uploaded_file($tmp,$dir.$fname)){
                    $update['video_path']=self::SCHEDULE_DIR.$fname;
                } else {
                    $this->session->set_flashdata('msg','فشل نقل الفيديو.'); redirect('reels/edit_scheduled/'.$id); return;
                }
            } else {
                $this->session->set_flashdata('msg','فيديو غير صالح.'); redirect('reels/edit_scheduled/'.$id); return;
            }
        }

        $this->db->where('id',$id)->update('scheduled_reels',$update);
        $this->session->set_flashdata('msg_success','تم التحديث.');
        redirect('reels/list');
    }

    public function delete_scheduled($id)
    {
        $this->require_login();
        $uid=(int)$this->session->userdata('user_id');
        $row=$this->db->where('id',(int)$id)->where('user_id',$uid)->get('scheduled_reels')->row_array();
        if(!$row){ $this->session->set_flashdata('msg','غير موجود.'); redirect('reels/list'); return; }
        if($row['status']!=='pending'){ $this->session->set_flashdata('msg','لا يمكن الحذف.'); redirect('reels/list'); return; }
        $this->db->where('id',$row['id'])->delete('scheduled_reels');
        $this->session->set_flashdata('msg_success','تم الحذف.');
        redirect('reels/list');
    }

    public function scheduled_logs($id)
    {
        $this->require_login();
        $uid=(int)$this->session->userdata('user_id');
        $scheduled=$this->db->where('id',(int)$id)->where('user_id',$uid)->get('scheduled_reels')->row_array();
        if(!$scheduled){ $this->session->set_flashdata('msg','غير موجود.'); redirect('reels/list'); return; }
        $logs=$this->Reel_model->get_scheduled_logs($uid,(int)$id);
        $data['scheduled']=$scheduled;
        $data['logs']=$logs;
        $this->load->view('reels/scheduled_logs',$data);
    }

    /* ======================== Cron Jobs ======================== */

    public function cron_publish($token=null)
    {
        if(!$this->input->is_cli_request()){
            if($token!==self::CRON_TOKEN){ show_error('Unauthorized',403); return; }
        }
        $lock=sys_get_temp_dir().'/reels_cron.lock';
        $fh=fopen($lock,'c+');
        if(!$fh || !flock($fh,LOCK_EX|LOCK_NB)){ echo "Another instance running\n"; return; }
        $due=$this->Reel_model->get_due_scheduled_reels(40);
        foreach($due as $r){
            if(self::FEATURE_STORIES && isset($r['media_type'])){
                if($r['media_type']==='story_video' && method_exists($this->Reel_model,'publish_scheduled_story_video')){
                    $this->Reel_model->publish_scheduled_story_video($r); continue;
                }
                if($r['media_type']==='story_photo' && method_exists($this->Reel_model,'publish_scheduled_story_photo')){
                    $this->Reel_model->publish_scheduled_story_photo($r); continue;
                }
            }
            $this->Reel_model->process_scheduled_reel($r);
        }
        echo "Processed ".count($due)." scheduled items.\n";
        flock($fh,LOCK_UN); fclose($fh);
    }

    public function cron_comments($token=null)
    {
        if(!$this->input->is_cli_request()){
            if($token!==self::CRON_TOKEN){ show_error('Unauthorized',403); return; }
        }
        $lock=sys_get_temp_dir().'/reels_comments.lock';
        $fh=fopen($lock,'c+');
        if(!$fh || !flock($fh,LOCK_EX|LOCK_NB)){ echo "Another instance running\n"; return; }
        $rows=$this->Reel_model->get_due_scheduled_comments(80);
        foreach($rows as $r){ $this->Reel_model->process_scheduled_comment($r); }
        echo "Processed ".count($rows)." scheduled comments.\n";
        flock($fh,LOCK_UN); fclose($fh);
    }

    /* ======================== Pages View ======================== */

    public function pages()
    {
        $this->require_login();
        $uid=(int)$this->session->userdata('user_id');

        $pages=$this->pagesModel->get_pages_by_user($uid);
        foreach($pages as $k=>$p){
            $fallback = 'https://graph.facebook.com/'.$p['fb_page_id'].'/picture?type=normal';
            if(self::IMAGE_FALLBACK_QUERY_TOKEN && !empty($p['page_access_token'])){
                $fallback .= '&access_token='.urlencode($p['page_access_token']);
            }
            if(empty($pages[$k]['_img'])){
                $pages[$k]['_img']=!empty($p['page_picture'])?$p['page_picture']:$fallback;
            }
        }
        $this->load->view('reels_pages',['pages'=>$pages]);
    }

    /* ======================== AJAX Endpoints ======================== */

    public function ajax_toggle_favorite()
    {
        $this->require_login();
        $user_id=(int)$this->session->userdata('user_id');
        $page_id = $this->input->post('page_id',true);
        if(!$page_id){ return $this->send_json(['status'=>'error','msg'=>'missing_page'],400); }
        $new = $this->pagesModel->toggle_favorite($user_id,$page_id);
        if($new===false) return $this->send_json(['status'=>'error','msg'=>'not_found'],404);
        return $this->send_json(['status'=>'ok','favorite'=>$new]);
    }

    public function ajax_bulk_action()
    {
        $this->require_login();
        $user_id=(int)$this->session->userdata('user_id');
        $action=$this->input->post('action',true);
        $ids   =$this->input->post('ids');
        if(!$action || empty($ids) || !is_array($ids)){
            return $this->send_json(['status'=>'error','msg'=>'invalid'],400);
        }
        switch($action){
            case 'favorite':
                $count=$this->pagesModel->set_favorite_bulk($user_id,$ids,1);
                return $this->send_json(['status'=>'ok','updated'=>$count]);
            case 'unfavorite':
                $count=$this->pagesModel->set_favorite_bulk($user_id,$ids,0);
                return $this->send_json(['status'=>'ok','updated'=>$count]);
            case 'unlink':
                $count=$this->pagesModel->unlink_pages($user_id,$ids);
                return $this->send_json(['status'=>'ok','deleted'=>$count]);
            case 'sync':
                $synced=0;
                foreach($ids as $pid){
                    if($this->_sync_single_page($user_id,$pid)) $synced++;
                }
                return $this->send_json(['status'=>'ok','synced'=>$synced]);
            default:
                return $this->send_json(['status'=>'error','msg'=>'unknown_action'],400);
        }
    }

    public function ajax_sync_page()
    {
        $this->require_login();
        $user_id=(int)$this->session->userdata('user_id');
        $page_id=$this->input->post('page_id',true);
        if(!$page_id) return $this->send_json(['status'=>'error','msg'=>'missing_page'],400);
        $ok = $this->_sync_single_page($user_id,$page_id);
        return $this->send_json(['status'=>$ok?'ok':'error']);
    }

    public function ajax_unlink_page()
    {
        $this->require_login();
        $user_id=(int)$this->session->userdata('user_id');
        $page_id=$this->input->post('page_id',true);
        if(!$page_id) return $this->send_json(['status'=>'error','msg'=>'missing_page'],400);
        $deleted=$this->pagesModel->unlink_pages($user_id,[$page_id]);
        return $this->send_json(['status'=>'ok','deleted'=>$deleted]);
    }

    public function ajax_scheduled_list()
    {
        $this->require_login();
        $user_id=(int)$this->session->userdata('user_id');
        $page_id=$this->input->get('page_id',true);
        if(!$page_id) return $this->send_json(['status'=>'error','msg'=>'missing_page'],400);

        if(!$this->db->table_exists('scheduled_reels')){
            return $this->send_json(['status'=>'ok','items'=>[]]);
        }

        $items=$this->db->where('user_id',$user_id)
                        ->where('fb_page_id',$page_id)
                        ->order_by('scheduled_time','ASC')
                        ->limit(200)
                        ->get('scheduled_reels')->result_array();

        return $this->send_json(['status'=>'ok','items'=>$items]);
    }

    /* ====== Helper for Sync Upsert ====== */
    private function _sync_single_page(int $user_id,string $fb_page_id): bool
    {
        $row = $this->db->where('user_id',$user_id)
                        ->where('fb_page_id',$fb_page_id)
                        ->get('facebook_pages')->row_array();
        if(!$row) return false;
        $acctoken = $row['page_access_token'];
        if(!$acctoken) return false;

        $url = "https://graph.facebook.com/v23.0/".$fb_page_id."?fields=id,name,picture&access_token=".urlencode($acctoken);
        $resp = @file_get_contents($url);
        if($resp===false) return false;
        $j = json_decode($resp,true);
        if(empty($j['id'])) return false;

        $pic = '';
        if(isset($j['picture']['data']['url'])) $pic = $j['picture']['data']['url'];

        $this->pagesModel->upsert_page($user_id,[
            'fb_page_id'=>$fb_page_id,
            'page_name'=>$j['name'] ?? $row['page_name'],
            'page_picture'=>$pic ?: $row['page_picture'],
            'page_access_token'=>$acctoken
        ]);
        return true;
    }
}