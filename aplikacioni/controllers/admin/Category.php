<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Category extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library(['ion_auth', 'form_validation', 'upload']);
        $this->load->helper(['url', 'language', 'file']);
        $this->load->model(['category_model']);

        if (!has_permissions('read', 'categories')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        }
    }



    public function get_categories()
    {
        $ignore_status = isset($_GET['ignore_status']) && $_GET['ignore_status'] == 1 ? 1 : '';
        $response['data'] = $this->data['category_result'] = $this->category_model->get_categories(NULL, '', '', 'row_order', 'ASC', 'true', '', $ignore_status);
        echo json_encode($response);
        return;
    }


    public function create_category()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = FORMS . 'category';
            $settings = get_settings('system_settings', true);
            $id = $this->input->get('id', true);
            if (isset($id) && !empty($id)) {
                $this->data['base_category_url'] = base_url() . 'admin/category/category_list?id=' . $id;
            } else {
                $this->data['base_category_url']  = base_url() . 'admin/category/category_list';
            }
            $this->data['category_result'] = $this->category_model->get_categories();
            $this->data['title'] = (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) ? 'Edit Category | ' . $settings['app_name'] : 'Add Category | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Add Category , Create Category | ' . $settings['app_name'];
            if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
                $this->data['fetched_data'] = fetch_details('categories', ['id' => $_GET['edit_id']]);
            }

            $this->data['categories'] = $this->category_model->get_categories();

            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function category_order()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = TABLES . 'category-order';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Category Order | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Category Order | ' . $settings['app_name'];
            $this->data['categories'] = $this->category_model->get_categories();
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }
    
    public function delete_category()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
    
            if (print_msg(!has_permissions('delete', 'categories'), PERMISSION_ERROR_MSG, 'categories')) {
                return false;
            }
    
            $category_id = $this->input->get('id', TRUE); // Get the category ID from the GET request
    
            // Fetch products associated with the category
            $products = $this->category_model->get_products_by_category($category_id);
            
            $product_status = '1'; // Default to active
            foreach ($products as $product) {
                if ($product['status'] == '0') {
                    $product_status = '0'; // Found a deactivated product
                    break;
                }
            }
    
            if ($product_status == '0') {
                // If any associated product is deactivated
                $this->response['error'] = true;
                $this->response['message'] = '';
            } else {
                // Proceed with deletion
                $delete_status = $this->category_model->delete_category($category_id);
                
                if ($delete_status) {
                    $this->response['error'] = false;
                    $this->response['message'] = 'Category deleted successfully.';
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Category is assigned to products therefore cannot be deleted.';
                }
            }
    
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($this->response));
        } else {
            redirect('admin/login', 'refresh');
        }
    }
    


    public function category_list()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            return $this->category_model->get_category_list();
        } else {
            redirect('admin/login', 'refresh');
        }
    }



    public function add_category()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
    
            $edit_category = $this->input->post('edit_category', true);
            if (null !== $edit_category) {
                if (print_msg(!has_permissions('update', 'categories'), PERMISSION_ERROR_MSG, 'categories')) {
                    return false;
                }
            } else {
                if (print_msg(!has_permissions('create', 'categories'), PERMISSION_ERROR_MSG, 'categories')) {
                    return false;
                }
            }
            
            $this->form_validation->set_rules('category_input_name', 'Category Name', 'trim|required|xss_clean');
            $this->form_validation->set_rules('banner', 'Banner', 'trim|xss_clean');
    
            $editCategory = $this->input->post('edit_category', true);
            if (isset($editCategory) && !empty($editCategory)) {
                 $this->form_validation->set_rules('category_input_image', 'Image', 'trim|xss_clean');
            } else {
                $this->form_validation->set_rules('category_input_image', 'Category Image', 'trim|required|xss_clean');
            }
    
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {
                // Check if category name already exists
                $edit_category = $this->input->post('edit_category', true);
                if (null !== $edit_category) {
                    if (is_exist(['name' => $this->input->post('category_input_name', true)], 'categories', $this->input->post('edit_category', true))) {
                        $response["error"] = true;
                        $response['csrfName'] = $this->security->get_csrf_token_name();
                        $response['csrfHash'] = $this->security->get_csrf_hash();
                        $response["message"] = "Category Name Already Exists!";
                        $response["data"] = array();
                        echo json_encode($response);
                        return false;
                    }
                } else {
                    if (is_exist(['name' =>$this->input->post('category_input_name', true)], 'categories')) {
                        $response["error"] = true;
                        $response['csrfName'] = $this->security->get_csrf_token_name();
                        $response['csrfHash'] = $this->security->get_csrf_hash();
                        $response["message"] = "Category Name Already Exists!";
                        $response["data"] = array();
                        echo json_encode($response);
                        return false;
                    }
                }

                // Proceed to add or update the category
                $data = array(
                    'edit_category' => $this->input->post('edit_category', true),
                    'categories' => $this->input->post('categories', true),
                    'category_input_name' => $this->input->post('category_input_name', true),
                    'category_input_image' => $this->input->post('category_input_image', true),
                    'category_parent' => $this->input->post('category_parent', true),

                    'banner' => $this->input->post('banner', true),
                );

                $this->category_model->add_category($data);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $message = (null !== $this->input->post('edit_category', true)) ? 'Category Updated Successfully' : 'Category Added Successfully';
                $this->response['message'] = $message;
                $this->response['reload'] = true;
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function update_category_order()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('update', 'category_order'), PERMISSION_ERROR_MSG, 'category_order', false)) {
                return false;
            }
            $i = 0;
            $temp = array();
            foreach ($_GET['category_id'] as $row) {
                $temp[$row] = $i;
                $data = [
                    'row_order' => $i
                ];
                $data = escape_array($data);
                $this->db->where(['id' => $row])->update('categories', $data);
                $i++;
            }

            $response['error'] = false;
            $response['message'] = 'Category Order Saved !';

            print_r(json_encode($response));
        } else {
            redirect('admin/login', 'refresh');
        }
    }
}
