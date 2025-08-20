<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Privacy_policy extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper']);
        $this->load->model('Setting_model');
    }

    public function index()
    {
        if (!has_permissions('read', 'privacy_policy')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        }
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = FORMS . 'privacy-policy';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Privacy Policy | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Privacy Policy | ' . $settings['app_name'];
            $this->data['privacy_policy'] = get_settings('privacy_policy');
            $this->data['terms_n_condition'] = get_settings('terms_conditions');
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }


    public function update_privacy_policy_settings()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('update', 'privacy_policy'), PERMISSION_ERROR_MSG, 'privacy_policy')) {
                return false;
            }

            $this->form_validation->set_rules('terms_n_conditions_input_description', 'Terms and Condition Description', 'trim|required|xss_clean');
            $this->form_validation->set_rules('privacy_policy_input_description', 'Privay Policy Description', 'trim|required|xss_clean');


            if (!$this->form_validation->run()) {

                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {

                $data = array(
                    'privacy_policy' => $this->input->post('privacy_policy', true),
                    'privacy_policy_input_description' => $this->input->post('privacy_policy_input_description', true),
                );
                $this->Setting_model->update_privacy_policy($data);

                $terms_condition_data = array(
                    'terms_conditions' => $this->input->post('terms_conditions', true),
                    'terms_n_conditions_input_description' => $this->input->post('terms_n_conditions_input_description', true),
                );
                $this->Setting_model->update_terms_n_condtions($terms_condition_data);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = 'System Setting Updated Successfully';
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function privacy_policy_page()
    {
        $settings = get_settings('system_settings', true);
        $this->data['title'] = 'Privacy Policy | ' . $settings['app_name'];
        $this->data['meta_description'] = 'Privacy Policy | ' . $settings['app_name'];
        $this->data['privacy_policy'] = get_settings('privacy_policy');
        $this->load->view('admin/pages/view/privacy-policy', $this->data);
    }

    public function terms_and_conditions_page()
    {
        $settings = get_settings('system_settings', true);
        $this->data['title'] = 'Terms & Conditions | ' . $settings['app_name'];
        $this->data['meta_description'] = 'Terms & Conditions | ' . $settings['app_name'];
        $this->data['terms_and_conditions'] = get_settings('terms_conditions');
        $this->load->view('admin/pages/view/terms-and-conditions', $this->data);
    }

    public function shipping_policy()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = FORMS . 'shipping-policy';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Shipping Policy | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Privacy Policy | ' . $settings['app_name'];
            $this->data['shipping_policy'] = get_settings('shipping_policy');
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }


    public function update_shipping_policy_settings()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('update', 'shipping_policy'), PERMISSION_ERROR_MSG, 'shipping_policy')) {
                return false;
            }

            $this->form_validation->set_rules('shipping_policy_input_description', 'Shiping Description', 'trim|required|xss_clean');


            if (!$this->form_validation->run()) {

                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {
                $data = array(
                    'shipping_policy' => $this->input->post('shipping_policy', true),
                    'shipping_policy_input_description' => $this->input->post('shipping_policy_input_description', true),
                );

                $this->Setting_model->update_shipping_policy($data);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = 'System Setting Updated Successfully';
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function shipping_policy_page()
    {
        $settings = get_settings('system_settings', true);
        $this->data['title'] = 'Shipping Policy | ' . $settings['app_name'];
        $this->data['meta_description'] = 'Privacy Policy | ' . $settings['app_name'];
        $this->data['shipping_policy'] = get_settings('shipping_policy');
        $this->load->view('admin/pages/view/shipping-policy', $this->data);
    }

    public function return_policy()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
           

            $this->data['main_page'] = FORMS . 'return-policy';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'return Policy | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Privacy Policy | ' . $settings['app_name'];
            $this->data['return_policy'] = get_settings('return_policy');
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function update_return_policy_settings()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('update', 'return_policy'), PERMISSION_ERROR_MSG, 'return_policy')) {
                return false;
            }

            $this->form_validation->set_rules('return_policy_input_description', 'return Description', 'trim|required|xss_clean');


            if (!$this->form_validation->run()) {

                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {

                $data = array(
                    'return_policy' => $this->input->post('return_policy', true),
                    'return_policy_input_description' => $this->input->post('return_policy_input_description', true),
                );
                $this->Setting_model->update_return_policy($data);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = 'System Setting Updated Successfully';
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function return_policy_page()
    {
        $settings = get_settings('system_settings', true);
        $this->data['title'] = 'Return Policy | ' . $settings['app_name'];
        $this->data['meta_description'] = 'Return Policy | ' . $settings['app_name'];
        $this->data['return_policy'] = get_settings('return_policy');
        $this->load->view('admin/pages/view/return-policy', $this->data);
    }
}
