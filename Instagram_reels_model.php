<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Instagram_reels_model (Scheduling v2.3 UTC)
 *
 * تحديثات هذه النسخة:
 *  - الحفاظ على التخزين UTC (باستخدام gmdate).
 *  - دعم schedule_index وحقول الوقت الأصلي (original_local_time / original_offset_minutes / original_timezone) لو أرسلت من الكنترولر.
 *  - تحسين fetch_due_scheduled لالتقاط السجلات ذات الحالة scheduled فقط (مع خيار اختياري لالتقاط pending أيضاً للتوافق الخلفي).
 *  - حماية إضافية: عند mark_publishing يتم اعتبار attempt_count = COALESCE(attempt_count,0)+1 (لو كانت NULL لأي سبب).
 *  - تثبيت status داخل create_scheduled_batch إلى STATUS_SCHEDULED (مقصود) حتى لو أُرسل شيء آخر في perAccountBase لضمان الاتساق.
 *  - دوال وتعليقات عربية توضيحية.
 *
 * ملاحظات:
 *  - الكنترولر الحالي يمرر الوقت المحلي محوّلاً مسبقاً إلى UTC في scheduled_time ويضع الأصل في original_local_time.
 *  - لا حاجة لتعديل إضافي على الكنترولر بعد هذه النسخة.
 */
class Instagram_reels_model extends CI_Model
{
    protected $table = 'instagram_reels';

    /* ثوابت الحالة */
    public const STATUS_PENDING     = 'pending';
    public const STATUS_UPLOADING   = 'uploading';
    public const STATUS_SCHEDULED   = 'scheduled';
    public const STATUS_PUBLISHING  = 'publishing';
    public const STATUS_PUBLISHED   = 'published';
    public const STATUS_FAILED      = 'failed';

    /* أنواع الوسائط */
    public const KIND_REEL_VIDEO  = 'ig_reel';
    public const KIND_STORY_IMAGE = 'ig_story_image';
    public const KIND_STORY_VIDEO = 'ig_story_video';

    /* التكرار */
    public const REC_NONE       = 'none';
    public const REC_DAILY      = 'daily';
    public const REC_WEEKLY     = 'weekly';
    public const REC_MONTHLY    = 'monthly';
    public const REC_QUARTERLY  = 'quarterly';

    protected $schemaCache = null;

    /* =================== أدوات داخلية =================== */

    private function nowUtc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    protected function loadSchema()
    {
        if ($this->schemaCache !== null) return;
        $cols = $this->db->query("SHOW COLUMNS FROM `{$this->table}`")->result_array();
        $map = [];
        foreach ($cols as $c) {
            $map[$c['Field']] = true;
        }
        $this->schemaCache = $map;
    }

    protected function hasColumn($col)
    {
        $this->loadSchema();
        return isset($this->schemaCache[$col]);
    }

    protected function baseUserQuery($user_id)
    {
        $this->db->from($this->table)
                 ->where('user_id', $user_id);
        if ($this->hasColumn('deleted_at')) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
    }

    protected function tokenize($q)
    {
        $q = trim($q);
        if ($q === '') return [];
        $parts = preg_split('/\s+/', $q);
        return array_filter(array_unique($parts), fn($w) => mb_strlen($w) >= 2);
    }

    protected function applyFilters(array $filters)
    {
        if (!empty($filters['ig_user_id'])) {
            $this->db->where('ig_user_id', $filters['ig_user_id']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }
        if (!empty($filters['media_kind'])) {
            $this->db->where('media_kind', $filters['media_kind']);
        }
        if (!empty($filters['publish_mode']) && $this->hasColumn('publish_mode')) {
            $this->db->where('publish_mode', $filters['publish_mode']);
        }
        if (!empty($filters['recurrence_kind']) && $this->hasColumn('recurrence_kind')) {
            $this->db->where('recurrence_kind', $filters['recurrence_kind']);
        }

        if (!empty($filters['date_from'])) {
            $this->db->where('created_at >=', $filters['date_from'].' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('created_at <=', $filters['date_to'].' 23:59:59');
        }

        if (!empty($filters['q'])) {
            $tokens = $this->tokenize($filters['q']);
            if ($tokens) {
                $this->db->group_start();
                foreach ($tokens as $t) {
                    $this->db->group_start()
                             ->like('file_name', $t)
                             ->or_like('description', $t)
                             ->or_like('media_id', $t)
                             ->group_end();
                }
                $this->db->group_end();
            }
        }
    }

    protected function applyOrdering($orderBy, $dir)
    {
        $allowed = ['id','created_at','published_at','status','media_kind','scheduled_time'];
        if (!in_array($orderBy, $allowed, true)) {
            $orderBy = 'id';
        }
        $dir = strtolower($dir)==='asc' ? 'ASC' : 'DESC';
        $this->db->order_by($orderBy, $dir);
    }

    /* =================== CRUD =================== */

    public function insert_record(array $data)
    {
        $now = $this->nowUtc();
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $now;
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_PENDING;
        }
        if ($this->hasColumn('publish_mode') && empty($data['publish_mode'])) {
            $data['publish_mode'] = 'immediate';
        }
        $this->db->insert($this->table, $data);
        return (int)$this->db->insert_id();
    }

    public function get($id, $user_id)
    {
        $this->baseUserQuery($user_id);
        $this->db->where('id', $id);
        return $this->db->get()->row_array();
    }

    public function get_batch_by_ids($user_id, array $ids)
    {
        $ids = array_values(array_filter(array_unique(array_map('intval',$ids))));
        if (!$ids) return [];
        $this->baseUserQuery($user_id);
        $this->db->where_in('id', $ids);
        $rows = $this->db->get()->result_array();
        $map = [];
        foreach ($rows as $r) $map[$r['id']] = $r;
        $ordered=[];
        foreach ($ids as $i) if(isset($map[$i])) $ordered[]=$map[$i];
        return $ordered;
    }

    public function mark_published($id, $media_id, $creation_id = null)
    {
        $now = $this->nowUtc();
        $this->db->where('id', $id)->update($this->table, [
            'status'       => self::STATUS_PUBLISHED,
            'media_id'     => $media_id,
            'creation_id'  => $creation_id,
            'published_at' => $now,
            'updated_at'   => $now,
        ]);
        return $this->db->affected_rows();
    }

    public function mark_failed($id, $error, $creation_id = null)
    {
        $now = $this->nowUtc();
        $creation_id_val = $creation_id;
        if ($creation_id_val === null) {
            $creation_id_val = $this->db->select('creation_id')->where('id',$id)->get($this->table)->row('creation_id');
        }
        $this->db->where('id', $id)->update($this->table, [
            'status'      => self::STATUS_FAILED,
            'last_error'  => $error,
            'creation_id' => $creation_id_val,
            'updated_at'  => $now,
        ]);
        return $this->db->affected_rows();
    }

    public function update_status($id, $status, array $extra = [])
    {
        $allowed = [
            self::STATUS_PENDING,
            self::STATUS_UPLOADING,
            self::STATUS_SCHEDULED,
            self::STATUS_PUBLISHING,
            self::STATUS_PUBLISHED,
            self::STATUS_FAILED
        ];
        if (!in_array($status, $allowed, true)) return 0;
        $extra['status'] = $status;
        $extra['updated_at'] = $this->nowUtc();
        $this->db->where('id', $id)->update($this->table, $extra);
        return $this->db->affected_rows();
    }

    public function reset_for_retry($id, $user_id, $new_creation_id = null)
    {
        $r = $this->get($id,$user_id);
        if (!$r || $r['status'] !== self::STATUS_FAILED) return 0;
        $now = $this->nowUtc();
        $data = [
            'status'       => self::STATUS_PENDING,
            'last_error'   => null,
            'updated_at'   => $now,
            'published_at' => null,
            'media_id'     => null
        ];
        if ($new_creation_id) $data['creation_id'] = $new_creation_id;
        $this->db->where('id',$id)->update($this->table, $data);
        return $this->db->affected_rows();
    }

    public function soft_delete($id, $user_id)
    {
        if (!$this->hasColumn('deleted_at')) return 0;
        $now = $this->nowUtc();
        $this->db->where('id',$id)->where('user_id',$user_id)
                 ->update($this->table, [
                     'deleted_at'=>$now,
                     'updated_at'=>$now
                 ]);
        return $this->db->affected_rows();
    }

    /* =================== استعلامات وإحصائيات =================== */

    public function get_by_user($user_id, $filters = [], $limit = 50, $offset = 0, $orderBy='id', $dir='DESC')
    {
        $this->baseUserQuery($user_id);
        $this->applyFilters($filters);
        $this->applyOrdering($orderBy,$dir);
        $this->db->limit($limit,$offset);
        return $this->db->get()->result_array();
    }

    public function count_by_user($user_id, $filters = [])
    {
        $this->baseUserQuery($user_id);
        $this->applyFilters($filters);
        return (int)$this->db->count_all_results();
    }

    public function summary_counts($user_id)
    {
        $this->baseUserQuery($user_id);
        $this->db->select('status, COUNT(*) c')->group_by('status');
        $rows = $this->db->get()->result_array();
        $out = [
            self::STATUS_PENDING    => 0,
            self::STATUS_UPLOADING  => 0,
            self::STATUS_SCHEDULED  => 0,
            self::STATUS_PUBLISHING => 0,
            self::STATUS_PUBLISHED  => 0,
            self::STATUS_FAILED     => 0
        ];
        foreach ($rows as $r) if(isset($out[$r['status']])) $out[$r['status']] = (int)$r['c'];
        return $out;
    }

    public function kind_counts($user_id)
    {
        $this->baseUserQuery($user_id);
        $this->db->select('media_kind, COUNT(*) c')->group_by('media_kind');
        $rows = $this->db->get()->result_array();
        $out=[];
        foreach($rows as $r) $out[$r['media_kind']] = (int)$r['c'];
        return $out;
    }

    public function latest_published_for_account($user_id, $ig_user_id, $limit = 10)
    {
        $this->baseUserQuery($user_id);
        $this->db->where('ig_user_id',$ig_user_id)
                 ->where('status', self::STATUS_PUBLISHED)
                 ->order_by('published_at','DESC')
                 ->limit($limit);
        return $this->db->get()->result_array();
    }

    public function latest_failed($user_id, $limit = 10)
    {
        $this->baseUserQuery($user_id);
        $this->db->where('status', self::STATUS_FAILED)
                 ->order_by('updated_at','DESC')
                 ->limit($limit);
        return $this->db->get()->result_array();
    }

    public function first_pending($user_id, $ig_user_id = null)
    {
        $this->baseUserQuery($user_id);
        $this->db->where('status', self::STATUS_PENDING);
        if ($ig_user_id) $this->db->where('ig_user_id',$ig_user_id);
        $this->db->order_by('id','ASC')->limit(1);
        return $this->db->get()->row_array();
    }

    public function bulk_update_status($user_id, array $ids, $newStatus)
    {
        if(!$ids) return 0;
        $allowed = [
            self::STATUS_PENDING,
            self::STATUS_UPLOADING,
            self::STATUS_SCHEDULED,
            self::STATUS_PUBLISHING,
            self::STATUS_PUBLISHED,
            self::STATUS_FAILED
        ];
        if(!in_array($newStatus,$allowed,true)) return 0;
        $ids = array_values(array_filter(array_unique(array_map('intval',$ids))));
        if(!$ids) return 0;

        $this->db->where('user_id',$user_id)
                 ->where_in('id',$ids)
                 ->update($this->table, [
                     'status'=>$newStatus,
                     'updated_at'=>$this->nowUtc()
                 ]);
        return $this->db->affected_rows();
    }

    public function failed_recent($user_id, $minutes = 60, $limit = 50)
    {
        $this->baseUserQuery($user_id);
        $since = gmdate('Y-m-d H:i:s', time() - $minutes*60);
        $this->db->where('status', self::STATUS_FAILED)
                 ->where('updated_at >=', $since)
                 ->order_by('updated_at','DESC')
                 ->limit($limit);
        return $this->db->get()->result_array();
    }

    public function quick_search($user_id, $term, $limit = 15)
    {
        $term = trim($term);
        if($term==='') return [];
        $this->baseUserQuery($user_id);
        $this->db->group_start()
                 ->like('file_name',$term)
                 ->or_like('description',$term)
                 ->or_like('media_id',$term)
                 ->group_end();
        $this->db->order_by('id','DESC')->limit($limit);
        return $this->db->get()->result_array();
    }

    /* =================== الجدولة والتكرار =================== */

    /**
     * إنشاء دفعة سجلات مجدولة (واحد لكل حساب).
     * perAccountBase يمكن أن يحتوي:
     *  media_kind, file_type, file_name, file_path, description,
     *  comments_count, comments_json,
     *  original_local_time, original_offset_minutes, original_timezone, schedule_index (لو سبق ضبطه)
     */
    public function create_scheduled_batch(
        int $user_id,
        array $perAccountBase,
        array $accounts,
        string $scheduled_time,
        string $recurrence_kind = self::REC_NONE,
        ?string $recurrence_until = null,
        ?int $group_id = null,
        int $schedule_index = 1
    ){
        $ids = [];
        $now = $this->nowUtc();
        foreach($accounts as $ig_user_id){
            $row = $perAccountBase;
            $row['user_id']            = $user_id;
            $row['ig_user_id']         = $ig_user_id;
            $row['publish_mode']       = 'scheduled';
            $row['scheduled_time']     = $scheduled_time;     // UTC جاهز
            $row['recurrence_kind']    = $recurrence_kind;
            $row['recurrence_until']   = $recurrence_until;
            $row['status']             = self::STATUS_SCHEDULED; // تثبيت الحالة
            $row['scheduled_group_id'] = $group_id;
            $row['attempt_count']      = 0;
            $row['last_attempt_at']    = null;
            $row['schedule_index']     = $schedule_index;
            $row['created_at']         = $now;
            $row['updated_at']         = $now;
            $this->db->insert($this->table,$row);
            $ids[] = (int)$this->db->insert_id();
        }
        if($recurrence_kind !== self::REC_NONE && $ids){
            $this->db->where_in('id',$ids)->update($this->table,[
                'recurrence_parent_id'=>$ids[0],
                'updated_at'=>$this->nowUtc()
            ]);
        }
        return $ids;
    }

    /**
     * جلب السجلات المستحقة للنشر (scheduled فقط افتراضياً).
     * يمكن تمرير $includePending=true لالتقاط pending أيضاً (توافق خلفي في حالة تغيير الحالة مستقبلاً).
     */
    public function fetch_due_scheduled(int $limit = 10, int $maxAttempts = 3, bool $includePending = false){
        $statusList = $includePending ? [self::STATUS_SCHEDULED,self::STATUS_PENDING] : [self::STATUS_SCHEDULED];
        $in = implode("','", array_map('addslashes',$statusList));
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE status IN ('$in')
              AND scheduled_time IS NOT NULL
              AND scheduled_time <= UTC_TIMESTAMP()
              AND (attempt_count < ? OR attempt_count = 0 OR attempt_count IS NULL)
            ORDER BY scheduled_time ASC, id ASC
            LIMIT ?
        ";
        return $this->db->query($sql,[$maxAttempts,$limit])->result_array();
    }

    /**
     * تحويل الحالة إلى publishing + attempt_count++ (أمان ضد NULL)
     */
    public function mark_publishing($id){
        $now = $this->nowUtc();
        $this->db->where('id',$id)
                 ->set('status', self::STATUS_PUBLISHING)
                 ->set('attempt_count','COALESCE(attempt_count,0)+1',false)
                 ->set('last_attempt_at', $now)
                 ->set('updated_at', $now)
                 ->update($this->table);
        return $this->db->affected_rows();
    }

    /**
     * إعادة جدولة لمحاولة لاحقة
     */
    public function reschedule_for_retry($id, $delayMinutes = 2){
        $this->db->where('id',$id)->update($this->table,[
            'status'=>self::STATUS_SCHEDULED,
            'scheduled_time'=>gmdate('Y-m-d H:i:s', time()+$delayMinutes*60),
            'updated_at'=>$this->nowUtc()
        ]);
        return $this->db->affected_rows();
    }

    /**
     * حساب التوقيت القادم للتكرار
     */
    public function calculate_next_time($currentTime, $recurrence_kind){
        $ts = strtotime($currentTime.' UTC');
        if(!$ts) return null;
        switch($recurrence_kind){
            case self::REC_DAILY:     return gmdate('Y-m-d H:i:s', strtotime('+1 day', $ts));
            case self::REC_WEEKLY:    return gmdate('Y-m-d H:i:s', strtotime('+1 week', $ts));
            case self::REC_MONTHLY:   return gmdate('Y-m-d H:i:s', strtotime('+1 month', $ts));
            case self::REC_QUARTERLY: return gmdate('Y-m-d H:i:s', strtotime('+3 month', $ts));
            default: return null;
        }
    }

    /**
     * استنساخ تكرار جديد
     */
    public function clone_next_recurrence(array $row, string $nextTime){
        if(empty($row['recurrence_kind']) || $row['recurrence_kind']==self::REC_NONE) return 0;
        if(!empty($row['recurrence_until']) && $nextTime > $row['recurrence_until']) return 0;

        $data = $row;
        unset(
            $data['id'],$data['media_id'],$data['creation_id'],$data['published_at'],
            $data['last_error'],$data['first_comment_id'],$data['comments_publish_result_json']
        );
        $now = $this->nowUtc();
        $data['status']              = self::STATUS_SCHEDULED;
        $data['scheduled_time']      = $nextTime;
        $data['attempt_count']       = 0;
        $data['last_attempt_at']     = null;
        $data['media_id']            = null;
        $data['creation_id']         = null;
        $data['updated_at']          = $now;
        $data['created_at']          = $now;
        $data['publish_mode']        = 'scheduled';
        $data['recurrence_parent_id']= $row['recurrence_parent_id'] ?: $row['id'];
        $this->db->insert($this->table,$data);
        return (int)$this->db->insert_id();
    }
}