<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pickup_location extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper', 'file']);
        $this->load->model('Pickup_location_model');
        if (!has_permissions('read', 'pickup_location')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        } else {
            $this->session->set_flashdata('authorize_flag', "");
        }
    }

    public function manage_pickup_locations()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            if (!has_permissions('read', 'pickup_location')) {
                $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                redirect('admin/home', 'refresh');
            }
            $this->data['main_page'] = TABLES . 'manage-pickup_location';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Pickup location Management | ' . $settings['app_name'];
            $this->data['meta_description'] = ' Pickup location Management  | ' . $settings['app_name'];
            if (isset($_GET['edit_id'])) {
                $this->data['fetched_data'] = fetch_details('pickup_locations', ['id' => $_GET['edit_id']]);
            }
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function add_pickup_location()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            $pickup_location = $this->input->post('edit_pickup_location', true);
            if (isset($pickup_location)) {
                if (print_msg(!has_permissions('update', 'pickup_location'), PERMISSION_ERROR_MSG, 'pickup_location')) {
                    return false;
                }
            } else {
                if (print_msg(!has_permissions('create', 'pickup_location'), PERMISSION_ERROR_MSG, 'pickup_location')) {
                    return false;
                }
            }

            $this->form_validation->set_rules('pickup_location', ' Pickup Location ', 'trim|required|xss_clean');
            $this->form_validation->set_rules('name', ' Name ', 'trim|required|xss_clean');
            $this->form_validation->set_rules('email', ' Email ', 'trim|required|valid_email|xss_clean');
            $this->form_validation->set_rules('phone', ' Phone ', 'trim|required|numeric|xss_clean');
            $this->form_validation->set_rules('city', ' City ', 'trim|required|xss_clean');
            $this->form_validation->set_rules('state', ' State ', 'trim|required|xss_clean');
            $this->form_validation->set_rules('country', ' Country ', 'trim|required|xss_clean');
            $this->form_validation->set_rules('pincode', ' Pincode ', 'trim|numeric|required|xss_clean');
            $this->form_validation->set_rules('address', ' Address ', 'trim|required|xss_clean');
            $this->form_validation->set_rules('address2', ' Address 2 ', 'trim|xss_clean');
            $this->form_validation->set_rules('latitude', ' Latitude ', 'trim|xss_clean');
            $this->form_validation->set_rules('longitude', ' Longitude ', 'trim|xss_clean');


            if (!$this->form_validation->run()) {

                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {

                $data = array(
                    'pickup_location' => $this->input->post('pickup_location', true),
                    'name' => $this->input->post('name', true),
                    'email' => $this->input->post('email', true),
                    'phone' => $this->input->post('phone', true),
                    'address' => $this->input->post('address', true),
                    'address2' => $this->input->post('address2', true),
                    'city' => $this->input->post('city', true),
                    'state' => $this->input->post('state', true),
                    'country' => $this->input->post('country', true),
                    'pincode' => $this->input->post('pincode', true),
                    'latitude' => $this->input->post('latitude', true),
                    'longitude' => $this->input->post('longitude', true),
                );
                $edit_pickup_location = $this->input->post('edit_pickup_location', true)  ;
                if (! empty($edit_pickup_location)) {
                    $data['edit_pickup_location'] = $this->input->post('edit_pickup_location', true)  ;
                }

                $this->Pickup_location_model->add_pickup_location($data);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $message = (null !== $this->input->post('edit_pickup_location', true)) ? 'Pickup Location Updated Successfully' : 'Pickup Location Added Successfully';
                $this->response['message'] = $message;
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function view_pickup_location()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            return $this->Pickup_location_model->get_list($table = 'pickup_locations');
        } else {
            redirect('admin/login', 'refresh');
        }
    }
}
