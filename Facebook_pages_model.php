<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Facebook_pages_model extends CI_Model
{
    protected $table = 'facebook_pages'; // giữ للوظائف التي تتعامل مع المفضلات والإحصاءات القديمة

    /**
     * جلب صفحات الفيسبوك لمستخدم من جدول المنصة facebook_rx_fb_page_info فقط.
     * يرجع قائمة الصفحات مع الحقول المتوقعة: fb_page_id, page_name, page_picture, page_access_token, is_favorite
     *
     * ملاحظة:
     * - is_favorite هنا يتم إحضارها من جدول facebook_pages إن وُجدت (حتى لا نكسر واجهة المفضلة).
     * - لا نقوم بعمل أي كتابة أو تعديل على منطق النشر/الجدولة هنا.
     */
    public function get_pages_by_user($user_id)
    {
        // إذا لم يوجد جدول المنصة نعطي مصفوفة فارغة (الأفضل توضيح السبب بدلاً من الرجوع للقديم)
        if (!$this->db->table_exists('facebook_rx_fb_page_info')) {
            return [];
        }

        // نقرأ فقط من جدول المنصة بالحقول الموجودة في بنيةكم
        $this->db->select("
            rx.page_id AS fb_page_id,
            COALESCE(rx.page_name, rx.username, rx.page_id) AS page_name,
            COALESCE(rx.page_profile, rx.page_cover, '') AS page_picture,
            COALESCE(rx.page_access_token, '') AS page_access_token,
            rx.id AS rx_id
        ", false);
        $this->db->from('facebook_rx_fb_page_info rx');
        $this->db->where('rx.user_id', $user_id);
        $this->db->order_by('COALESCE(rx.page_name, rx.username, rx.page_id)', 'ASC', false);
        $rows = $this->db->get()->result_array();

        // لجلب حالة is_favorite إن وُجدت في جدول facebook_pages نستخدم استعلام واحد لتقليل النداءات
        // نحضر خرائط fb_page_id => is_favorite من جدول facebook_pages لنفس الـ user_id
        $favMap = [];
        if ($this->db->table_exists($this->table)) {
            $favRows = $this->db->select('fb_page_id,is_favorite')
                                ->where('user_id',$user_id)
                                ->get($this->table)
                                ->result_array();
            foreach($favRows as $fr){
                $favMap[(string)$fr['fb_page_id']] = !empty($fr['is_favorite']) ? 1 : 0;
            }
        }

        // تطبيع الحقول للتماشي مع الكود الحالي في الكونترولرز/فيوز
        foreach($rows as $k => $r){
            // ensure keys exist
            if (!isset($rows[$k]['fb_page_id'])) $rows[$k]['fb_page_id'] = $r['page_id'] ?? '';
            if (!isset($rows[$k]['page_name']) || $rows[$k]['page_name'] === null) $rows[$k]['page_name'] = $rows[$k]['fb_page_id'];
            if (!isset($rows[$k]['page_picture']) || $rows[$k]['page_picture'] === null) $rows[$k]['page_picture'] = '';
            if (!isset($rows[$k]['page_access_token']) || $rows[$k]['page_access_token'] === null) $rows[$k]['page_access_token'] = '';
            // is_favorite: ارجع القيمة من الخريطة أو 0
            $rows[$k]['is_favorite'] = $favMap[(string)$rows[$k]['fb_page_id']] ?? 0;
            // حافظ على الأسماء القديمة إن كان الكود يعتمد عليها
            $rows[$k]['fb_page_id'] = (string)$rows[$k]['fb_page_id'];
        }

        return $rows;
    }

    /**
     * تبديل حالة المفضلة
     * - هذه الدالة تعمل على جدول facebook_pages كما كان (نترك سلوك التبديل القديم)
     */
    public function toggle_favorite($user_id, $fb_page_id)
    {
        $row = $this->db->where('user_id',$user_id)
                        ->where('fb_page_id',$fb_page_id)
                        ->get($this->table)->row_array();
        if(!$row) return false;
        $new = $row['is_favorite'] ? 0 : 1;
        $this->db->where('id',$row['id'])->update($this->table,['is_favorite'=>$new]);
        return $new;
    }

    /**
     * مفضلة Bulk
     */
    public function set_favorite_bulk($user_id, $ids, $value=1)
    {
        if(empty($ids)) return 0;
        $this->db->where('user_id',$user_id)
                 ->where_in('fb_page_id',$ids)
                 ->update($this->table,['is_favorite'=>$value?1:0]);
        return $this->db->affected_rows();
    }

    /**
     * حذف ربط صفحات (يبقى كما هو على facebook_pages)
     */
    public function unlink_pages($user_id, $ids)
    {
        if(empty($ids)) return 0;
        $this->db->where('user_id',$user_id)
                 ->where_in('fb_page_id',$ids)
                 ->delete($this->table);
        return $this->db->affected_rows();
    }

    /**
     * Upsert صفحة إلى جدول facebook_pages (للإبقاء على آليات الاشتراك القديمة)
     * هذه الدالة لم تمسّها لتجنب كسر أي منطق تسجيل/ربطسبق.
     */
    public function upsert_page($user_id, $data)
    {
        $row = $this->db->where('user_id',$user_id)
                        ->where('fb_page_id',$data['fb_page_id'])
                        ->get($this->table)->row_array();

        $now = date('Y-m-d H:i:s');
        $base = [
            'page_name'         => $data['page_name'] ?? '',
            'page_picture'      => $data['page_picture'] ?? '',
            'page_access_token' => $data['page_access_token'] ?? '',
            'last_sync_at'      => $now,
        ];

        $igFields = [
            'ig_detected_user_id','ig_user_id','ig_username',
            'ig_profile_name','ig_profile_picture','ig_linked'
        ];
        foreach ($igFields as $f) {
            if (array_key_exists($f, $data)) {
                if ($f === 'ig_user_id' || $f === 'ig_linked') {
                    if ($row && !empty($row['ig_linked']) && empty($data['ig_linked'])) {
                        continue;
                    }
                }
                $base[$f] = $data[$f];
            }
        }

        if($row){
            $this->db->where('id',$row['id'])->update($this->table,$base);
            return $row['id'];
        } else {
            $base['user_id']    = $user_id;
            $base['fb_page_id'] = $data['fb_page_id'];
            $base['created_at'] = $now;
            $this->db->insert($this->table,$base);
            return $this->db->insert_id();
        }
    }

    /**
     * تحديث إحصاءات بعد جدولة (يبقى كما كان على facebook_pages)
     */
    public function update_stats_after_schedule($user_id, $fb_page_id)
    {
        $this->db->set('last_scheduled_at', date('Y-m-d H:i:s'), false)
                 ->set('scheduled_count','scheduled_count+1', false)
                 ->where('user_id',$user_id)
                 ->where('fb_page_id',$fb_page_id)
                 ->update($this->table);
    }

    /**
     * تحديث إحصاءات بعد نشر (كما كان)
     */
    public function update_stats_after_publish($user_id, $fb_page_id)
    {
        $this->db->set('last_posted_at', date('Y-m-d H:i:s'), false)
                 ->where('user_id',$user_id)
                 ->where('fb_page_id',$fb_page_id)
                 ->update($this->table);
    }

    /**
     * جلب المجدول لصفحة معينة (كمساعد للفيو)
     */
    public function get_scheduled_for_page($user_id, $fb_page_id)
    {
        return $this->db->where('user_id',$user_id)
                        ->where('fb_page_id',$fb_page_id)
                        ->order_by('scheduled_time','ASC')
                        ->get('scheduled_reels')->result_array();
    }

    /* ======== دوال إنستجرام المساعدة (كما كانت) ======== */

    public function set_instagram_linked($user_id, $fb_page_id, $igUserId, $meta = [])
    {
        $update = [
            'ig_user_id'    => $igUserId,
            'ig_linked'     => 1,
            'ig_last_sync_at'=> date('Y-m-d H:i:s')
        ];
        if (!empty($meta['username']))       $update['ig_username']       = $meta['username'];
        if (!empty($meta['profile_name']))   $update['ig_profile_name']   = $meta['profile_name'];
        if (!empty($meta['profile_picture']))$update['ig_profile_picture']= $meta['profile_picture'];

        $this->db->where('user_id',$user_id)->where('fb_page_id',$fb_page_id)->update($this->table,$update);
        return $this->db->affected_rows();
    }

    public function unlink_instagram($user_id, $fb_page_id)
    {
        $this->db->where('user_id',$user_id)
                 ->where('fb_page_id',$fb_page_id)
                 ->update($this->table,[
                    'ig_user_id' => null,
                    'ig_linked'  => 0
                 ]);
        return $this->db->affected_rows();
    }

    public function update_instagram_meta($user_id, $ig_user_id, $meta = [])
    {
        if(!$ig_user_id) return 0;
        $update = ['ig_last_sync_at'=>date('Y-m-d H:i:s')];
        foreach (['username'=>'ig_username','profile_name'=>'ig_profile_name','profile_picture'=>'ig_profile_picture'] as $in=>$col) {
            if(isset($meta[$in])) $update[$col] = $meta[$in];
        }
        $this->db->where('user_id',$user_id)->where('ig_user_id',$ig_user_id)->update($this->table,$update);
        return $this->db->affected_rows();
    }

    public function mark_instagram_health($user_id, $ig_user_id, $status)
    {
        $allowed = ['ok','expiring','expired','error','revoked'];
        if(!in_array($status,$allowed)) $status='error';
        $this->db->where('user_id',$user_id)
                 ->where('ig_user_id',$ig_user_id)
                 ->update($this->table,['ig_health_status'=>$status]);
        return $this->db->affected_rows();
    }
}