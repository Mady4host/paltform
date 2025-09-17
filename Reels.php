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
    const MAX_SCHEDULE_FILES     = 200;
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

/***************************** Helper: Normalize Schedule String *****************************/
private function normalizeLocalSchedule(?string $s): ?string
{
    if (!$s) return null;
    $s = trim($s);
    if ($s === '') return null;
    $s = str_replace('/', '-', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    // YYYY-MM-DD HH:MM:SS -> YYYY-MM-DD HH:MM
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $s)) {
        $s = substr($s, 0, 16);
    }
    // مسافة إلى T للتوافق مع localToUtc
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) {
        $s = str_replace(' ', 'T', $s);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $s)) {
        $ts = strtotime($s);
        if ($ts === false) return null;
        $s = date('Y-m-d\TH:i', $ts);
    }
    return $s;
}

    /* ====== Lock helpers ====== */

    private function resolveLockPath(string $name): string
    {
        $dir = FCPATH.'application/cache/locks/';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (is_dir($dir) && is_writable($dir)) {
            return $dir.$name;
        }
        $tmp = @sys_get_temp_dir();
        if ($tmp && is_dir($tmp) && is_writable($tmp)) {
            return rtrim($tmp,'/').'/'.$name;
        }
        return FCPATH.$name;
    }

    private function withLock(string $lockName, callable $fn): void
    {
        $lock = $this->resolveLockPath($lockName);
        $this->dbg('cron_lock_path', $lock);

        $fh = @fopen($lock, 'c+');
        if (!$fh) {
            $this->dbg('cron_lock_open_fail', $lock);
            $fn();
            return;
        }
        if (!flock($fh, LOCK_EX|LOCK_NB)) {
            echo "Another instance running (lock=$lock)\n";
            fclose($fh);
            return;
        }
        try {
            $fn();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
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
    $uid = (int)$this->session->userdata('user_id');

    $stories_log = function(string $label, array $ctx = []) {
        $dir = FCPATH.'application/logs/';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $line = '['.gmdate('Y-m-d H:i:s')."] $label";
        if (!empty($ctx)) $line .= ' '.json_encode($ctx, JSON_UNESCAPED_UNICODE);
        @file_put_contents($dir.'stories_api.log', $line.PHP_EOL, FILE_APPEND);
    };

    $stories_log('CTRL_ENTER', [
        'ini' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'       => ini_get('post_max_size'),
            'memory_limit'        => ini_get('memory_limit'),
            'max_execution_time'  => ini_get('max_execution_time'),
        ],
        'post_media_type' => $this->input->post('media_type', true),
    ]);

    $pages = $this->pagesModel->get_pages_by_user($uid);
    if (!$pages) {
        $stories_log('CTRL_NO_PAGES', []);
        $this->session->set_flashdata('msg','لا يوجد صفحات.');
        redirect('reels/upload'); return;
    }
    $pagesTokens = $this->Reel_model->get_user_pages($uid);

    $media_type  = $this->input->post('media_type') ?: 'reel';
    $fb_page_ids = $this->input->post('fb_page_ids');

    if (empty($fb_page_ids)) {
        $stories_log('CTRL_NO_SELECTED_PAGES', []);
        $this->session->set_flashdata('msg','اختر صفحة واحدة على الأقل.');
        redirect('reels/upload'); return;
    }

    $findPageToken = function($pagesTokens, string $pid) {
        if (is_array($pagesTokens)) {
            if (isset($pagesTokens[$pid]) && is_array($pagesTokens[$pid])) {
                $row = $pagesTokens[$pid];
                return $row['page_access_token'] ?? $row['access_token'] ?? null;
            }
            foreach ($pagesTokens as $row) {
                if (!is_array($row)) continue;
                $rid = $row['fb_page_id'] ?? $row['page_id'] ?? null;
                if ((string)$rid === (string)$pid) {
                    return $row['page_access_token'] ?? $row['access_token'] ?? null;
                }
            }
        }
        return null;
    };

    $valid_pages = [];
    $missing_token = [];
    foreach ((array)$fb_page_ids as $pid) {
        $tok = $findPageToken($pagesTokens, (string)$pid);
        if (!empty($tok)) $valid_pages[] = (string)$pid;
        else $missing_token[] = (string)$pid;
    }

    $stories_log('CTRL_PAGES_FILTER', [
        'selected'       => array_values((array)$fb_page_ids),
        'valid_pages'    => $valid_pages,
        'missing_token'  => $missing_token
    ]);

    if ($missing_token) {
        $this->session->set_flashdata('msg', 'تم استبعاد صفحات بدون توكن: '.implode(', ', $missing_token));
    }
    if (!$valid_pages) {
        $stories_log('CTRL_NO_VALID_PAGES', ['selected_count'=>count((array)$fb_page_ids)]);
        $this->session->set_flashdata('msg', 'لا توجد صفحات صالحة للنشر (توكن مفقود).');
        redirect('reels/upload'); return;
    }

    $stories_log('CTRL_BEFORE_BRANCH', [
        'media_type'=>$media_type,
        'FEATURE_STORIES'=>self::FEATURE_STORIES,
        'selected_pages_count'=>count((array)$fb_page_ids),
        'valid_pages_count'=>count($valid_pages)
    ]);

    /* Story Photo: دعم الفوري + المجدول */
   if ($media_type === 'story_photo' && self::FEATURE_STORIES) {
    try {
        $sf = $_FILES['story_photo_file'] ?? null;
        $isArray = $sf && is_array($sf['name']);
        $stories_log('CTRL_STORY_PHOTO_ENTRY', [
            'file_present' => $sf ? (!empty($sf['name']) || !empty($sf['name'][0])) : false,
            'is_array'     => $isArray,
            'file_err'     => $sf['error'] ?? null,
            'file_size'    => $sf['size'] ?? null,
            'file_type'    => $sf['type'] ?? null,
            'pages'        => $valid_pages
        ]);

        if (!$sf) {
            $this->session->set_flashdata('msg','اختر صورة للستوري.');
            redirect('reels/upload'); return;
        }

        $names = $isArray ? (array)$sf['name']     : [$sf['name']];
        $types = $isArray ? (array)$sf['type']     : [$sf['type']];
        $tmps  = $isArray ? (array)$sf['tmp_name'] : [$sf['tmp_name']];
        $errs  = $isArray ? (array)$sf['error']    : [$sf['error']];
        $sizes = $isArray ? (array)$sf['size']     : [$sf['size']];
        $count = count($names);

        $tz_offset = (int)($this->input->post('tz_offset_minutes') ?? 0);
        $tz_name   = trim((string)$this->input->post('tz_name'));

        $candidateArrays = [];
        $preferKeys = [
            'story_schedule_locals','story_schedule_times',
            'photo_schedule_locals','photo_schedule_times',
            'story_photo_schedule_locals','story_photo_schedule_times',
            'schedule_times','scheduled_times'
        ];
        foreach ($preferKeys as $k) {
            $v = $_POST[$k] ?? null;
            if (is_array($v)) $candidateArrays[$k] = $v;
        }
        foreach ($_POST as $k=>$v) {
            if (!is_array($v)) continue;
            $kn = strtolower((string)$k);
            if (preg_match('/(sched|time|date)/', $kn) && preg_match('/(story|photo)/', $kn)) {
                if (!isset($candidateArrays[$k])) $candidateArrays[$k] = $v;
            }
        }

        $singleCandidates = [
            'story_schedule_local','story_schedule_time','story_scheduled_time',
            'schedule_time','scheduled_time','scheduled_local','schedule_local'
        ];
        $singleValues = [];
        foreach ($singleCandidates as $k) {
            $val = trim((string)($this->input->post($k) ?? ''));
            if ($val !== '') { $singleValues[$k] = $val; }
        }

        $stories_log('CTRL_STORY_PHOTO_SCHED_KEYS', [
            'post_keys'      => array_keys($_POST),
            'candidate_keys' => array_keys($candidateArrays),
            'single_keys'    => array_keys($singleValues)
        ]);

        $allowedImg = ['jpg','jpeg','png','webp','gif'];
        $successMsgs = [];
        $errorMsgs   = [];

        for ($i=0; $i<$count; $i++) {
            $fileOne = [
                'name'     => $names[$i] ?? '',
                'type'     => $types[$i] ?? '',
                'tmp_name' => $tmps[$i]  ?? '',
                'error'    => (int)($errs[$i] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int)($sizes[$i] ?? 0),
            ];

            if ($fileOne['error'] === UPLOAD_ERR_INI_SIZE || $fileOne['error'] === UPLOAD_ERR_FORM_SIZE) {
                $stories_log('CTRL_STORY_PHOTO_SIZE_LIMIT', ['idx'=>$i,'err'=>$fileOne['error']]);
                $errorMsgs[] = 'حجم الصورة يتجاوز الحد الأقصى (عنصر #'.($i+1).').';
                continue;
            }
            if ($fileOne['error'] !== UPLOAD_ERR_OK || !is_file($fileOne['tmp_name'])) {
                $stories_log('CTRL_STORY_PHOTO_UPLOAD_ERR', ['idx'=>$i,'err'=>$fileOne['error']]);
                $errorMsgs[] = 'فشل رفع ملف ستوري الصورة (عنصر #'.($i+1).').';
                continue;
            }

            $ext = strtolower(pathinfo($fileOne['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedImg, true)) {
                $errorMsgs[] = 'امتداد غير مدعوم لستوري الصورة (عنصر #'.($i+1).').';
                continue;
            }

            $desc = trim(xss_clean((string)$this->input->post('description')));
            if ($desc==='') {
                $base = pathinfo($fileOne['name'], PATHINFO_FILENAME);
                $desc = $base;
            }
            $selected_tags = trim((string)$this->input->post('selected_hashtags'));
            if ($selected_tags!=='') {
                foreach (preg_split('/\s+/u',$selected_tags) as $tg){
                    if ($tg==='') continue;
                    if (stripos($desc,$tg)===false) $desc.=' '.$tg;
                }
            }

            $rawLocal = '';
            foreach ($candidateArrays as $k=>$arr) {
                if (isset($arr[$i]) && trim((string)$arr[$i])!=='') {
                    $rawLocal = trim((string)$arr[$i]); break;
                }
            }
            if ($rawLocal === '' && $i === 0) {
                foreach ($singleValues as $v) {
                    if (trim($v)!==''){ $rawLocal = trim($v); break; }
                }
            }

            $normLocal = $this->normalizeLocalSchedule($rawLocal);
            $parsedUtc = $normLocal ? $this->localToUtc($normLocal, $tz_offset) : null;
            $isFuture  = $this->isFutureUtc($parsedUtc);

            $stories_log('CTRL_STORY_PHOTO_ITEM', [
                'idx'=>$i,
                'name'=>$fileOne['name'],
                'raw'=>$rawLocal,
                'normalized'=>$normLocal,
                'parsed_utc'=>$parsedUtc,
                'is_future'=>$isFuture
            ]);

            if ($parsedUtc && $isFuture) {
                $absDir = FCPATH.self::SCHEDULE_DIR;
                if (!is_dir($absDir)) @mkdir($absDir,0775,true);

                $safe  = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', $fileOne['name']);
                $fname = 'story_photo_sched_'.time().'_'.$i.'_'.mt_rand(1000,9999).'_'.$safe;

                if (!move_uploaded_file($fileOne['tmp_name'], $absDir.$fname)) {
                    $errorMsgs[] = 'فشل حفظ الصورة للجدولة (عنصر #'.($i+1).').';
                    continue;
                }

                $original_local_time = null;
                if ($normLocal && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $normLocal)) {
                    $original_local_time = str_replace('T',' ', $normLocal).':00';
                }

                $now = gmdate('Y-m-d H:i:s');
                $rows = [];
                foreach ($valid_pages as $pid) {
                    $rows[] = [
                        'user_id'                 => $uid,
                        'fb_page_id'              => $pid,
                        'video_path'              => self::SCHEDULE_DIR.$fname,
                        'description'             => $desc,
                        'scheduled_time'          => $parsedUtc,
                        'original_local_time'     => $original_local_time,
                        'original_offset_minutes' => $tz_offset,
                        'original_timezone'       => $tz_name,
                        'media_type'              => 'story_photo',
                        'status'                  => 'pending',
                        'attempt_count'           => 0,
                        'processing'              => 0,
                        'created_at'              => $now
                    ];
                }
                $this->db->insert_batch('scheduled_reels', $rows);
                $stories_log('PHOTO_SCHED_CREATE', [
                    'idx'=>$i,
                    'pages'=>count($valid_pages),
                    'utc'=>$parsedUtc,
                    'file'=>self::SCHEDULE_DIR.$fname
                ]);
                $successMsgs[] = 'تمت جدولة ستوري الصورة (عنصر #'.($i+1).') لعدد '.count($valid_pages).' صفحة.';
            } else {
                $_POST['fb_page_ids'] = $valid_pages;
                $filesNormalized = ['story_photo_file' => $fileOne];
                $responses = $this->Reel_model->upload_story_photo($uid, $pagesTokens, $_POST, $filesNormalized);

                foreach ($responses as $r) {
                    if (($r['type'] ?? '') === 'success') $successMsgs[] = $r['msg'] ?? '';
                    else $errorMsgs[] = $r['msg'] ?? '';
                }
            }
        } // end for

        if ($successMsgs) $this->session->set_flashdata('msg_success', implode('<br>', array_filter($successMsgs)));
        if ($errorMsgs)   $this->session->set_flashdata('msg', implode('<br>', array_filter($errorMsgs)));

        if ($this->input->is_ajax_request()) {
            $this->send_json([
                'success'=> empty($errorMsgs),
                'messages'=> array_merge(
                    array_map(fn($s)=>['type'=>'success','msg'=>$s], array_filter($successMsgs)),
                    array_map(fn($e)=>['type'=>'error','msg'=>$e], array_filter($errorMsgs))
                )
            ], empty($errorMsgs) ? 200 : 400);
            return;
        }
        redirect('reels/list'); return;

    } catch (Throwable $e) {
        log_message('error','story_photo_fatal: '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
        $stories_log('CTRL_STORY_PHOTO_FATAL', ['msg'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
        if ($this->input->is_ajax_request()) {
            $this->send_json(['success'=>false,'error'=>'internal_error','message'=>$e->getMessage()], 500);
            return;
        }
        $this->session->set_flashdata('msg','حصل خطأ داخلي أثناء نشر/جدولة ستوري الصورة.');
        redirect('reels/upload'); return;
    }
}
    /* Story Video */
    if ($media_type === 'story_video' && self::FEATURE_STORIES) {
        try {
            $stories_log('CTRL_STORY_VIDEO_ENTRY', [
                'first_name' => $_FILES['video_files']['name'][0] ?? '',
                'first_size' => (int)($_FILES['video_files']['size'][0] ?? 0),
                'first_err'  => (int)($_FILES['video_files']['error'][0] ?? -1),
                'pages'      => $valid_pages
            ]);

            if (empty($_FILES['video_files']['name'][0])) {
                $this->session->set_flashdata('msg','اختر ملفات فيديو (ستوري).');
                redirect('reels/upload'); return;
            }

            $_POST['fb_page_ids'] = $valid_pages;

            $responses = $this->Reel_model->upload_story_video($uid, $pagesTokens, $_POST, $_FILES);
            $success=[]; $error=[];
            foreach ($responses as $r) { if (($r['type'] ?? '')==='success') $success[]=$r['msg'] ?? ''; else $error[]=$r['msg'] ?? ''; }

            $stories_log('CTRL_STORY_VIDEO_DONE', ['success'=>$success, 'error'=>$error]);

            if ($success) $this->session->set_flashdata('msg_success',implode('<br>',array_filter($success)));
            if ($error)   $this->session->set_flashdata('msg',implode('<br>',array_filter($error)));

            if ($this->input->is_ajax_request()){
                $this->send_json([
                    'success'=>empty(array_filter($error)),
                    'messages'=>array_merge(
                        array_map(fn($s)=>['type'=>'success','msg'=>$s],array_filter($success)),
                        array_map(fn($e)=>['type'=>'error','msg'=>$e],array_filter($error))
                    )
                ], empty(array_filter($error))?200:400);
                return;
            }
            redirect('reels/list'); return;

        } catch (Throwable $e) {
            log_message('error','story_video_fatal: '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
            $stories_log('CTRL_STORY_VIDEO_FATAL', ['msg'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
            if ($this->input->is_ajax_request()){
                $this->send_json(['success'=>false,'error'=>'internal_error','message'=>$e->getMessage()],500);
                return;
            }
            $this->session->set_flashdata('msg','حصل خطأ داخلي أثناء نشر ستوري الفيديو.');
            redirect('reels/upload'); return;
        }
    }

    /* ريلز */
    if (empty($_FILES['video_files']['name'][0])) {
        $this->session->set_flashdata('msg','اختر ملفات فيديو.');
        redirect('reels/upload'); return;
    }

    $global_desc = trim(xss_clean((string)$this->input->post('description')));
    $descs       = $this->input->post('descriptions') ?: [];
    $sched_local = $this->input->post('schedule_times') ?: [];
    $comments    = $this->input->post('comments') ?: [];
    $tz_offset   = (int)($this->input->post('tz_offset_minutes') ?? 0);
    $tz_name     = trim((string)$this->input->post('tz_name'));

    $names = $_FILES['video_files']['name'];
    $tmps  = $_FILES['video_files']['tmp_name'];
    $sizes = $_FILES['video_files']['size'];
    $errs  = $_FILES['video_files']['error'];
    $count = count($names);

    if ($count > self::MAX_SCHEDULE_FILES) {
        $this->session->set_flashdata('msg','عدد الملفات كبير.');
        redirect('reels/upload'); return;
    }

    $scheduled=[]; $immediate=[];
    for ($i=0; $i<$count; $i++) {
        $local = $sched_local[$i] ?? '';
        if ($local===''){ $immediate[]=$i; continue; }
        $utc = $this->localToUtc($local, $tz_offset);
        if ($this->isFutureUtc($utc)) $scheduled[]=$i; else $immediate[]=$i;
    }
    $this->dbg('classification',['scheduled'=>$scheduled,'immediate'=>$immediate]);

    $scheduled_msg='';
    if ($scheduled) {
        $scheduled_msg = $this->scheduleBatch(
            $uid, $valid_pages, $scheduled,
            $names, $tmps, $sizes, $errs,
            $descs, $global_desc, $sched_local,
            $tz_offset, $tz_name, $comments
        );
    }

    $immediate_msgs=[];
    if ($immediate) {
        $subsetFiles = $this->subsetFiles($_FILES,'video_files',$immediate);
        $subsetPost  = [
            'fb_page_ids'      => $valid_pages,
            'descriptions'     => [],
            'schedule_times'   => [],
            'comments'         => [],
            'tz_offset_minutes'=> $tz_offset,
            'tz_name'          => $tz_name,
            'description'      => $global_desc,
            'selected_hashtags'=> $this->input->post('selected_hashtags')
        ];
        $newIdx=0;
        foreach ($immediate as $orig) {
            $subsetPost['descriptions'][$newIdx]   = $descs[$orig] ?? '';
            $subsetPost['schedule_times'][$newIdx] = $sched_local[$orig] ?? '';
            $subsetPost['comments'][$newIdx]       = $comments[$orig] ?? [];
            $newIdx++;
        }
        $immediate_msgs = $this->Reel_model->upload_reels($uid, $pagesTokens, $subsetPost, $subsetFiles);
    }

    $success=[]; $error=[];
    if ($scheduled_msg) $success[] = $scheduled_msg;
    foreach ($immediate_msgs as $m){
        if (($m['type'] ?? '')==='success') $success[]=$m['msg'] ?? ''; else $error[]=$m['msg'] ?? '';
    }

    if ($success) $this->session->set_flashdata('msg_success', implode('<br>', array_filter($success)));
    if ($error)   $this->session->set_flashdata('msg', implode('<br>', array_filter($error)));

    if ($this->input->is_ajax_request()){
        $this->send_json([
            'success'=> empty(array_filter($error)),
            'messages'=> array_merge(
                array_map(fn($s)=>['type'=>'success','msg'=>$s], array_filter($success)),
                array_map(fn($e)=>['type'=>'error','msg'=>$e], array_filter($error))
            )
        ], empty(array_filter($error)) ? 200 : 400);
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
    if(!is_dir($absDir)) @mkdir($absDir,0775,true);

    $selected_tags = trim((string)$this->input->post('selected_hashtags'));
    $rows=[]; $now=gmdate('Y-m-d H:i:s'); $saved=0; $mapIdxToPages=[];

    $hasMediaType = $this->db->field_exists('media_type','scheduled_reels');

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
            $row = [
                'user_id'                  => $uid,
                'fb_page_id'               => $pid,
                'video_path'               => self::SCHEDULE_DIR.$fname,
                'description'              => $desc,
                'scheduled_time'           => $utc,
                'original_local_time'      => $original_local_time,
                'original_offset_minutes'  => $tz_offset,
                'original_timezone'        => $tz_name,
                'status'                   => 'pending',
                'attempt_count'            => 0,
                'processing'               => 0,
                'created_at'               => $now
            ];
            if ($hasMediaType) $row['media_type'] = 'reel';

            $rows[] = $row;
        }
        $mapIdxToPages[$i]=$page_ids;
    }

    if(!$rows) return 'لم يتم جدولة أي ملف.';

    $this->db->insert_batch('scheduled_reels',$rows);

    if ($this->db->table_exists('facebook_pages')) {
        $hasLastScheduled = $this->db->field_exists('last_scheduled_at','facebook_pages');
        $hasSchedCount    = $this->db->field_exists('scheduled_count','facebook_pages');
        if ($hasLastScheduled || $hasSchedCount) {
            foreach($page_ids as $pid){
                $this->db->where('user_id',$uid)->where('fb_page_id',$pid);
                if ($hasLastScheduled) {
                    $this->db->set('last_scheduled_at', $now);
                }
                if ($hasSchedCount) {
                    $this->db->set('scheduled_count','scheduled_count+'.$saved,false);
                }
                $this->db->update('facebook_pages');
            }
        }
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

        $stories_log = function(string $label, array $ctx = []) {
            $dir = FCPATH.'application/logs/';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $line = '['.gmdate('Y-m-d H:i:s')."] $label";
            if (!empty($ctx)) $line .= ' '.json_encode($ctx, JSON_UNESCAPED_UNICODE);
            @file_put_contents($dir.'stories_api.log', $line.PHP_EOL, FILE_APPEND);
        };

        $this->withLock('reels_cron.lock', function () use ($stories_log) {
            $now = gmdate('Y-m-d H:i:s');
            $due = $this->Reel_model->get_due_scheduled_reels(40);
            $stories_log('CRON_RUN', ['now'=>$now, 'due_count'=>count($due)]);

            foreach($due as $r){
                $id = (int)($r['id'] ?? 0);
                $mt = (string)($r['media_type'] ?? '');
                $vp = (string)($r['video_path'] ?? '');
                $pid = (string)($r['fb_page_id'] ?? '');

                $stories_log('CRON_PICK', [
                    'id'=>$id,'media_type'=>$mt,'page'=>$pid,'path'=>$vp,'scheduled_time'=>($r['scheduled_time'] ?? null)
                ]);

                $ext = strtolower(pathinfo($vp, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg','jpeg','png','webp','gif'], true);

                try {
                    if (self::FEATURE_STORIES) {
                        if ($mt === 'story_photo' || ($mt === '' && $isImage)) {
                            if (method_exists($this->Reel_model,'publish_scheduled_story_photo')) {
                                $this->Reel_model->publish_scheduled_story_photo($r);
                                continue;
                            } else {
                                $stories_log('CRON_WARN', ['id'=>$id,'msg'=>'publish_scheduled_story_photo missing']);
                            }
                        }
                        if ($mt === 'story_video') {
                            if (method_exists($this->Reel_model,'publish_scheduled_story_video')) {
                                $this->Reel_model->publish_scheduled_story_video($r);
                                continue;
                            } else {
                                $stories_log('CRON_WARN', ['id'=>$id,'msg'=>'publish_scheduled_story_video missing']);
                            }
                        }
                    }

                    $this->Reel_model->process_scheduled_reel($r);

                } catch (Throwable $e) {
                    $stories_log('CRON_EXCEPTION', ['id'=>$id,'msg'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
                }
            }

            $stories_log('CRON_DONE', []);
            echo "Processed ".count($due)." scheduled items.\n";
        });
    }
    public function cron_comments($token=null)
    {
        if(!$this->input->is_cli_request()){
            if($token!==self::CRON_TOKEN){ show_error('Unauthorized',403); return; }
        }
        $this->withLock('reels_comments.lock', function () {
            $rows=$this->Reel_model->get_due_scheduled_comments(80);
            foreach($rows as $r){ $this->Reel_model->process_scheduled_comment($r); }
            echo "Processed ".count($rows)." scheduled comments.\n";
        });
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
