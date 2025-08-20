<?php
class Register extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper']);
        $this->load->model(['address_model', 'category_model', 'cart_model', 'faq_model']);
        $this->data['is_logged_in'] = ($this->ion_auth->logged_in()) ? 1 : 0;
        $this->data['user'] = ($this->ion_auth->logged_in()) ? $this->ion_auth->user()->row() : array();
        $this->data['settings'] = get_settings('system_settings', true);
        $this->data['web_settings'] = get_settings('web_settings', true);
        $this->response['csrfName'] = $this->security->get_csrf_token_name();
        $this->response['csrfHash'] = $this->security->get_csrf_hash();
    }
    public function index()
    {
        if ((isset($this->data['settings']['is_web_under_maintenance']) && $this->data['settings']['is_web_under_maintenance'] == 1)) {
            redirect(base_url("maintenance"));
        }
        if (!$this->ion_auth->logged_in() == 1) {
            $this->data['main_page'] = 'register-login';
            $this->data['title'] = 'Home | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Home, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Home | ' . $this->data['web_settings']['meta_description'];
            $this->data['system_settings'] = get_settings('system_settings', true);
            $this->data['web_settings'] = get_settings('web_settings', true);
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }
}
