<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Return_request extends CI_Controller {


	public function __construct(){
		parent::__construct();
		$this->load->database();
		$this->load->library(['ion_auth', 'form_validation','upload']);
		$this->load->helper(['url', 'language','file']);		
		$this->load->model('return_request_model');		

        if (!has_permissions('read', 'return_request')) {
            $this->session->set_flashdata('authorize_flag',PERMISSION_ERROR_MSG);
            redirect('admin/home','refresh');
        }

	}

	public function index(){
		if($this->ion_auth->logged_in() && $this->ion_auth->is_admin())
		{
			$this->data['main_page'] = TABLES.'return-request';
			$settings=get_settings('system_settings',true);
			$this->data['title'] = 'Return Request | '.$settings['app_name'];
			$this->data['meta_description'] = ' Return Request  | '.$settings['app_name'];
			$this->data['delivery_res'] = $this->db->where(['ug.group_id' => '3', 'u.active' => 1])->join('users_groups ug', 'ug.user_id = u.id')->get('users u')->result_array();

			$this->load->view('admin/template',$this->data);
		}
		else{
			redirect('admin/login','refresh');
		}
    }

    public function update_return_request(){
        if($this->ion_auth->logged_in() && $this->ion_auth->is_admin())
		{ 
            if ( print_msg(!has_permissions('update', 'return_request'),PERMISSION_ERROR_MSG,'return_request')) {
               return false;
            }
			$this->form_validation->set_rules('return_request_id', 'id', 'trim|required|numeric|xss_clean');
			$this->form_validation->set_rules('status', 'Status', 'trim|required|numeric|xss_clean');
			$this->form_validation->set_rules('update_remarks', 'Remarks ', 'trim|xss_clean');
			$this->form_validation->set_rules('order_item_id', 'Order Id ', 'trim|required|numeric|xss_clean');
			$status = $this->input->post('status', true);
			if (isset($status) && $status == 1) {				
				$this->form_validation->set_rules('deliver_by', 'Delivery Boy ', 'trim|required|xss_clean');
			}
			
			 if(!$this->form_validation->run()){

	        	$this->response['error'] = true;				
				$this->response['csrfName'] = $this->security->get_csrf_token_name();
				$this->response['csrfHash'] = $this->security->get_csrf_hash();
				$this->response['message'] = validation_errors() ;
				print_r(json_encode($this->response));	
	        } else {             
                $data = array(
                    'return_request_id' => $this->input->post('return_request_id', true),
                    'user_id' => $this->input->post('user_id', true),
                    'order_item_id' => $this->input->post('order_item_id', true),
                    'deliver_by' => $this->input->post('deliver_by', true),
                    'update_remarks' => $this->input->post('update_remarks', true),
                    'id' => $this->input->post('id', true),
                    'status' => $this->input->post('status', true),
                );
	        	$this->return_request_model->update_return_request($data);
	        	$this->response['error'] = false;				
				$this->response['csrfName'] = $this->security->get_csrf_token_name();
				$this->response['csrfHash'] = $this->security->get_csrf_hash();				
				$this->response['message'] = 'Return request updated successfully';
				print_r(json_encode($this->response));	
	        }
		}
		else{
			redirect('admin/login','refresh');
		}
    }


    public function view_return_request_list(){
		if($this->ion_auth->logged_in() && $this->ion_auth->is_admin())
		{			
			return $this->return_request_model->get_return_request_list();
		} else {
			redirect('admin/login','refresh');
		}		
	}
}
