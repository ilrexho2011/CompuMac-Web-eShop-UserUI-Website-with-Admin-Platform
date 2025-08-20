<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Purchase_code extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper']);
        $this->load->model('Setting_model');

        if (!has_permissions('read', 'contact_us')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        }
    }

    public function index()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = FORMS . 'purchase-code';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'System Regsitration | Purchase Code Validation | ' . $settings['app_name'];
            $this->data['meta_description'] = 'System Regsitration | Purchase Code Validation |  | ' . $settings['app_name'];
            $this->data['doctor_brown'] = get_settings('doctor_brown');
            $this->data['admin_app_doctor_brown'] = get_settings('admin_app_doctor_brown');

            $web_purchase_code = get_settings('web_doctor_brown', true);
            $web_purchase_code = isset($web_purchase_code['code_bravo']) ? $web_purchase_code['code_bravo'] : "";
            $this->data['web_doctor_brown'] =  $web_purchase_code;

            $app_purchase_code = get_settings('doctor_brown', true);
            $app_purchase_code = isset($app_purchase_code['code_bravo']) ? $app_purchase_code['code_bravo'] : "";
            $this->data['doctor_brown'] =  $app_purchase_code;

            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function validator()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $web_purchase_code = $this->input->post('web_purchase_code', true);
            $app_purchase_code = $this->input->post('app_purchase_code', true);
            if (isset($app_purchase_code) && !empty($app_purchase_code)) {
                $purchase_code = $this->input->post("app_purchase_code", true);
                $url = "https://wrteam.in/validator/home/validator_new?purchase_code=$purchase_code&domain_url=" . base_url() . "&item_id=" . APP_CODE;
                $result = curl($url);
                if (isset($result['body']) && !empty($result['body'])) {
                    if (isset($result['body']['error']) && $result['body']['error'] == 0) {

                        $doctor_brown = get_settings('doctor_brown');
                        if (empty($doctor_brown)) {
                            $doctor_brown['code_bravo'] = $result["body"]["purchase_code"];
                            $doctor_brown['time_check'] = $result["body"]["token"];
                            $doctor_brown['code_adam'] = $result["body"]["username"];
                            $doctor_brown['dr_firestone'] = $result["body"]["item_id"];

                            $data['variable'] = "doctor_brown";
                            $data['value'] = json_encode($doctor_brown);
                            insert_details($data, 'settings');
                        }
                        $this->response['error'] = false;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['message'] = $result['body']['message'];
                        print_r(json_encode($this->response));
                    } else {
                        $this->response['error'] = true;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['message'] = $result['body']['message'];
                        print_r(json_encode($this->response));
                    }
                }
            } elseif (isset($web_purchase_code) && !empty($web_purchase_code)) {
                $purchase_code = $this->input->post("web_purchase_code", true);
                $url = "https://wrteam.in/validator/home/validator_new?purchase_code=$purchase_code&domain_url=" . base_url() . "&item_id=" . WEB_CODE;
                
                $result = curl($url);
                if (isset($result['body']) && !empty($result['body'])) {
                    if (isset($result['body']['error']) && $result['body']['error'] == 0) {
                        $doctor_brown = get_settings('web_doctor_brown');
                        if (empty($doctor_brown)) {
                            $doctor_brown['code_bravo'] = $result["body"]["purchase_code"];
                            $doctor_brown['time_check'] = $result["body"]["token"];
                            $doctor_brown['code_adam'] = $result["body"]["username"];
                            $doctor_brown['dr_firestone'] = $result["body"]["item_id"];
                            $data['variable'] = "web_doctor_brown";
                            $data['value'] = json_encode($doctor_brown);
                            insert_details($data, 'settings');
                        }
                        $this->response['error'] = false;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['message'] = $result['body']['message'];
                        print_r(json_encode($this->response));
                    } else {
                        $this->response['error'] = true;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['message'] = $result['body']['message'];
                        print_r(json_encode($this->response));
                    }
                }
            } else {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = "Somthing Went wrong. Please contact Super admin.";
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    // De-Register WEB 
    public function de_register_web()
    {

        $this->form_validation->set_rules('purchase_code', 'Purchase Code', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['message'] = array(
                'purchase_code' => form_error('purchase_code'),
            );
            print_r(json_encode($this->response));
        } else {
            $purchasecode = $this->input->post("purchase_code", true);
            $deregister_data = get_settings('web_doctor_brown');
            $purchasecode_data = isset($deregister_data) ? json_decode($deregister_data, true) : "";
            $purchasecode_data['domain_url'] = base_url();
            if (!empty($purchasecode_data)) {
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = $purchasecode_data;
                print_r(json_encode($this->response));
            } else {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = "Web De-Register has been failed !";
                print_r(json_encode($this->response));
            }
        }
    }

    public function delete_web_purchasecode()
    {
        $this->form_validation->set_rules('de_register_code', 'De-register Code', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['message'] = array(
                'de_register_code' => form_error('de_register_code'),
            );
            print_r(json_encode($this->response));
        } else {
            if (!empty($_POST['de_register_code'])) {
                if (delete_details(['variable' => 'web_doctor_brown'], 'settings')) {
                    $this->response['error'] = false;
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['message'] = "Web Purchase code de-registerd successfully!";
                    print_r(json_encode($this->response));
                } else {
                    $this->response['error'] = true;
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['message'] = "Purchase code de-register failed!";
                    print_r(json_encode($this->response));
                }
            } else {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = "Something went wrong!";
                print_r(json_encode($this->response));
            }
        }
    }

    // De-Register APP
    public function de_register_app()
    {

        $this->form_validation->set_rules('purchase_code', 'Purchase Code', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['message'] = array(
                'purchase_code' => form_error('purchase_code'),
            );
            print_r(json_encode($this->response));
        } else {
            $purchasecode = $this->input->post("purchase_code", true);
            $deregister_data = get_settings('doctor_brown');
            $purchasecode_data = isset($deregister_data) ? json_decode($deregister_data, true) : "";
            $purchasecode_data['domain_url'] = base_url();
            if (!empty($purchasecode_data)) {
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = $purchasecode_data;
                print_r(json_encode($this->response));
            } else {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = "App De-Register has been failed !";
                print_r(json_encode($this->response));
            }
        }
    }

    public function delete_app_purchase_code()
    {
        $this->form_validation->set_rules('de_register_code', 'De-register Code', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['message'] = array(
                'de_register_code' => form_error('de_register_code'),
            );
            print_r(json_encode($this->response));
        } else {
            if (!empty($_POST['de_register_code'])) {
                if (delete_details(['variable' => 'doctor_brown'], 'settings')) {
                    $this->response['error'] = false;
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['message'] = "App Purchase code de-registerd successfully!";
                    print_r(json_encode($this->response));
                } else {
                    $this->response['error'] = true;
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['message'] = "Purchase code de-register failed!";
                    print_r(json_encode($this->response));
                }
            } else {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = "Something went wrong!";
                print_r(json_encode($this->response));
            }
        }
    }
}
