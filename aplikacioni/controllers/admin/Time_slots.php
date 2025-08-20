<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Time_slots extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper']);
        $this->load->model('Setting_model');
        if (!has_permissions('read', 'time_slot_settings')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        }
    }

    public function index()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = FORMS . 'time-slots';
            $settings = get_settings('system_settings', true);
            $this->data['time_slot_config'] = get_settings('time_slot_config', true);
            $this->data['title'] = 'Time slots | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Time slots | ' . $settings['app_name'];
            if (isset($_GET['edit_id'])) {
                $featured_data = fetch_details('time_slots', ['id' => $_GET['edit_id']]);
                $this->data['fetched_data'] = $featured_data;
            }
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function view_time_slots()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            return $this->Setting_model->get_time_slot_details();
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function delete_time_slots()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('delete', 'time_slot_settings'), PERMISSION_ERROR_MSG, 'time_slot_settings')) {
                return false;
            }
            if (delete_details(['id' => $_GET['id']], 'time_slots') == TRUE) {
                $this->response['error'] = false;
                $this->response['message'] = 'Deleted Successfully';
                print_r(json_encode($this->response));
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Something Went Wrong';
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function update_time_slots()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('update', 'time_slot_settings'), PERMISSION_ERROR_MSG, 'time_slot_settings')) {
                return false;
            }

            $this->form_validation->set_rules('title', 'Title', 'trim|required|xss_clean');
            $this->form_validation->set_rules('from_time', 'From Time', 'trim|required|xss_clean');
            $this->form_validation->set_rules('to_time', 'To TIme', 'trim|required|xss_clean');
            $this->form_validation->set_rules('last_order_time', 'Last Order Time', 'trim|required|xss_clean');
            $this->form_validation->set_rules('status', 'Status', 'trim|required|xss_clean');

            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {

                $data = array(
                    'title' => $this->input->post('title', true),
                    'from_time' => $this->input->post('from_time', true),
                    'to_time' => $this->input->post('to_time', true),
                    'last_order_time' => $this->input->post('last_order_time', true),
                    'status' => $this->input->post('status', true),
                );

                $this->Setting_model->update_time_slot($data);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $message = (null !== $this->input->post('edit_time_slot', true)) ? 'Time slot Updated Successfully' : 'Time slot Added Successfully';
                $this->response['message'] = $message;
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function update_time_slots_config()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            if (print_msg(!has_permissions('update', 'time_slot_settings'), PERMISSION_ERROR_MSG, 'time_slot_settings')) {
                return false;
            }
            $this->form_validation->set_rules('time_slot_config', 'Time Slot Config ', 'trim|required|numeric|xss_clean');
            $this->form_validation->set_rules('is_time_slots_enabled', 'Time Slot ', 'trim|xss_clean');
            $this->form_validation->set_rules('delivery_starts_from', 'Delivery Starts From', 'trim|required|numeric|xss_clean');
            $this->form_validation->set_rules('allowed_days', 'Days you want to allow ', 'trim|required|numeric|xss_clean');

            if (!$this->form_validation->run()) {

                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {

                $data = array(
                    'time_slot_config' => $this->input->post('time_slot_config', true),
                    'is_time_slots_enabled' => $this->input->post('is_time_slots_enabled', true),
                    'delivery_starts_from' => $this->input->post('delivery_starts_from', true),
                    'allowed_days' => $this->input->post('allowed_days', true),
                );

                $this->Setting_model->update_time_slot_config($data);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = 'Time Slot Config Updated Successfully';
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }
}
