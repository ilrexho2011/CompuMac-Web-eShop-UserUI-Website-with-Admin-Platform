<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Attributes extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper']);
        $this->load->model('attribute_model');
        if (!has_permissions('read', 'attribute')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        }
    }

    public function index()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = FORMS . 'attribute';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Add Attributes | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Add Attributes | ' . $settings['app_name'];
            if (isset($_GET['edit_id'])) {
                $this->data['fetched_data'] = fetch_details('attributes', ['id' => $_GET['edit_id']]);
            }
            $this->data['attribute_set'] = fetch_details('attribute_set',  ['status' => 1]);

            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function manage_attribute()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = TABLES . 'manage-attribute';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Manage Attribute | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Manage Attribute  | ' . $settings['app_name'];
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function add_attributes()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $edit_attribute = $this->input->post('edit_attribute', true);
            if (null !== $edit_attribute) {
                if (print_msg(!has_permissions('update', 'attribute'), PERMISSION_ERROR_MSG, 'attribute')) {
                    return false;
                }
            } else {
                if (print_msg(!has_permissions('create', 'attribute'), PERMISSION_ERROR_MSG, 'attribute')) {
                    return false;
                }
            }

            $this->form_validation->set_rules('name', 'Name', 'trim|required|xss_clean');
            $this->form_validation->set_rules('attribute_set', 'Attribute set', 'trim|required|xss_clean');
            $this->form_validation->set_rules('attribute_value[]', 'Attribute Value', 'trim|required|xss_clean');
            $this->form_validation->set_rules('swatche_value[]', 'Attribute Image', 'trim|required|xss_clean');
            $swatche_type = $this->input->post('swatche_type', true);

            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {
                $this->attribute_model->add_attributes($_POST);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $message = (isset($_POST['edit_attribute'])) ? 'Attribute Updated Successfully' : 'Attribute Added Successfully';
                $this->response['message'] = $message;
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function attribute_list()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            return $this->attribute_model->get_attribute_list();
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function add_attribute_values()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $edit_attribute = $this->input->post('edit_attribute', true);
            if (null !== $edit_attribute) {
                if (print_msg(!has_permissions('update', 'attribute'), PERMISSION_ERROR_MSG, 'attribute')) {
                    return false;
                }
            } else {
                if (print_msg(!has_permissions('create', 'attribute'), PERMISSION_ERROR_MSG, 'attribute')) {
                    return false;
                }
            }

            $this->form_validation->set_rules('name', 'Name', 'trim|required|xss_clean');
            $this->form_validation->set_rules('attribute_set', 'Attribute set', 'trim|required|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {
                if (isset($_POST['edit_attribute'])) {

                    if (is_exist(['name' => $this->input->post('name', true), 'attribute_set_id' => $this->input->post('attribute_set', true)], 'attributes', $this->input->post('edit_attribute', true))) {
                        $response["error"]   = true;
                        $response['csrfName'] = $this->security->get_csrf_token_name();
                        $response['csrfHash'] = $this->security->get_csrf_hash();
                        $response["message"] = "This Combination Already Exist. Provide a new combination";
                        $response["data"] = array();
                        echo json_encode($response);
                        return false;
                    }
                } else {
                    if (is_exist(['name' => $this->input->post('name', true), 'attribute_set_id' => $this->input->post('attribute_set', true)], 'attributes')) {
                        $response["error"]   = true;
                        $response['csrfName'] = $this->security->get_csrf_token_name();
                        $response['csrfHash'] = $this->security->get_csrf_hash();
                        $response["message"] = "This Combination Already Exist. Provide a new combination";
                        $response["data"] = array();
                        echo json_encode($response);
                        return false;
                    }
                }
                $data =  array(
                    'edit_attribute_value' => $this->input->post('edit_attribute_value', true),
                    'attributes_id' => $this->input->post('attributes_id', true),
                    'swatche_value' => $this->input->post('swatche_value', true),
                    'swatche_type' => $this->input->post('swatche_type', true),
                    'value' => $this->input->post('value', true),
                );

                $this->attribute_model->add_attribute_value($data);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $message = (null !== $this->input->post('edit_attribute', true)) ? 'Attribute Updated Successfully' : 'Attribute Added Successfully';
                $this->response['message'] = $message;
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }
}
