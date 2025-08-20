<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Return_reasons extends CI_Controller
{


	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library(['ion_auth', 'form_validation', 'upload']);
		$this->load->helper(['url', 'language', 'file']);
		$this->load->model(['return_reason_model', 'delivery_boy_model']);

		if (!has_permissions('read', 'return_request')) {
			$this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
			redirect('admin/home', 'refresh');
		}
	}

	public function index()
	{
        
		if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
			$this->data['main_page'] = TABLES . 'return-reasons';
			$settings = get_settings('system_settings', true);
			$this->data['title'] = 'Return Reasons | ' . $settings['app_name'];
			$this->data['meta_description'] = ' Return Reasons  | ' . $settings['app_name'];
			$this->load->view('admin/template', $this->data);
		} else {
			redirect('admin/login', 'refresh');
		}
	}

	public function manage_return_reason()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = TABLES . 'return-reasons';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Return Reasons Management | ' . $settings['app_name'];
            $this->data['meta_description'] = ' Return Reasons Management  | ' . $settings['app_name'];
            if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
                $this->data['fetched_data'] = fetch_details('return_reasons', ['id' => $_GET['edit_id']]);
                $this->data['csrfName'] = $this->security->get_csrf_token_name();
                $this->data['csrfHash'] = $this->security->get_csrf_hash();
                echo json_encode($this->data);
                return;
            }
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

	public function view_return_reason()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            return $this->return_reason_model->get_return_reason_list();
        } else {
            redirect('admin/login', 'refresh');
        }
    }
	public function delete_return_reason()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (!has_permissions('delete', 'return_request')) {
                $response['error'] = true;
                $response['message'] = 'You do not have permission to delete return reasons';
                print_r(json_encode($response));
                return false;
            }
            if (delete_details(['id' => $_GET['id']], 'return_reasons') == TRUE) {
                $response['error'] = false;
                $response['message'] = 'Deleted Succesfully';
                print_r(json_encode($response));
            } else {
                $response['error'] = true;
                $response['message'] = 'Something Went Wrong';
                print_r(json_encode($response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }


	public function add_return_reasons()
	{
		if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
			if (!has_permissions('create', 'return_request')) {
				$response["error"]   = true;
				$response['csrfName'] = $this->security->get_csrf_token_name();
				$response['csrfHash'] = $this->security->get_csrf_hash();
				$response["message"] = "You don't have permission to create / update return reason !";
				$response["data"] = array();
				echo json_encode($response);
				return false;
			}
			$this->form_validation->set_rules('return_reason', 'Reason', 'trim|required|xss_clean');
			$this->form_validation->set_rules('image', 'Image ', 'required|xss_clean');

			if (!$this->form_validation->run()) {

				$this->response['error'] = true;
				$this->response['csrfName'] = $this->security->get_csrf_token_name();
				$this->response['csrfHash'] = $this->security->get_csrf_hash();
				$this->response['message'] = validation_errors();
				print_r(json_encode($this->response));
			} else {
				$reason = $this->input->post('return_reason');
				$message = $this->input->post('message');
				$image = $this->input->post('image');
				$data = array(
					'return_reason' => $reason,
					'message' => isset($message) ? $message : '',
					'image' => $image,
					'edit_return_reason_id' => $this->input->post('edit_return_reason_id')
				);
				$add_return_reasons = $this->return_reason_model->add_return_reason_details($data);

				$response["error"] = false;
				$response['csrfName'] = $this->security->get_csrf_token_name();
				$response['csrfHash'] = $this->security->get_csrf_hash();
				$response["message"] = $data['edit_return_reason_id'] != null ? "Return Reason Updated Successfully" : "Return Reason Added Successfully";
				$response["data"] = $add_return_reasons;
				echo json_encode($response);
			}
		} else {
			redirect('admin/login', 'refresh');
		}
	}
}
