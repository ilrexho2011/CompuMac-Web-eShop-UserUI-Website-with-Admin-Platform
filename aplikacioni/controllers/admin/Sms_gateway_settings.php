<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Sms_gateway_settings extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper', 'sms_helper']);
        $this->load->model(['Setting_model', 'notification_model', 'category_model','custom_sms_model']);
        if (!has_permissions('read', 'sms_gateway_settings')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        }
    }

    public function index()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (!has_permissions('read', 'sms-gateway-settings')) {
                $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                redirect('admin/home', 'refresh');
            }
            $this->data['main_page'] = FORMS . 'sms-gateway-settings';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'SMS Gateway Settings | ' . $settings['app_name'];
            $this->data['meta_description'] = ' SMS Gateway Settings  | ' . $settings['app_name'];
            $this->data['sms_gateway_settings'] = get_settings('sms_gateway_settings', true);
            $this->data['send_notification_settings'] = get_settings('send_notification_settings', true);
            $this->data['notification_modules'] = $this->config->item('notification_modules');
            if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
                $this->data['fetched_data'] = fetch_details('custom_sms', ['id' => $_GET['edit_id']]);
            }
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function add_sms_data()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (!has_permissions('read', 'sms-gateway-settings')) {
                $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                redirect('admin/home', 'refresh');
            }
            if (print_msg(!has_permissions('update', 'sms-gateway-settings'), PERMISSION_ERROR_MSG, 'sms-gateway-settings')) {
                return false;
            }

            $this->Setting_model->update_smsgateway($_POST);
            $this->response['error'] = false;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['message'] = 'System Setting Updated Successfully';
            print_r(json_encode($this->response));

        }
    }

    public function update_notification_module()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (!has_permissions('read', 'sms-gateway-settings')) {
                $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                redirect('admin/home', 'refresh');
            }

            $this->Setting_model->update_notification_setting($_POST);
            $this->response['error'] = false;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['message'] = (isset($edit_id)) ? ' Data Updated Successfully' : 'Data Added Successfully';

            print_r(json_encode($this->response));
        } else {
            redirect('admin/login', 'refresh');
        }
    }

}
