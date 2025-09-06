<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Instagram_accounts_platform_model
 * يعتمد حصراً على جدول المنصّة facebook_rx_fb_page_info
 * ويُرجع مفاتيح متوافقة مع الواجهات الحالية:
 *  - ig_user_id    => instagram_business_account_id (جديد وصحيح)
 *  - ig_username   => insta_username               (جديد وصحيح)
 *  - ig_profile_picture => ig_profile_picture      (صحيح)
 *  - page_name     => page_name أو username كـ fallback
 *  - page_access_token => page_access_token (تمريره لو احتاجه الناشر)
 *
 * فلترة افتراضية: has_instagram='1' و instagram_business_account_id <> ''
 * ويمكن تفعيل requireLinked لفلترة ig_linked=1 لو عايز المربوط فعلاً فقط.
 *
 * لا يكتب أي شيء في قاعدة البيانات؛ قراءة فقط.
 */
class Instagram_accounts_platform_model extends CI_Model
{
    protected $table = 'facebook_rx_fb_page_info';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * @param int  $user_id
     * @param bool $requireLinked إذا true: يفلتر ig_linked=1
     * @return array<array{
     *   ig_user_id:string, ig_username:string, ig_profile_picture:string,
     *   page_name:string, page_access_token:string,
     *   _rx_row_id:int, _fb_page_id:string, _ig_linked:int
     * }>
     */
    public function get_instagram_accounts_by_user($user_id, $requireLinked = true)
    {
        if (!$this->db->table_exists($this->table)) {
            return [];
        }

        $this->db->select(
            "
            id,
            user_id,
            page_id,
            COALESCE(page_name, username, page_id) AS page_name,
            page_access_token,
            instagram_business_account_id,
            insta_username,
            ig_profile_picture,
            has_instagram,
            ig_linked
        ", false);
        $this->db->from($this->table);
        $this->db->where('user_id', (int)$user_id);
        $this->db->where('has_instagram', '1');
        $this->db->where("COALESCE(instagram_business_account_id,'') <> '', false);
        if ($requireLinked) {
            $this->db->where('ig_linked', 1);
        }
        $this->db->order_by('COALESCE(page_name, username, page_id)', 'ASC', false);

        $rows = $this->db->get()->result_array();
        $out  = [];

        foreach ($rows as $r) {
            $out[] = [
                'ig_user_id'         => (string)$r['instagram_business_account_id'],
                'ig_username'        => (string)$r['insta_username'],
                'ig_profile_picture' => (string)($r['ig_profile_picture'] ?: ''),
                'page_name'          => (string)$r['page_name'],
                'page_access_token'  => (string)$r['page_access_token'],
                '_rx_row_id'         => (int)$r['id'],
                '_fb_page_id'        => (string)$r['page_id'],
                '_ig_linked'         => (int)$r['ig_linked'],
            ];
        }

        return $out;
    }
}