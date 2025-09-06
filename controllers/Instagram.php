<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Instagram Controller
 * Handles Instagram account management and content publishing.
 * Uses Instagram_accounts_platform_model for account sourcing from platform table.
 */
class Instagram extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Instagram_accounts_platform_model', 'instagram_accounts_model');
        $this->load->library(['session']);
        $this->load->helper(['url', 'form', 'security']);
        $this->load->database();
    }

    /**
     * Check if user is logged in
     */
    private function require_login()
    {
        if (!$this->session->userdata('user_id')) {
            $redir = rawurlencode(current_url());
            redirect('home/login?redirect=' . $redir);
            exit;
        }
    }

    /**
     * Load Instagram accounts for the logged-in user and inject into data array
     * @param array $data Reference to data array to inject accounts into
     * @param bool $requireLinked Whether to require ig_linked=1
     */
    private function loadAccounts(array &$data, bool $requireLinked = true)
    {
        $user_id = $this->session->userdata('user_id');
        if (!$user_id) {
            $data['accounts'] = [];
            return;
        }

        $data['accounts'] = $this->instagram_accounts_model->get_instagram_accounts_by_user($user_id, $requireLinked);
    }

    /**
     * Instagram upload form (classic)
     */
    public function upload()
    {
        $this->require_login();
        
        $data = [];
        $this->loadAccounts($data, true);
        
        $this->load->view('instagram_upload', $data);
    }

    /**
     * Instagram upload form (modern)
     */
    public function upload_modern()
    {
        $this->require_login();
        
        $data = [];
        $this->loadAccounts($data, true);
        
        $this->load->view('instagram_upload_modern', $data);
    }

    /**
     * Instagram content listing
     */
    public function listing()
    {
        $this->require_login();
        
        $data = [];
        $this->loadAccounts($data, true);
        
        // Initialize filter data
        $data['filter'] = $_GET;
        $data['items'] = []; // This would be populated with actual listing data
        $data['pages'] = 1; // This would be populated with pagination data
        
        $this->load->view('instagram_listing', $data);
    }

    /**
     * Instagram listing actions
     */
    public function listing_actions()
    {
        $this->require_login();
        
        $data = [];
        $this->loadAccounts($data, true);
        
        // Initialize filter data
        $data['filter'] = $_GET;
        $data['items'] = []; // This would be populated with actual listing data
        $data['pages'] = 1; // This would be populated with pagination data
        
        $this->load->view('instagram_listing_actions', $data);
    }

    /**
     * Instagram media management
     */
    public function media()
    {
        $this->require_login();
        
        $data = [];
        $this->loadAccounts($data, true);
        
        // Initialize filter data
        $data['filter'] = $_GET;
        $data['items'] = []; // This would be populated with actual media data
        $data['summary'] = ['published' => 0, 'pending' => 0, 'failed' => 0, 'uploading' => 0]; // Summary data
        
        $this->load->view('instagram_media', $data);
    }
}