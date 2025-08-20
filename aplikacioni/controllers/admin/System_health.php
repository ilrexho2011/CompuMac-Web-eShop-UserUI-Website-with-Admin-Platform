<?php

defined('BASEPATH') or exit('No direct script access allowed');

class System_health extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function index()
	{
		if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
			$this->data['main_page'] = VIEW . 'system-health';
			$settings = get_settings('system_settings', true);
			$this->data['title'] = 'System Health | ' . $settings['app_name'];
			$this->data['meta_description'] = ' System Health | ' . $settings['app_name'];
			$this->data['system_health'] = get_settings('system_health', true);
			$this->load->view('admin/template', $this->data);
		} else {
			redirect('admin/login', 'refresh');
		}
	}
}