<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library(['ion_auth', 'form_validation', 'upload']);
        $this->load->helper(['url', 'language', 'file']);
        $this->load->model(['product_model', 'category_model', 'rating_model']);

        if (!has_permissions('read', 'product')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        }
    }
    public function index()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = TABLES . 'manage-product';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Product Management | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Product Management |' . $settings['app_name'];
            if (isset($_GET['edit_id'])) {
                $this->data['fetched_data'] = fetch_details('product_faqs', ['id' => $_GET['edit_id']]);
            }
            $this->data['categories'] = $this->category_model->get_categories();
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function create_product()
    {

        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = FORMS . 'product';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Add Product | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Add Product | ' . $settings['app_name'];
            $this->data['taxes'] = fetch_details('taxes', null, '*');
            $this->data['countries'] = fetch_details('countries', null, 'name,id');
            $this->data['shipping_data'] = fetch_details('pickup_locations', ['status' => 1], 'id,pickup_location');
            $this->data['brands'] = fetch_details('brands', null, 'name,id');
            $this->data['shipping_method'] = get_settings('shipping_method', true);
            $this->data['system_settings'] = get_settings('system_settings', true);
            $this->data['cities'] = fetch_details('cities', "", 'name,id');
            if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
                $this->data['title'] = 'Update Product | ' . $settings['app_name'];
                $this->data['meta_description'] = 'Update Product | ' . $settings['app_name'];
                $product_details = fetch_details('products', ['id' => $_GET['edit_id']], '*');
                $countries = fetch_details('countries', ['name' => $product_details[0]['made_in']], 'name');
                if (!empty($product_details)) {
                    $this->data['product_details'] = $product_details;
                    $this->data['product_variants'] = get_variants_values_by_pid($_GET['edit_id']);
                    $product_attributes = fetch_details('product_attributes', ['product_id' => $_GET['edit_id']]);
                    if (!empty($product_attributes) && !empty($product_details)) {
                        $this->data['product_attributes'] = $product_attributes;
                    }
                } else {
                    redirect('admin/product/create_product', 'refresh');
                }
            }


            // Query to select the attributes along with their status
            $attributes = $this->db->select('attr_val.id, attr.name as attr_name, attr_set.name as attr_set_name, attr_val.value, attr_val.status')
                ->join('attributes attr', 'attr.id=attr_val.attribute_id')
                ->join('attribute_set attr_set', 'attr_set.id=attr.attribute_set_id')
                ->where(['attr.status' => 1, 'attr_set.status' => 1])
                ->get('attribute_values attr_val')->result_array();

            $attributes_refind = array();

            // Iterate through the attributes
            foreach ($attributes as $attribute) {
                // Only include attributes where status is '1'
                if ($attribute['status'] === '1') {
                    // Initialize attribute set array if it does not exist
                    if (!array_key_exists($attribute['attr_set_name'], $attributes_refind)) {
                        $attributes_refind[$attribute['attr_set_name']] = array();
                    }

                    // Initialize attribute name array if it does not exist
                    if (!array_key_exists($attribute['attr_name'], $attributes_refind[$attribute['attr_set_name']])) {
                        $attributes_refind[$attribute['attr_set_name']][$attribute['attr_name']] = array();
                    }

                    // Add the attribute value
                    $attributes_refind[$attribute['attr_set_name']][$attribute['attr_name']][] = array(
                        'id' => $attribute['id'],
                        'text' => $attribute['value'],
                        'data-values' => $attribute['value']
                    );
                }
            }
            $this->data['categories'] = $this->category_model->get_categories();
            $this->data['attributes_refind'] = $attributes_refind;
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }


    public function product_order()
    {

        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (!has_permissions('read', 'product_order')) {
                $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                redirect('admin/home', 'refresh');
            }

            $this->data['main_page'] = TABLES . 'products-order';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Product Order | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Product Order | ' . $settings['app_name'];
            $this->data['categories'] = $this->category_model->get_categories();
            $products = $this->db->select('*')->order_by('row_order')->get('products')->result_array();
            $this->data['product_result'] = $products;
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function get_variants_by_id()
    {
        $attr_values = array();
        $final_variant_ids = array();
        $variant_ids = json_decode($this->input->get('variant_ids'));
        $attributes_values = json_decode($this->input->get('attributes_values'));
        foreach ($attributes_values as $a => $b) {
            foreach ($b as $key => $value) {
                array_push($attr_values, $value);
            }
        }
        $res = $this->db->select('id,value')->where_in('id', $attr_values)->get('attribute_values')->result_array();

        for ($i = 0; $i < count($variant_ids); $i++) {
            for ($j = 0; $j < count($variant_ids[$i]); $j++) {
                $k = array_search($variant_ids[$i][$j], array_column($res, 'id'));
                $final_variant_ids[$i][$j] = $res[$k];
            }
        }
        $response['result'] = $final_variant_ids;
        print_r(json_encode($response));
    }

    public function fetch_attributes_by_id()
    {
        $variants = get_variants_values_by_pid($_GET['edit_id']);
        $res['attr_values'] = get_attribute_values_by_pid($_GET['edit_id']);
        $res['pre_selected_variants_names'] = (!empty($variants)) ? $variants[0]['attr_name'] : null;
        $res['pre_selected_variants_ids'] = $variants;
        $response['csrfName'] = $this->security->get_csrf_token_name();
        $response['csrfHash'] = $this->security->get_csrf_hash();
        $response['result'] = $res;
        print_r(json_encode($response));
    }

    public function fetch_attribute_values_by_id($id = NULL)
    {
        if (isset($id) && !empty($id)) {
            $aid = $id;
        } else {
            $aid = $_GET['id'];
        }
        $variant_ids = get_attribute_values_by_id($aid);
        print_r(json_encode($variant_ids));
    }

    public function fetch_variants_values_by_pid()
    {
        $res = get_variants_values_by_pid($_GET['edit_id']);
        $response['result'] = $res;
        print_r(json_encode($response));
    }



    public function update_product_order()
    {

        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('update', 'product_order'), PERMISSION_ERROR_MSG, 'product_order', false)) {
                return false;
            }

            $i = 0;
            $temp = array();
            foreach ($_GET['product_id'] as $row) {
                $temp[$row] = $i;
                $data = [
                    'row_order' => $i
                ];
                $data = escape_array($data);
                $this->db->where(['id' => $row])->update('products', $data);
                $i++;
            }

            $response['error'] = false;
            $response['message'] = 'Product Order Saved !';

            print_r(json_encode($response));
        } else {
            redirect('admin/login', 'refresh');
        }
    }


    public function search_category_wise_products()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            $this->db->select('p.*');
            if ($_GET['cat_id'] == 0) {
                $data = "";
            } else {
                $this->db->where('p.category_id', $_GET['cat_id']);
                $this->db->or_where('c.parent_id', $_GET['cat_id']);
            }
            $product_data = json_encode($this->db->order_by('row_order')->join('categories c', 'p.category_id = c.id')->get('products p')->result_array());
            //this is for return product data don't remove it
            print_r($product_data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function delete_product()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('delete', 'product'), PERMISSION_ERROR_MSG, 'product')) {
                return false;
            }
            if (delete_details(['product_id' => $_GET['id']], 'product_variants')) {

                delete_details(['id' => $_GET['id']], 'products');
                delete_details(['product_id' => $_GET['id']], 'product_attributes');
                $response['error'] = false;
                $response['message'] = 'Deleted Successfully';
            } else {
                $response['error'] = true;
                $response['message'] = 'Something Went Wrong';
            }
            print_r(json_encode($response));
        } else {
            redirect('admin/login', 'refresh');
        }
    }
    // Custom validation function to validate Quantity Step Size
    public function validate_step_size()
    {
        $step_size = $this->input->post('quantity_step_size');
        $min_order_quantity = $this->input->post('minimum_order_quantity');
        $total_allowed_quantity = $this->input->post('total_allowed_quantity');

        // Ensure all fields are numeric and not empty
        if (is_numeric($step_size) && is_numeric($min_order_quantity) && is_numeric($total_allowed_quantity)) {

            // Validate that Quantity Step Size is greater than or equal to Minimum Order Quantity
            if ($step_size < $min_order_quantity) {
                $this->form_validation->set_message('validate_step_size', 'Quantity Step Size cannot be less than Minimum Order Quantity.');
                return FALSE;
            }

            // Validate that Quantity Step Size is less than or equal to Total Allowed Quantity
            if ($step_size > $total_allowed_quantity) {
                $this->form_validation->set_message('validate_step_size', 'Quantity Step Size cannot be greater than Total Allowed Quantity.');
                return FALSE;
            }

            // Validate that Quantity Step Size is a multiple of Total Allowed Quantity
            if ($total_allowed_quantity % $step_size != 0) {
                $this->form_validation->set_message('validate_step_size', 'Quantity Step Size must be a multiple of Total Allowed Quantity.');
                return FALSE;
            }
        }

        return TRUE;
    }

    public function add_product()
    {

        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            $edit_product_id = $this->input->post('edit_product_id', true);
            if (isset($edit_product_id)) {
                if (print_msg(!has_permissions('update', 'product'), PERMISSION_ERROR_MSG, 'product')) {
                    return false;
                }
            } else {
                if (print_msg(!has_permissions('create', 'product'), PERMISSION_ERROR_MSG, 'product')) {
                    return false;
                }
            }

            $this->form_validation->set_rules('pro_input_name', 'Product Name', 'trim|required|xss_clean');
            $this->form_validation->set_rules('short_description', 'Short Description', 'trim|required|xss_clean');
            $this->form_validation->set_rules('category_id', 'Category Id', 'trim|required|xss_clean', array('required' => 'Category is required'));
            $this->form_validation->set_rules('pro_input_tax[]', 'Tax', 'trim|xss_clean');
            $this->form_validation->set_rules('pro_input_image', 'Image', 'trim|required|xss_clean', array('required' => 'Image is required'));
            $this->form_validation->set_rules('made_in', 'Made In', 'trim|xss_clean');
            $this->form_validation->set_rules('brand', 'Brand', 'trim|xss_clean');
            $this->form_validation->set_rules('product_type', 'Product type', 'trim|required|xss_clean');
            $this->form_validation->set_rules('warranty_period', 'Warranty Period', 'trim|xss_clean');
            $this->form_validation->set_rules('guarantee_period', 'Guarantee Period', 'trim|xss_clean');
            $this->form_validation->set_rules('hsn_code', 'HSN_Code', 'trim|xss_clean');
            $this->form_validation->set_rules('video', 'Video', 'trim|xss_clean');
            $this->form_validation->set_rules('video_type', 'Video Type', 'trim|xss_clean');
            $this->form_validation->set_rules('deliverable_type', 'Deliverable Type', 'trim|xss_clean');
            $this->form_validation->set_rules('product_identity', 'product_identity', 'trim|xss_clean');
            $this->form_validation->set_rules('total_allowed_quantity', 'Total Allowed Quantity', 'trim|xss_clean');
            $this->form_validation->set_rules('minimum_order_quantity', 'Minimum Order Quantity', 'trim|xss_clean');
            $this->form_validation->set_rules('quantity_step_size', 'Quantity Step Size', 'required|greater_than_equal_to[' . $this->input->post('minimum_order_quantity') . ']');
            $this->form_validation->set_rules('quantity_step_size', 'Quantity Step Size', 'callback_validate_step_size');

            $video_type = $this->input->post('video_type', true);
            if (isset($video_type) && $video_type != '') {
                if ($this->input->post('video_type', true) == 'youtube' || $this->input->post('video_type', true) == 'vimeo') {
                    $this->form_validation->set_rules('video', 'Video link', 'trim|required|xss_clean', array('required' => " Please paste a %s in the input box. "));
                } else {
                    $this->form_validation->set_rules('pro_input_video', 'Video file', 'trim|required|xss_clean', array('required' => " Please choose a %s to be set. "));
                }
            }
            if (isset($_POST['download_allowed']) && $_POST['download_allowed'] != '' && !empty($_POST['download_allowed']) && $_POST['download_allowed'] == 'on') {
                $this->form_validation->set_rules('download_link_type', 'Download Link Type', 'required|xss_clean');
                if (isset($_POST['download_link_type']) && $_POST['download_link_type'] != '' && !empty($_POST['download_link_type']) && $_POST['download_link_type'] == 'self_hosted') {
                    $this->form_validation->set_rules('pro_input_zip', 'Zip file ', 'required|xss_clean');
                }
                if (isset($_POST['download_link_type']) && $_POST['download_link_type'] != '' && !empty($_POST['download_link_type']) && $_POST['download_link_type'] == 'add_link') {
                    $this->form_validation->set_rules('download_link', 'Digital Product URL/Link', 'required|xss_clean');
                }
            }

            if (((int) $_POST['quantity_step_size'] > (int) $_POST['minimum_order_quantity']) && ((int) $_POST['quantity_step_size'] > (int) $_POST['total_allowed_quantity'])) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = 'Please enter valid Quantity Step size';
                print_r(json_encode($this->response));
                return true;
            }

            if (isset($_POST['tags']) && $_POST['tags'] != '') {
                $_POST['tags'] = json_decode($_POST['tags'], 1);
                $tags = array_column($_POST['tags'], 'value');
                $_POST['tags'] = implode(",", $tags);
            }

            if (isset($_POST['is_cancelable']) && $_POST['is_cancelable'] == '1') {
                $this->form_validation->set_rules('cancelable_till', 'Till which status', 'trim|required|xss_clean');
            }
            if (isset($_POST['cod_allowed'])) {
                $this->form_validation->set_rules('cod_allowed', 'COD allowed', 'trim|xss_clean');
            }
            if (isset($_POST['is_prices_inclusive_tax'])) {
                $this->form_validation->set_rules('is_prices_inclusive_tax', 'Tax included in prices', 'trim|xss_clean');
            }
            if (isset($_POST['deliverable_type']) && !empty($_POST['deliverable_type']) && ($_POST['deliverable_type'] == INCLUDED || $_POST['deliverable_type'] == EXCLUDED)) {
                $this->form_validation->set_rules('deliverable_zipcodes[]', 'Deliverable Zipcodes', 'trim|required|xss_clean');
            }
            if (isset($_POST['deliverable_city_type']) && !empty($_POST['deliverable_city_type']) && ($_POST['deliverable_city_type'] == INCLUDED || $_POST['deliverable_city_type'] == EXCLUDED)) {
                $this->form_validation->set_rules('deliverable_cities[]', 'Deliverable Cities', 'trim|required|xss_clean');
            }

            // If product type is simple			
            if (isset($_POST['product_type']) && $_POST['product_type'] == 'simple_product' || $_POST['product_type'] == 'digital_product') {

                $this->form_validation->set_rules('simple_price', 'Price', 'trim|required|numeric|greater_than[0]|greater_than_equal_to[' . $this->input->post('simple_special_price') . ']|xss_clean');
                $this->form_validation->set_rules('simple_special_price', 'Special Price', 'trim|numeric|greater_than[0]|less_than_equal_to[' . $this->input->post('simple_price') . ']|xss_clean');

                if ($_POST['product_type'] == 'simple_product') {
                    $this->form_validation->set_rules('weight', 'Weight', 'trim|required|numeric|greater_than[0]|xss_clean');
                    $this->form_validation->set_rules('height', 'Height', 'trim|required|numeric|xss_clean');
                    $this->form_validation->set_rules('length', 'Length', 'trim|required|numeric|xss_clean');
                    $this->form_validation->set_rules('breadth', 'Breadth', 'trim|required|numeric|xss_clean');
                }

                if (isset($_POST['simple_product_stock_status']) && in_array($_POST['simple_product_stock_status'], array('0', '1')) && $_POST['product_type'] != 'digital_product') {

                    $this->form_validation->set_rules('product_sku', 'SKU', 'trim|xss_clean');
                    $this->form_validation->set_rules('product_total_stock', 'Total Stock', 'trim|required|numeric|xss_clean');
                    $this->form_validation->set_rules('simple_product_stock_status', 'Stock Status', 'trim|required|numeric|xss_clean');
                }
            } elseif (isset($_POST['product_type']) && $_POST['product_type'] == 'variable_product') { //If product type is variant	

                $this->form_validation->set_rules('weight[]', 'Weight', 'trim|required|numeric|greater_than[0]|xss_clean');
                $this->form_validation->set_rules('height[]', 'Height', 'trim|required|numeric|xss_clean');
                $this->form_validation->set_rules('length[]', 'Length', 'trim|required|numeric|xss_clean');
                $this->form_validation->set_rules('breadth[]', 'Breadth', 'trim|required|numeric|xss_clean');
                if (isset($_POST['variant_stock_status']) && $_POST['variant_stock_status'] == '0') {
                    if ($_POST['variant_stock_level_type'] == "product_level") {

                        $this->form_validation->set_rules('sku_pro_type', 'SKU', 'trim|xss_clean');
                        $this->form_validation->set_rules('total_stock_variant_type', 'Total Stock', 'trim|required|xss_clean');
                        $this->form_validation->set_rules('variant_stock_status', 'Stock Status', 'trim|required|xss_clean');
                        if (isset($_POST['variant_price']) && isset($_POST['variant_special_price'])) {
                            foreach ($_POST['variant_price'] as $key => $value) {
                                $this->form_validation->set_rules('variant_price[' . $key . ']', 'Price', 'trim|required|numeric|xss_clean|greater_than[0]|greater_than_equal_to[' . $this->input->post('variant_special_price[' . $key . ']') . ']');
                                $this->form_validation->set_rules('variant_special_price[' . $key . ']', 'Special Price', 'trim|numeric|xss_clean|greater_than[0]|less_than_equal_to[' . $this->input->post('variant_price[' . $key . ']') . ']');
                            }
                        } else {
                            $this->form_validation->set_rules('variant_price', 'Price', 'trim|required|numeric|xss_clean|greater_than[0]|greater_than_equal_to[' . $this->input->post('variant_special_price[0]') . ']');
                            $this->form_validation->set_rules('variant_special_price', 'Special Price', 'trim|numeric|xss_clean|greater_than[0]|less_than_equal_to[' . $this->input->post('variant_price') . ']');
                        }
                    } else {
                        if (isset($_POST['variant_price']) && isset($_POST['variant_special_price']) && isset($_POST['variant_sku']) && isset($_POST['variant_total_stock']) && isset($_POST['variant_stock_status'])) {
                            foreach ($_POST['variant_price'] as $key => $value) {
                                $this->form_validation->set_rules('variant_price[' . $key . ']', 'Price', 'trim|required|numeric|xss_clean|greater_than[0]|greater_than_equal_to[' . $this->input->post('variant_special_price[' . $key . ']') . ']');
                                $this->form_validation->set_rules('variant_special_price[' . $key . ']', 'Special Price', 'trim|numeric|xss_clean|greater_than[0]|less_than_equal_to[' . $this->input->post('variant_price[' . $key . ']') . ']');
                                $this->form_validation->set_rules('variant_sku[' . $key . ']', 'SKU', 'trim|xss_clean');
                                $this->form_validation->set_rules('variant_total_stock[' . $key . ']', 'Total Stock asd', 'trim|required|numeric|xss_clean');
                                $this->form_validation->set_rules('variant_level_stock_status[' . $key . ']', 'Stock Status', 'trim|required|numeric|xss_clean');
                            }
                        } else {
                            $this->form_validation->set_rules('variant_price', 'Price', 'trim|required|numeric|xss_clean|greater_than[0]|greater_than_equal_to[' . $this->input->post('variant_special_price[0]') . ']');
                            $this->form_validation->set_rules('variant_special_price', 'Special Price', 'trim|numeric|xss_clean|greater_than[0]|less_than_equal_to[' . $this->input->post('variant_price') . ']');
                            $this->form_validation->set_rules('variant_sku', 'SKU', 'trim|xss_clean');
                            $this->form_validation->set_rules('variant_total_stock', 'Total Stock asd', 'trim|required|numeric|xss_clean');
                            $this->form_validation->set_rules('variant_level_stock_status', 'Stock Status', 'trim|required|numeric|xss_clean');
                        }
                    }
                } else {
                    if (isset($_POST['variant_price']) && isset($_POST['variant_special_price'])) {
                        foreach ($_POST['variant_price'] as $key => $value) {
                            $this->form_validation->set_rules('variant_price[' . $key . ']', 'Price', 'trim|required|numeric|xss_clean|greater_than[0]|greater_than_equal_to[' . $this->input->post('variant_special_price[' . $key . ']') . ']');
                            $this->form_validation->set_rules('variant_special_price[' . $key . ']', 'Special Price', 'trim|numeric|xss_clean|greater_than[0]|less_than_equal_to[' . $this->input->post('variant_price[' . $key . ']') . ']');
                        }
                    } else {
                        $this->form_validation->set_rules('variant_price', 'Price', 'trim|required|numeric|xss_clean|greater_than[0]|greater_than_equal_to[' . $this->input->post('variant_special_price[0]') . ']');
                        $this->form_validation->set_rules('variant_special_price', 'Special Price', 'trim|numeric|xss_clean|greater_than[0]|less_than_equal_to[' . $this->input->post('variant_price') . ']');
                    }
                }
            }

            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                return print_r(json_encode($this->response));
            } else {

                $deliverable_zipcodes = $this->input->post('deliverable_zipcodes', true);
                if (isset($deliverable_zipcodes) && !empty($deliverable_zipcodes)) {
                    $_POST['zipcodes'] = implode(",", $this->input->post('deliverable_zipcodes', true));
                } else {
                    $_POST['zipcodes'] = NULL;
                }

                $deliverable_cities = $this->input->post('deliverable_cities', true);
                if (isset($deliverable_cities) && !empty($deliverable_cities)) {
                    $_POST['cities'] = implode(",", $this->input->post('deliverable_cities', true));
                } else {
                    $_POST['cities'] = NULL;
                }

                $this->product_model->add_product($_POST);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $message = (isset($_POST['edit_product_id'])) ? 'Product Updated Successfully' : 'Product Added Successfully';
                $this->response['message'] = $message;
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function get_brands_data()
    {
        $search = $this->input->get('search');
        $response = $this->product_model->get_brands($search);
        echo json_encode($response);
    }
    public function get_offer__brands_data()
    {
        $search = $this->input->get('search');
        $response = $this->product_model->get_offer_brands($search);
        echo json_encode($response);
    }
    public function get_product_data()
    {

        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (isset($_GET['flag']) && !empty($_GET['flag'])) {
                return $this->product_model->get_product_details($_GET['flag']);
            } else {

                return $this->product_model->get_product_details();
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }




    public function get_product_data_list()
    {

        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            return $this->product_model->get_product_details('low');
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function get_rating_list()
    {

        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            return $this->rating_model->get_rating();
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function get_faqs_list()
    {

        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            return $this->product_model->get_faqs();
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function fetch_attributes()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $attributes = $this->db->select('attr_val.id,attr.name as attr_name ,attr_set.name as attr_set_name,attr_val.value')->join('attributes attr', 'attr.id=attr_val.attribute_id')->join('attribute_set attr_set', 'attr_set.id=attr_val.attribute_set_id')->get('attribute_values attr_val')->result_array();
            $attributes_refind = array();
            for ($i = 0; $i < count($attributes); $i++) {

                if (!array_key_exists($attributes[$i]['attr_set_name'], $attributes_refind)) {
                    $attributes_refind[$attributes[$i]['attr_set_name']] = array();

                    for ($j = 0; $j < count($attributes); $j++) {

                        if ($attributes[$i]['attr_set_name'] == $attributes[$j]['attr_set_name']) {

                            if (!array_key_exists($attributes[$j]['attr_name'], $attributes_refind[$attributes[$i]['attr_set_name']])) {

                                $attributes_refind[$attributes[$i]['attr_set_name']][$attributes[$j]['attr_name']] = array();
                            }
                            $attributes_refind[$attributes[$i]['attr_set_name']][$attributes[$j]['attr_name']][$j]['id'] = $attributes[$j]['id'];

                            $attributes_refind[$attributes[$i]['attr_set_name']][$attributes[$j]['attr_name']][$j]['text'] = $attributes[$j]['value'];

                            $attributes_refind[$attributes[$i]['attr_set_name']][$attributes[$j]['attr_name']] = array_values($attributes_refind[$attributes[$i]['attr_set_name']][$attributes[$j]['attr_name']]);
                        }
                    }
                }
            }
            print_r(json_encode($attributes_refind));
        } else {
            redirect('admin/login', 'refresh');
        }
    }


    public function view_product()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
                $this->data['main_page'] = VIEW . 'products';
                $settings = get_settings('system_settings', true);
                $this->data['title'] = 'View Product | ' . $settings['app_name'];
                $this->data['meta_description'] = 'View Product | ' . $settings['app_name'];
                $res = fetch_product($user_id = NULL, $filter = NULL, $this->input->get('edit_id', true));
                $this->data['product_details'] = $res['product'];
                $this->data['product_attributes'] = get_attribute_values_by_pid($_GET['edit_id']);
                $this->data['product_variants'] = get_variants_values_by_pid($_GET['edit_id'], [0, 1, 7]);
                $this->data['product_rating'] = $this->rating_model->fetch_rating((isset($_GET['edit_id'])) ? $_GET['edit_id'] : '', '');
                $this->data['currency'] = $settings['currency'];
                $this->data['category_result'] = fetch_details('categories', ['status' => '1'], 'id,name');
                if (!empty($res['product'])) {
                    $this->load->view('admin/template', $this->data);
                } else {
                    redirect('admin/product', 'refresh');
                }
            } else {
                redirect('admin/product', 'refresh');
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }


    public function delete_rating()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            if (print_msg(!has_permissions('delete', 'product'), PERMISSION_ERROR_MSG, 'product', false)) {
                return false;
            }

            $this->rating_model->delete_rating($_GET['id']);

            $this->response['error'] = false;
            $this->response['message'] = 'Deleted Successfully';

            print_r(json_encode($this->response));
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function change_variant_status($id = '', $status = '', $product_id = '')
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

            if (!has_permissions('update', 'product')) {
                $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                redirect('admin/product/view-product?edit_id=' . $product_id, 'refresh');
            }

            $status = (trim($status) != '' && is_numeric(trim($status))) ? trim($status) : "";
            $id = (!empty(trim($id)) && is_numeric(trim($id))) ? trim($id) : "";

            if (empty($id) || $status == '') {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = "Invalid Status or ID value supplied";

                $this->session->set_flashdata('message', $this->response['message']);
                $this->session->set_flashdata('message_type', 'error');
                if (!empty($product_id)) {
                    $callback_url = base_url("admin/product/view-product?edit_id=$product_id");
                    header("location:$callback_url");
                    return false;
                } else {
                    print_r(json_encode($this->response));
                    return false;
                }
            }
            $all_status = [0, 1, 7];
            if (!in_array($status, $all_status)) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = "Invalid Status value supplied";

                $this->session->set_flashdata('message', $this->response['message']);
                $this->session->set_flashdata('message_type', 'error');
                if (!empty($product_id)) {
                    $callback_url = base_url("admin/product/view-product?edit_id=$product_id");
                    header("location:$callback_url");
                    return false;
                } else {
                    print_r(json_encode($this->response));
                    return false;
                }
            }

            /* change variant status to the new status */
            update_details(['status' => $status], ['id' => $id], 'product_variants');

            $this->response['error'] = false;
            $this->response['message'] = 'Variant status changed successfully';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();

            $this->session->set_flashdata('message', $this->response['message']);
            $this->session->set_flashdata('message_type', 'success');
            if (!empty($product_id)) {
                $callback_url = base_url("admin/product/view-product?edit_id=$product_id");
                header("location:$callback_url");
                return false;
            } else {
                print_r(json_encode($this->response));
                return false;
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function bulk_upload()
    {

        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = FORMS . 'bulk-upload';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Bulk Upload | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Bulk Upload | ' . $settings['app_name'];

            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    // public function process_bulk_upload()
    // {
    //     if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
    //         if (print_msg(!has_permissions('create', 'product'), PERMISSION_ERROR_MSG, 'product')) {
    //             return false;
    //         }
    //         $this->form_validation->set_rules('bulk_upload', '', 'xss_clean');
    //         $this->form_validation->set_rules('type', 'Type', 'trim|required|xss_clean');
    //         if (empty($_FILES['upload_file']['name'])) {
    //             $this->form_validation->set_rules('upload_file', 'File', 'trim|required|xss_clean', array('required' => 'Please choose file'));
    //         }

    //         if (!$this->form_validation->run()) {
    //             $this->response['error'] = true;
    //             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //             $this->response['message'] = validation_errors();
    //             print_r(json_encode($this->response));
    //         } else {
    //             $allowed_mime_type_arr = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv');
    //             $mime = get_mime_by_extension($_FILES['upload_file']['name']);
    //             if (!in_array($mime, $allowed_mime_type_arr)) {
    //                 $this->response['error'] = true;
    //                 $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                 $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                 $this->response['message'] = 'Invalid file format!';
    //                 print_r(json_encode($this->response));
    //                 return false;
    //             }
    //             $csv = $_FILES['upload_file']['tmp_name'];
    //             $temp = 0;
    //             $temp1 = 0;
    //             $handle = fopen($csv, "r");
    //             $allowed_status = array("received", "processed", "shipped");
    //             $video_types = array("youtube", "vimeo");
    //             $this->response['message'] = '';
    //             $type = $_POST['type'];
    //             if ($type == 'upload') {
    //                 while (($row = fgetcsv($handle, 10000, ",")) != FALSE) //get row vales
    //                 {

    //                     if ($temp != 0) {
    //                         if (empty($row[0])) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Category id is empty at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if ($row[2] != 'simple_product' && $row[2] != 'variable_product') {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Product type is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (empty($row[4])) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Name is empty at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }


    //                         if (!empty($row[7]) && $row[7] != 1) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'COD allowed is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[11]) && $row[11] != 1) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Is prices inclusive tax is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[12]) && $row[12] != 1) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Is Returnable is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[13]) && $row[13] != 1) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Is Cancelable is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[13]) && $row[13] == 1 && (empty($row[14]) || !in_array($row[14], $allowed_status))) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Cancelable till is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (empty($row[13]) && !(empty($row[14]))) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Cancelable till is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (empty($row[15])) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Image is empty at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[17]) && !in_array($row[17], $video_types)) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Video type is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }
    //                         if ($row[27] != 0 && $row[27] != 1 && $row[27] != 2 && $row[27] != 3 && $row[27] == "") {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Not valid value for deliverable_type at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }
    //                         if ($row[29] != 0 && $row[29] != 1 && $row[29] != 2 && $row[29] != 3 && $row[29] == "") {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Not valid value for deliverable_city_type at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if ($row[27] == INCLUDED || $row[27] == EXCLUDED) {
    //                             if (empty($row[28])) {
    //                                 $this->response['error'] = true;
    //                                 $this->response['message'] = 'Deliverable_zipcodes is empty at row ' . $temp;
    //                                 $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                                 $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                                 print_r(json_encode($this->response));
    //                                 return false;
    //                             }
    //                         }
    //                         if ($row[29] == INCLUDED || $row[29] == EXCLUDED) {
    //                             if (empty($row[30])) {
    //                                 $this->response['error'] = true;
    //                                 $this->response['message'] = 'Deliverable_zipcodes is empty at row ' . $temp;
    //                                 $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                                 $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                                 print_r(json_encode($this->response));
    //                                 return false;
    //                             }
    //                         }

    //                         $index1 = 31;
    //                         $total_variants = 0;
    //                         for ($j = 0; $j < 50; $j++) {

    //                             if (!empty($row[$index1])) {

    //                                 $total_variants++;
    //                             }
    //                             $index1 = $index1 + 7;
    //                         }
    //                         $variant_index = 31;
    //                         for ($k = 0; $k < $total_variants; $k++) {
    //                             if ($row[2] == 'variable_product') {
    //                                 if (empty($row[$variant_index])) {
    //                                     $this->response['error'] = true;
    //                                     $this->response['message'] = 'Attribute value ids is empty at row  ' . $temp;
    //                                     $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                                     $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                                     print_r(json_encode($this->response));
    //                                     return false;
    //                                 }
    //                                 $variant_index = $variant_index + 7;
    //                             }
    //                         }

    //                         if ($total_variants == 0 && $row[2] == 'variable_product') {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Variants not found at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         } elseif ($row[2] == 'simple_product' && $total_variants > 1) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'You can not add variants more than one for simple prodcuct at row  ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }
    //                     }
    //                     $temp++;
    //                 }

    //                 fclose($handle);
    //                 $handle = fopen($csv, "r");
    //                 while (($row = fgetcsv($handle, 10000, ",")) != FALSE) //get row vales
    //                 {
    //                     if ($temp1 != 0) {
    //                         $data['category_id'] = $row[0];
    //                         if (!empty($row[1])) {
    //                             $data['tax'] = $row[1];
    //                         }
    //                         $data['type'] = $row[2];
    //                         if ($row[3] != '') {
    //                             $data['stock_type'] = $row[3];
    //                         }

    //                         $data['name'] = $row[4];
    //                         $data['short_description'] = $row[5];
    //                         $data['slug'] = create_unique_slug($row[4], 'products');
    //                         if ($row[6] != '') {
    //                             $data['indicator'] = $row[6];
    //                         }
    //                         if ($row[7] != '') {
    //                             $data['cod_allowed'] = $row[7];
    //                         }

    //                         if ($row[8] != '') {
    //                             $data['minimum_order_quantity'] = $row[8];
    //                         }
    //                         if ($row[9] != '') {
    //                             $data['quantity_step_size'] = $row[9];
    //                         }
    //                         if ($row[10] != '') {
    //                             $data['total_allowed_quantity'] = $row[10];
    //                         }
    //                         if ($row[11] != '') {
    //                             $data['is_prices_inclusive_tax'] = $row[11];
    //                         }
    //                         if ($row[12] != '') {
    //                             $data['is_returnable'] = $row[12];
    //                         }
    //                         if ($row[13] != '') {
    //                             $data['is_cancelable'] = $row[13];
    //                         }
    //                         $data['cancelable_till'] = $row[14];
    //                         $data['image'] = $row[15];
    //                         if (isset($row[16]) && $row[16] != '') {
    //                             $other_images = explode(',', $row[16] ?? '');
    //                             $data['other_images'] = json_encode($other_images, 1);
    //                         } else {
    //                             $data['other_images'] = '[]';
    //                         }
    //                         $data['video_type'] = $row[17];
    //                         $data['video'] = $row[18];
    //                         $data['tags'] = $row[19];
    //                         $data['warranty_period'] = $row[20];
    //                         $data['guarantee_period'] = $row[21];
    //                         $data['made_in'] = $row[22];

    //                         if (!empty($row[23])) {
    //                             $data['sku'] = $row[23];
    //                         }
    //                         if (!empty($row[24])) {
    //                             $data['stock'] = $row[24];
    //                         }
    //                         if ($row[25] != '') {
    //                             $data['availability'] = $row[25];
    //                         }

    //                         $data['description'] = $row[26];
    //                         $data['deliverable_type'] = $row[27]; //in csv its 28th
    //                         $data['deliverable_zipcodes'] = $row[28]; // in csv its 29th
    //                         $data['deliverable_city_type'] = $row[29]; //in csv its 28th
    //                         $data['deliverable_cities'] = $row[30]; // in csv its 29th

    //                         $this->db->insert('products', $data);
    //                         $product_id = $this->db->insert_id();

    //                         $index1 = 31;
    //                         $total_variants = 0;
    //                         for ($j = 0; $j < 50; $j++) {
    //                             if (!empty($row[$index1])) {
    //                                 $total_variants++;
    //                             }
    //                             $index1 = $index1 + 7;
    //                         }
    //                         $index1 = 31;
    //                         $attribute_value_ids = '';
    //                         for ($j = 0; $j < $total_variants; $j++) {
    //                             if (!empty($row[$index1])) {
    //                                 if (!empty($attribute_value_ids)) {
    //                                     $attribute_value_ids .= ',' . strval($row[$index1]);
    //                                 } else {
    //                                     $attribute_value_ids = strval($row[$index1]);
    //                                 }
    //                             }
    //                             $index1 = $index1 + 7;
    //                         }
    //                         $attribute_value_ids = !empty($attribute_value_ids) ? $attribute_value_ids : '';
    //                         $pro_attr_data = [

    //                             'product_id' => $product_id,
    //                             'attribute_value_ids' => $attribute_value_ids,

    //                         ];
    //                         $this->db->insert('product_attributes', $pro_attr_data);
    //                         $index = 31;
    //                         for ($i = 0; $i < $total_variants; $i++) {
    //                             $variant_data[$i]['images'] = '[]';
    //                             $variant_data[$i]['product_id'] = $product_id;
    //                             $variant_data[$i]['attribute_value_ids'] = $row[$index];
    //                             $index++;
    //                             $variant_data[$i]['price'] = $row[$index];
    //                             $index++;
    //                             if (isset($row[$index]) && !empty($row[$index])) {
    //                                 $variant_data[$i]['special_price'] = $row[$index];
    //                             } else {
    //                                 $variant_data[$i]['special_price'] = 0;
    //                             }

    //                             $index++;
    //                             if (isset($row[$index]) && !empty($row[$index])) {
    //                                 $variant_data[$i]['sku'] = $row[$index];
    //                             }
    //                             $index++;
    //                             if (isset($row[$index]) && !empty($row[$index])) {
    //                                 $variant_data[$i]['stock'] = $row[$index];
    //                             }

    //                             $index++;
    //                             if (isset($row[$index]) && $row[$index] != '' && !empty($row[$index])) {
    //                                 $images = explode(',', $row[$index] ?? '');
    //                                 $variant_data[$i]['images'] = json_encode($images, 1);
    //                             }

    //                             $index++;
    //                             if (isset($row[$index]) && $row[$index] != '') {
    //                                 $variant_data[$i]['availability'] = $row[$index];
    //                             }

    //                             $index++;

    //                             $this->db->insert('product_variants', $variant_data[$i]);
    //                         }
    //                     }
    //                     $temp1++;
    //                 }
    //                 fclose($handle);
    //                 $this->response['error'] = false;
    //                 $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                 $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                 $this->response['message'] = 'Products uploaded successfully!';
    //                 print_r(json_encode($this->response));
    //                 return false;
    //             } else {
    //                 while (($row = fgetcsv($handle, 10000, ",")) != FALSE) //get row vales
    //                 {

    //                     if ($temp != 0) {
    //                         if (empty($row[0])) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Product id is empty at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[3]) && $row[3] != 'simple_product' && $row[3] != 'variable_product') {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Product type is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }


    //                         if (!empty($row[8]) && $row[8] != 1) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'COD allowed is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[12]) && $row[12] != 1) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Is prices inclusive tax is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[13]) && $row[13] != 1) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Is Returnable is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[14]) && $row[14] != 1) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Is Cancelable is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[14]) && $row[14] == 1 && (empty($row[15]) || !in_array($row[15], $allowed_status))) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Cancelable till is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (empty($row[14]) && !(empty($row[15]))) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Cancelable till is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if (!empty($row[18]) && !in_array($row[18], $video_types)) {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Video type is invalid at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }
    //                         if ($row[27] != "") {
    //                             if ($row[27] != 0 && $row[27] != 1 && $row[27] != 2 && $row[27] != 3) {
    //                                 $this->response['error'] = true;
    //                                 $this->response['message'] = 'Not valid value for deliverable_type at row ' . $temp;
    //                                 $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                                 $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                                 print_r(json_encode($this->response));
    //                                 return false;
    //                             }
    //                         }

    //                         if ($row[29] != 0 && $row[29] != 1 && $row[29] != 2 && $row[29] != 3 && $row[29] == "") {
    //                             $this->response['error'] = true;
    //                             $this->response['message'] = 'Not valid value for deliverable_city_type at row ' . $temp;
    //                             $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                             $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                             print_r(json_encode($this->response));
    //                             return false;
    //                         }

    //                         if ($row[27] != "" && ($row[27] == INCLUDED || $row[27] == EXCLUDED)) {
    //                             if (empty($row[28])) {
    //                                 $this->response['error'] = true;
    //                                 $this->response['message'] = 'Deliverable_zipcodes is empty at row ' . $temp;
    //                                 $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                                 $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                                 print_r(json_encode($this->response));
    //                                 return false;
    //                             }
    //                         }

    //                         if ($row[29] == INCLUDED || $row[29] == EXCLUDED) {
    //                             if (empty($row[30])) {
    //                                 $this->response['error'] = true;
    //                                 $this->response['message'] = 'Deliverable_zipcodes is empty at row ' . $temp;
    //                                 $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                                 $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                                 print_r(json_encode($this->response));
    //                                 return false;
    //                             }
    //                         }
    //                     }
    //                     $temp++;
    //                 }

    //                 fclose($handle);
    //                 $handle = fopen($csv, "r");
    //                 while (($row = fgetcsv($handle, 10000, ",")) != FALSE) //get row values
    //                 {
    //                     if ($temp1 != 0) {
    //                         $product_id = $row[0];
    //                         $product = fetch_details('products', ['id' => $product_id], '*');
    //                         if (isset($product[0]) && !empty($product[0])) {
    //                             if (!empty($row[1])) {
    //                                 $data['category_id'] = $row[1];
    //                             } else {
    //                                 $data['category_id'] = $product[0]['category_id'];
    //                             }
    //                             if (!empty($row[2])) {
    //                                 $data['tax'] = $row[2];
    //                             } else {
    //                                 $data['tax'] = $product[0]['tax'];
    //                             }
    //                             if (!empty($row[3])) {
    //                                 $data['type'] = $row[3];
    //                             } else {
    //                                 $data['type'] = $product[0]['type'];
    //                             }
    //                             if ($row[4] != '') {
    //                                 $data['stock_type'] = $row[4];
    //                             } else {
    //                                 $data['stock_type'] = $product[0]['stock_type'];
    //                             }
    //                             if (!empty($row[5])) {
    //                                 $data['name'] = $row[5];
    //                                 $data['slug'] = create_unique_slug($row[5], 'products');
    //                             } else {
    //                                 $data['name'] = $product[0]['name'];
    //                             }
    //                             if (!empty($row[6])) {
    //                                 $data['short_description'] = $row[6];
    //                             } else {
    //                                 $data['short_description'] = $product[0]['short_description'];
    //                             }
    //                             if ($row[7] != '') {
    //                                 $data['indicator'] = $row[7];
    //                             } else {
    //                                 $data['indicator'] = $product[0]['indicator'];
    //                             }
    //                             if (!empty($row[8])) {
    //                                 $data['cod_allowed'] = $row[8];
    //                             } else {
    //                                 $data['cod_allowed'] = $product[0]['cod_allowed'];
    //                             }

    //                             if (!empty($row[9])) {
    //                                 $data['minimum_order_quantity'] = $row[9];
    //                             } else {
    //                                 $data['minimum_order_quantity'] = $product[0]['minimum_order_quantity'];
    //                             }
    //                             if (!empty($row[10])) {
    //                                 $data['quantity_step_size'] = $row[10];
    //                             } else {
    //                                 $data['quantity_step_size'] = $product[0]['quantity_step_size'];
    //                             }
    //                             if ($row[11] != '') {
    //                                 $data['total_allowed_quantity'] = $row[11];
    //                             } else {
    //                                 $data['total_allowed_quantity'] = $product[0]['total_allowed_quantity'];
    //                             }
    //                             if ($row[12] != '') {
    //                                 $data['is_prices_inclusive_tax'] = $row[12];
    //                             } else {
    //                                 $data['is_prices_inclusive_tax'] = $product[0]['is_prices_inclusive_tax'];
    //                             }
    //                             if ($row[13] != '') {
    //                                 $data['is_returnable'] = $row[13];
    //                             } else {
    //                                 $data['is_returnable'] = $product[0]['is_returnable'];
    //                             }
    //                             if ($row[14] != '') {
    //                                 $data['is_cancelable'] = $row[14];
    //                             } else {
    //                                 $data['is_cancelable'] = $product[0]['is_cancelable'];
    //                             }
    //                             if (!empty($row[15])) {
    //                                 $data['cancelable_till'] = $row[15];
    //                             } else {
    //                                 $data['cancelable_till'] = $product[0]['cancelable_till'];
    //                             }
    //                             if (!empty($row[16])) {
    //                                 $data['image'] = $row[16];
    //                             } else {
    //                                 $data['image'] = $product[0]['image'];
    //                             }
    //                             if (!empty($row[17])) {
    //                                 $data['video_type'] = $row[17];
    //                             } else {
    //                                 $data['video_type'] = $product[0]['video_type'];
    //                             }
    //                             if (!empty($row[18])) {
    //                                 $data['video'] = $row[18];
    //                             } else {
    //                                 $data['video'] = $product[0]['video'];
    //                             }
    //                             if (!empty($row[19])) {
    //                                 $data['tags'] = $row[19];
    //                             } else {
    //                                 $data['tags'] = $product[0]['tags'];
    //                             }
    //                             if (!empty($row[20])) {
    //                                 $data['warranty_period'] = $row[20];
    //                             } else {
    //                                 $data['warranty_period'] = $product[0]['warranty_period'];
    //                             }
    //                             if (!empty($row[21])) {
    //                                 $data['guarantee_period'] = $row[21];
    //                             } else {
    //                                 $data['guarantee_period'] = $product[0]['guarantee_period'];
    //                             }
    //                             if (!empty($row[22])) {
    //                                 $data['made_in'] = $row[22];
    //                             } else {
    //                                 $data['made_in'] = $product[0]['made_in'];
    //                             }
    //                             if (!empty($row[23])) {
    //                                 $data['sku'] = $row[23];
    //                             } else {
    //                                 $data['sku'] = $product[0]['sku'];
    //                             }
    //                             if ($row[24] != '') {
    //                                 $data['stock'] = $row[24];
    //                             } else {
    //                                 $data['stock'] = $product[0]['stock'];
    //                             }
    //                             if ($row[25] != '') {
    //                                 $data['availability'] = $row[25];
    //                             } else {
    //                                 $data['availability'] = $product[0]['availability'];
    //                             }
    //                             if ($row[26] != '') {
    //                                 $data['description'] = $row[26];
    //                             } else {
    //                                 $data['description'] = $product[0]['description'];
    //                             }
    //                             if ($row[27] != '') {
    //                                 $data['deliverable_type'] = $row[27];
    //                             } else {
    //                                 $data['deliverable_type'] = $product[0]['deliverable_type'];
    //                             }
    //                             if ($row[27] != '' && ($row[27] == INCLUDED || $row[27] == EXCLUDED)) {
    //                                 $data['deliverable_zipcodes'] = $row[28];
    //                             } else {
    //                                 $data['deliverable_zipcodes'] = $product[0]['deliverable_zipcodes'];
    //                             }
    //                             if ($row[29] != '') {
    //                                 $data['deliverable_city_type'] = $row[29];
    //                             } else {
    //                                 $data['deliverable_city_type'] = $product[0]['deliverable_city_type'];
    //                             }
    //                             if ($row[29] != '' && ($row[27] == INCLUDED || $row[29] == EXCLUDED)) {
    //                                 $data['deliverable_cities'] = $row[30];
    //                             } else {
    //                                 $data['deliverable_cities'] = $product[0]['deliverable_cities'];
    //                             }

    //                             $this->db->where('id', $row[0])->update('products', $data);
    //                         }
    //                         $index1 = 30;
    //                         $total_variants = 0;
    //                         for ($j = 0; $j < 50; $j++) {
    //                             if (!empty($row[$index1])) {
    //                                 $total_variants++;
    //                             }
    //                             $index1 = $index1 + 6;
    //                         }
    //                         $index = 29;
    //                         for ($i = 0; $i < $total_variants; $i++) {
    //                             $variant_id = $row[$index];
    //                             $variant = fetch_details('product_variants', ['id' => $row[$index]], '*');
    //                             if (isset($variant[0]) && !empty($variant[0])) {
    //                                 $variant_data[$i]['product_id'] = $variant[0]['product_id'];
    //                                 $index++;
    //                                 if (isset($row[$index]) && !empty($row[$index])) {
    //                                     $variant_data[$i]['price'] = $row[$index];
    //                                 } else {
    //                                     $variant_data[$i]['price'] = $variant[0]['price'];
    //                                 }
    //                                 $index++;
    //                                 if (isset($row[$index]) && $row[$index] != '') {
    //                                     $variant_data[$i]['special_price'] = $row[$index];
    //                                 } else {
    //                                     $variant_data[$i]['special_price'] = $variant[0]['special_price'];
    //                                 }
    //                                 $index++;
    //                                 if (isset($row[$index]) && !empty($row[$index])) {
    //                                     $variant_data[$i]['sku'] = $row[$index];
    //                                 } else {
    //                                     $variant_data[$i]['sku'] = $variant[0]['sku'];
    //                                 }
    //                                 $index++;
    //                                 if (isset($row[$index]) && $row[$index] != '') {
    //                                     $variant_data[$i]['stock'] = $row[$index];
    //                                 } else {
    //                                     $variant_data[$i]['stock'] = $variant[0]['stock'];
    //                                 }

    //                                 $index++;
    //                                 if (isset($row[$index]) && $row[$index] != '') {
    //                                     $variant_data[$i]['availability'] = $row[$index];
    //                                 } else {
    //                                     $variant_data[$i]['availability'] = $variant[0]['availability'];
    //                                 }
    //                                 $index++;
    //                                 $this->db->where('id', $variant_id)->update('product_variants', $variant_data[$i]);
    //                             }
    //                         }
    //                     }
    //                     $temp1++;
    //                 }
    //                 fclose($handle);
    //                 $this->response['error'] = false;
    //                 $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //                 $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //                 $this->response['message'] = 'Products updated successfully!';
    //                 print_r(json_encode($this->response));
    //                 return false;
    //             }
    //         }
    //     } else {
    //         redirect('admin/login', 'refresh');
    //     }
    // }
    public function process_bulk_upload()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('create', 'product'), PERMISSION_ERROR_MSG, 'product')) {
                return false;
            }
            // $this->form_validation->set_rules('bulk_upload', '', 'xss_clean');
            $this->form_validation->set_rules('type', 'Type', 'trim|required|xss_clean');
            if (empty($_FILES['upload_file']['name'])) {
                $this->form_validation->set_rules('upload_file', 'File', 'trim|required|xss_clean', array('required' => 'Please choose file'));
            }

            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {

                // $allowed_mime_type_arr = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv');
                // $mime = get_mime_by_extension($_FILES['upload_file']['name']);
                // if (!in_array($mime, $allowed_mime_type_arr)) {
                //     $this->response['error'] = true;
                //     $this->response['csrfName'] = $this->security->get_csrf_token_name();
                //     $this->response['csrfHash'] = $this->security->get_csrf_hash();
                //     $this->response['message'] = 'Invalid file format!';
                //     print_r(json_encode($this->response));
                //     return false;
                // }
                // $csv = $_FILES['upload_file']['tmp_name'];
                // $temp = 0;
                // $temp1 = 0;
                // $handle = fopen($csv, "r");
                // $allowed_status = array("received", "processed", "shipped");
                // $video_types = array("youtube", "vimeo");
                // $this->response['message'] = '';
                // $type = $_POST['type'];

                $_POST = $this->input->post(NULL, true);

                $type = $_POST['type']; // Assuming this is related to processing logic

                $allowed_mime_type_arr = array(
                    'text/x-comma-separated-values',
                    'text/comma-separated-values',
                    'application/x-csv',
                    'text/x-csv',
                    'text/csv',
                    'application/csv',
                    'text/plain', // Allowing .txt files
                    'application/json',
                    'text/json'
                );

                $mime = get_mime_by_extension($_FILES['upload_file']['name']);

                if (!in_array($mime, $allowed_mime_type_arr)) {
                    $this->response['error'] = true;
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['message'] = 'Invalid file format!';
                    print_r(json_encode($this->response));
                    return false;
                }

                $file_path = $_FILES['upload_file']['tmp_name'];

                // Check if file is JSON
                $extension = pathinfo($_FILES['upload_file']['name'], PATHINFO_EXTENSION);

                if ($extension == 'json' || $extension == 'txt') {
                    // Read JSON file content
                    $file_content = file_get_contents($file_path);
                    if ($file_content === false) {
                        $this->response['error'] = true;
                        $this->response['message'] = 'Error reading the file!';
                        print_r(json_encode($this->response));
                        return false;
                    }

                    // Decode JSON
                    $json_data = json_decode($file_content, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->response['error'] = true;
                        $this->response['message'] = 'Invalid JSON format!';
                        print_r(json_encode($this->response));
                        return false;
                    }
                } else {
                    // Convert CSV to JSON
                    $json_data = csvToJsonProduct($file_path, $type);
                    if (!$json_data) {
                        $this->response['error'] = true;
                        $this->response['message'] = 'Error converting CSV to JSON!';
                        print_r(json_encode($this->response));
                        return false;
                    }
                }

                $csv = $_FILES['upload_file']['tmp_name'];
                $temp = 0;
                $temp1 = 0;
                $handle = fopen($csv, "r");

                $allowed_status = array("received", "processed", "shipped");
                $video_types = array("youtube", "vimeo");
                $product_types = array("simple_product", "variable_product", "digital_product");
                $this->response['message'] = '';

                if ($type == 'upload') {
                    $errors = [];
                    $pro_data = [];

                    $required_fields = [
                        'category_id',
                        'type',
                        'name',
                        'short_description',
                        'image',
                        'variants',
                    ];

                    for ($i = 0; $i < count($json_data); $i++) {
                        $row = $json_data[$i];
                        $missing_fields = [];

                        // Check for missing required fields
                        foreach ($required_fields as $field) {
                            if (!isset($row[$field]) || empty($row[$field])) {
                                $missing_fields[] = $field;
                            }
                        }

                        // Check if video_type is valid
                        if (isset($row['video_type']) && !empty($row['video_type']) && !in_array(strtolower($row['video_type']), $video_types)) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid video_type: " . $row['video_type'];
                            continue;
                        }
                        if (isset($row['video_type']) && !empty($row['video_type'])) {
                            if (!isset($row['video']) || empty($row['video'])) {
                                $missing_fields[] = 'video';
                            }
                        }

                        //check for valid category id
                        if (!is_exist(['id' => $row['category_id']], 'categories')) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid category_id: " . $row['category_id'];
                            continue;
                        }

                        //check for valid tax
                        if (isset($row['tax']) && !empty($row['tax']) && !is_exist(['id' => $row['tax']], 'taxes')) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid tax: " . $row['tax'];
                            continue;
                        }

                        //check for valid product type
                        if (!in_array($row['type'], $product_types)) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid product_type : " . $row['type'] . " it should either be one of the following : variable_product, simple_product or digital_product";
                            continue;
                        }

                        if (isset($row['stock_type']) && !empty($row['stock_type']) && !in_array($row['stock_type'], [0, 1, 2])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid stock_type : " . $row['stock_type'] . " it should either be one of the following : 0, 1 or 2";
                            continue;
                        }
                        if (isset($row['indicator']) && !empty($row['indicator']) && !in_array(intval($row['indicator']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid indicator : " . $row['indicator'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['cod_allowed']) && !empty($row['cod_allowed']) && !in_array(intval($row['cod_allowed']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid cod_allowed : " . $row['cod_allowed'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['is_prices_inclusive_tax']) && !empty($row['is_prices_inclusive_tax']) && !in_array(intval($row['is_prices_inclusive_tax']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid is_prices_inclusive_tax : " . $row['is_prices_inclusive_tax'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['is_returnable']) && !empty($row['is_returnable']) && !in_array(intval($row['is_returnable']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid is_returnable : " . $row['is_returnable'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }

                        if (isset($row['is_cancelable']) && !empty($row['is_cancelable']) && !in_array(intval($row['is_cancelable']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid is_cancelable : " . $row['is_cancelable'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['availability']) && !empty($row['availability']) && !in_array(intval($row['availability']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid availability : " . $row['availability'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['is_attachment_required']) && !empty($row['is_attachment_required']) && !in_array($row['is_attachment_required'], [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid is_attachment_required : " . $row['is_attachment_required'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }

                        if (isset($row['cancelable_till']) && !empty($row['cancelable_till']) && !in_array($row['cancelable_till'], $allowed_status)) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid cancelable_till : " . $row['cancelable_till'] . " it should either be one of the following : received, processed or shipped";
                            continue;
                        }
                        if (isset($row['minimum_order_quantity']) && !empty($row['minimum_order_quantity']) && $row['minimum_order_quantity'] < 0) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid minimum_order_quantity : " . $row['minimum_order_quantity'] . " it should be greater than 0";
                            continue;
                        }
                        if (isset($row['quantity_step_size']) && !empty($row['quantity_step_size']) && $row['quantity_step_size'] < 0) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid quantity_step_size : " . $row['quantity_step_size'] . " it should be greater than 0";
                            continue;
                        }
                        if (isset($row['total_allowed_quantity']) && !empty($row['total_allowed_quantity']) && $row['total_allowed_quantity'] < 0) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid total_allowed_quantity : " . $row['total_allowed_quantity'] . " it should be greater than 0";
                            continue;
                        }
                        if (isset($row['is_cancelable']) && !empty($row['is_cancelable']) && $row['is_cancelable'] == "1") {
                            if (!isset($row['cancelable_till']) || empty($row['cancelable_till'])) {
                                $missing_fields[] = 'cancelable_till';
                            }
                        } else {
                            $row['cancelable_till'] = '';
                            $row['is_cancelable'] = 0;
                        }

                        if (isset($row['type']) && !empty($row['type']) && $row['type'] == "simple_product") {

                            if (!isset($row['variants'][0]['price']) || empty($row['variants'][0]['price'])) {
                                $missing_fields[] = 'price';
                            }
                            if (!isset($row['variants'][0]['special_price']) || empty($row['variants'][0]['special_price'])) {
                                $missing_fields[] = 'special_price';
                            }

                            if (isset($row['stock_type']) && !empty($row['stock_type']) && $row['stock_type'] == "0") {
                                if (!isset($row['sku']) || empty($row['sku'])) {
                                    $missing_fields[] = 'sku';
                                }
                                if (!isset($row['stock']) || empty($row['stock'])) {
                                    $missing_fields[] = 'stock';
                                }
                                if (!isset($row['availability']) || empty($row['availability'])) {
                                    $missing_fields[] = 'availability';
                                }
                            }
                        } else {
                            for ($k = 0; $k < count($row['variants']); $k++) {


                                if (!isset($row['variants'][$k]['price']) || empty($row['variants'][$k]['price'])) {
                                    $missing_fields[] = 'price';
                                }
                                if (!isset($row['variants'][$k]['special_price']) || empty($row['variants'][$k]['special_price'])) {
                                    $missing_fields[] = 'special_price';
                                }
                                if (!isset($row['variants'][$k]['attribute_value_ids']) || empty($row['variants'][$k]['attribute_value_ids'])) {
                                    $missing_fields[] = 'attribute_value_ids';
                                }
                                if (isset($row['stock_type']) && !empty($row['stock_type']) && $row['stock_type'] == "2") {
                                    if (!isset($row['variants'][$k]['sku']) || empty($row['variants'][$k]['sku'])) {
                                        $missing_fields[] = 'sku';
                                    }
                                    if (!isset($row['variants'][$k]['stock']) || empty($row['variants'][$k]['stock'])) {
                                        $missing_fields[] = 'stock';
                                    }
                                    if (!isset($row['variants'][$k]['availability']) || empty($row['variants'][$k]['availability'])) {
                                        $missing_fields[] = 'availability';
                                    }
                                }
                            }
                        }

                        if (!empty($missing_fields)) {
                            $errors[] = "Record " . ($i + 1) . " is missing the following fields: " . implode(', ', $missing_fields);
                            continue;
                        }
                    }

                    // If there are errors, return them
                    if (!empty($errors)) {
                        $this->response['error'] = true;
                        $this->response['message'] = $errors;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        print_r(json_encode($this->response));
                        exit;
                    }

                    for ($i = 0; $i < count($json_data); $i++) {
                        $pro_data = [];
                        $pro_attr_data = [];
                        $row = $json_data[$i];
                        $slug = create_unique_slug($row['name'], 'products');
                        // Prepare valid data

                        $other_images = explode(',', $row['other_images']);
                        $pro_data = [
                            'name' => $row['name'],
                            'short_description' => $row['short_description'],
                            'slug' => $slug,
                            'type' => $row['type'],
                            'tax' => $row['tax'],
                            'category_id' => $row['category_id'],
                            'made_in' => $row['made_in'],
                            'brand' => $row['brand'],
                            'indicator' => $row['indicator'],
                            'image' => $row['image'],
                            'total_allowed_quantity' => $row['total_allowed_quantity'],
                            'minimum_order_quantity' => $row['minimum_order_quantity'],
                            'quantity_step_size' => $row['quantity_step_size'],
                            'warranty_period' => $row['warranty_period'],
                            'guarantee_period' => $row['guarantee_period'],
                            'other_images' => isset($row['other_images']) && !empty($row['other_images']) ? json_encode($other_images) : "[]",
                            'video_type' => $row['video_type'],
                            'video' => $row['video'],
                            'tags' => $row['tags'],
                            'status' => 2,
                            'description' => $row['description'],
                            'pickup_location' => $row['pickup_location'],
                            'is_attachment_required' => $row['is_attachment_required'],
                            'stock_type' => $row['stock_type'],
                            'is_returnable' => $row['is_returnable'],
                            'is_cancelable' => $row['is_cancelable'],
                            'cancelable_till' => $row['cancelable_till'],
                            'cod_allowed' => isset($row['cod_allowed']) && !empty($row['cod_allowed']) ? $row['cod_allowed'] : 0,
                            'is_prices_inclusive_tax' => isset($row['is_prices_inclusive_tax']) && !empty($row['is_prices_inclusive_tax']) ? $row['is_prices_inclusive_tax'] : 0,
                        ];

                        if ($row['type'] == 'simple_product') {
                            $pro_data += [
                                'sku' => $row['sku'],
                                'stock' => $row['stock'],
                                'availability' => $row['availability'],
                            ];
                        }

                        $this->db->insert('products', $pro_data);
                        $p_id = $this->db->insert_id();

                        $attribute_value_ids = '';
                        for ($k = 0; $k < count($row['variants']); $k++) {
                            $pro_variance_data = [];
                            if (isset($row['variants'][$k]['attribute_value_ids']) && !empty($row['variants'][$k]['attribute_value_ids'])) {
                                $attribute_value_ids .= ',' . $row['variants'][$k]['attribute_value_ids'];
                            }

                            $pro_variance_data = [
                                'product_id' => $p_id,
                                'attribute_value_ids' => $row['variants'][$k]['attribute_value_ids'],
                                'price' => $row['variants'][$k]['price'],
                                'special_price' => (isset($row['variants'][$k]['special_price']) && !empty($row['variants'][$k]['special_price'])) ? $row['variants'][$k]['special_price'] : $row['variants'][$k]['price'],
                                'weight' => (isset($row['variants'][$k]['weight'])) ? floatval($row['variants'][$k]['weight']) : 0,
                                'height' => (isset($row['variants'][$k]['height'])) ? $row['variants'][$k]['height'] : 0,
                                'breadth' => (isset($row['variants'][$k]['breadth'])) ? $row['variants'][$k]['breadth'] : 0,
                                'length' => (isset($row['variants'][$k]['length'])) ? $row['variants'][$k]['length'] : 0,

                            ];

                            if ($row['type'] == 'variable_product') {
                                $pro_variance_data += [
                                    'sku' => $row['variants'][$k]['sku'],
                                    'stock' => $row['variants'][$k]['stock'],
                                    'availability' => (isset($row['variants'][$k]['availability']) && !empty($row['variants'][$k]['availability'])) ? $row['variants'][$k]['availability'] : NULL,
                                    'images' => (isset($row['variants'][$k]['images']) && !empty($row['variants'][$k]['images'])) ? json_encode(explode(',', $row['variants'][$k]['images'])) : "[]",
                                ];
                            }
                            $this->db->insert('product_variants', $pro_variance_data);
                        }
                        if (isset($attribute_value_ids) && !empty($attribute_value_ids)) {
                            $product_attributes = explode(',', trim($attribute_value_ids, ','));
                            $attributes_data = implode(',', array_unique($product_attributes));
                            $pro_attr_data = [
                                'product_id' => $p_id,
                                'attribute_value_ids' => strval($attributes_data),
                            ];

                            $this->db->insert('product_attributes', $pro_attr_data);
                        }
                    }

                    $this->response['error'] = false;
                    $this->response['message'] = 'Products inserted successfully.';
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    print_r(json_encode($this->response));
                } else {
                    $errors = [];
                    $pro_data = [];

                    $required_fields = [
                        'product_id',
                        'category_id',
                        'type',
                        'name',
                        'short_description',
                        'image',
                        'variants',
                    ];

                    for ($i = 0; $i < count($json_data); $i++) {
                        $row = $json_data[$i];
                        $missing_fields = [];

                        // Check for missing required fields
                        foreach ($required_fields as $field) {
                            if (!isset($row[$field]) || empty($row[$field])) {
                                $missing_fields[] = $field;
                            }
                        }

                        // Check if video_type is valid
                        if (isset($row['video_type']) && !empty($row['video_type']) && !in_array(strtolower($row['video_type']), $video_types)) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid video_type: " . $row['video_type'];
                            continue;
                        }
                        if (isset($row['video_type']) && !empty($row['video_type'])) {
                            if (!isset($row['video']) || empty($row['video'])) {
                                $missing_fields[] = 'video';
                            }
                        }

                        //check for valid category id
                        if (!is_exist(['id' => $row['category_id']], 'categories')) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid category_id: " . $row['category_id'];
                            continue;
                        }

                        //check for valid tax
                        if (isset($row['tax']) && !empty($row['tax']) && !is_exist(['id' => $row['tax']], 'taxes')) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid tax: " . $row['tax'];
                            continue;
                        }

                        //check for valid product type
                        if (!in_array($row['type'], $product_types)) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid product_type : " . $row['type'] . " it should either be one of the following : variable_product, simple_product or digital_product";
                            continue;
                        }

                        if (isset($row['stock_type']) && !empty($row['stock_type']) && !in_array($row['stock_type'], [0, 1, 2])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid stock_type : " . $row['stock_type'] . " it should either be one of the following : 0, 1 or 2";
                            continue;
                        }
                        if (isset($row['indicator']) && !empty($row['indicator']) && !in_array(intval($row['indicator']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid indicator : " . $row['indicator'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['cod_allowed']) && !empty($row['cod_allowed']) && !in_array(intval($row['cod_allowed']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid cod_allowed : " . $row['cod_allowed'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['is_prices_inclusive_tax']) && !empty($row['is_prices_inclusive_tax']) && !in_array(intval($row['is_prices_inclusive_tax']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid is_prices_inclusive_tax : " . $row['is_prices_inclusive_tax'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['is_returnable']) && !empty($row['is_returnable']) && !in_array(intval($row['is_returnable']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid is_returnable : " . $row['is_returnable'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }

                        if (isset($row['is_cancelable']) && !empty($row['is_cancelable']) && !in_array(intval($row['is_cancelable']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid is_cancelable : " . $row['is_cancelable'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['availability']) && !empty($row['availability']) && !in_array(intval($row['availability']), [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid availability : " . $row['availability'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }
                        if (isset($row['is_attachment_required']) && !empty($row['is_attachment_required']) && !in_array($row['is_attachment_required'], [0, 1])) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid is_attachment_required : " . $row['is_attachment_required'] . " it should either be one of the following : 0 or 1";
                            continue;
                        }

                        if (isset($row['cancelable_till']) && !empty($row['cancelable_till']) && !in_array($row['cancelable_till'], $allowed_status)) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid cancelable_till : " . $row['cancelable_till'] . " it should either be one of the following : received, processed or shipped";
                            continue;
                        }
                        if (isset($row['minimum_order_quantity']) && !empty($row['minimum_order_quantity']) && $row['minimum_order_quantity'] < 0) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid minimum_order_quantity : " . $row['minimum_order_quantity'] . " it should be greater than 0";
                            continue;
                        }
                        if (isset($row['quantity_step_size']) && !empty($row['quantity_step_size']) && $row['quantity_step_size'] < 0) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid quantity_step_size : " . $row['quantity_step_size'] . " it should be greater than 0";
                            continue;
                        }
                        if (isset($row['total_allowed_quantity']) && !empty($row['total_allowed_quantity']) && $row['total_allowed_quantity'] < 0) {
                            $errors[] = "Record " . ($i + 1) . " has an invalid total_allowed_quantity : " . $row['total_allowed_quantity'] . " it should be greater than 0";
                            continue;
                        }
                        if (isset($row['is_cancelable']) && !empty($row['is_cancelable']) && $row['is_cancelable'] == "1") {
                            if (!isset($row['cancelable_till']) || empty($row['cancelable_till'])) {
                                $missing_fields[] = 'cancelable_till';
                            }
                        } else {
                            $row['cancelable_till'] = '';
                            $row['is_cancelable'] = 0;
                        }

                        if (isset($row['type']) && !empty($row['type']) && $row['type'] == "simple_product") {

                            if (!isset($row['variants'][0]['price']) || empty($row['variants'][0]['price'])) {
                                $missing_fields[] = 'price';
                            }
                            if (!isset($row['variants'][0]['special_price']) || empty($row['variants'][0]['special_price'])) {
                                $missing_fields[] = 'special_price';
                            }

                            if (isset($row['stock_type']) && !empty($row['stock_type']) && $row['stock_type'] == "0") {
                                if (!isset($row['sku']) || empty($row['sku'])) {
                                    $missing_fields[] = 'sku';
                                }
                                if (!isset($row['stock']) || empty($row['stock'])) {
                                    $missing_fields[] = 'stock';
                                }
                                if (!isset($row['availability']) || empty($row['availability'])) {
                                    $missing_fields[] = 'availability';
                                }
                            }
                        } else {
                            for ($k = 0; $k < count($row['variants']); $k++) {

                                if (!isset($row['variants'][$k]['variant_id']) || empty($row['variants'][$k]['variant_id'])) {
                                    $missing_fields[] = 'variant_id';
                                }

                                if (!isset($row['variants'][$k]['price']) || empty($row['variants'][$k]['price'])) {
                                    $missing_fields[] = 'price';
                                }
                                if (!isset($row['variants'][$k]['special_price']) || empty($row['variants'][$k]['special_price'])) {
                                    $missing_fields[] = 'special_price';
                                }
                                if (!isset($row['variants'][$k]['attribute_value_ids']) || empty($row['variants'][$k]['attribute_value_ids'])) {
                                    $missing_fields[] = 'attribute_value_ids';
                                }
                                if (isset($row['stock_type']) && !empty($row['stock_type']) && $row['stock_type'] == "2") {
                                    if (!isset($row['variants'][$k]['sku']) || empty($row['variants'][$k]['sku'])) {
                                        $missing_fields[] = 'sku';
                                    }
                                    if (!isset($row['variants'][$k]['stock']) || empty($row['variants'][$k]['stock'])) {
                                        $missing_fields[] = 'stock';
                                    }
                                    if (!isset($row['variants'][$k]['availability']) || empty($row['variants'][$k]['availability'])) {
                                        $missing_fields[] = 'availability';
                                    }
                                }
                            }
                        }

                        if (!empty($missing_fields)) {
                            $errors[] = "Record " . ($i + 1) . " is missing the following fields: " . implode(', ', $missing_fields);
                            continue;
                        }
                    }

                    // If there are errors, return them
                    if (!empty($errors)) {
                        $this->response['error'] = true;
                        $this->response['message'] = $errors;
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        print_r(json_encode($this->response));
                        exit;
                    }

                    for ($i = 0; $i < count($json_data); $i++) {
                        $pro_data = [];
                        $pro_attr_data = [];
                        $row = $json_data[$i];
                        $slug = create_unique_slug($row['name'], 'products');
                        // Prepare valid data

                        $other_images = explode(',', $row['other_images']);
                        $pro_data = [
                            'name' => $row['name'],
                            'short_description' => $row['short_description'],
                            'slug' => $slug,
                            'type' => $row['type'],
                            'tax' => $row['tax'],
                            'category_id' => $row['category_id'],
                            'made_in' => $row['made_in'],
                            'brand' => $row['brand'],
                            'indicator' => $row['indicator'],
                            'image' => $row['image'],
                            'total_allowed_quantity' => $row['total_allowed_quantity'],
                            'minimum_order_quantity' => $row['minimum_order_quantity'],
                            'quantity_step_size' => $row['quantity_step_size'],
                            'warranty_period' => $row['warranty_period'],
                            'guarantee_period' => $row['guarantee_period'],
                            'other_images' => isset($row['other_images']) && !empty($row['other_images']) ? json_encode($other_images) : "[]",
                            'video_type' => $row['video_type'],
                            'video' => $row['video'],
                            'tags' => $row['tags'],
                            'status' => 2,
                            'description' => $row['description'],
                            'pickup_location' => $row['pickup_location'],
                            'is_attachment_required' => $row['is_attachment_required'],
                            'stock_type' => $row['stock_type'],
                            'is_returnable' => $row['is_returnable'],
                            'is_cancelable' => $row['is_cancelable'],
                            'cancelable_till' => $row['cancelable_till'],
                            'cod_allowed' => isset($row['cod_allowed']) && !empty($row['cod_allowed']) ? $row['cod_allowed'] : 0,
                            'is_prices_inclusive_tax' => isset($row['is_prices_inclusive_tax']) && !empty($row['is_prices_inclusive_tax']) ? $row['is_prices_inclusive_tax'] : 0,
                        ];

                        if ($row['type'] == 'simple_product') {
                            $pro_data += [
                                'sku' => $row['sku'],
                                'stock' => $row['stock'],
                                'availability' => $row['availability'],
                            ];
                        }
                        $this->db->where('id', $row['product_id'])->update('products', $pro_data);

                        $attribute_value_ids = '';
                        for ($k = 0; $k < count($row['variants']); $k++) {
                            $pro_variance_data = [];
                            if (isset($row['variants'][$k]['attribute_value_ids']) && !empty($row['variants'][$k]['attribute_value_ids'])) {
                                $attribute_value_ids .= ',' . $row['variants'][$k]['attribute_value_ids'];
                            }

                            $pro_variance_data = [
                                'product_id' => $row['product_id'],
                                'attribute_value_ids' => $row['variants'][$k]['attribute_value_ids'],
                                'price' => $row['variants'][$k]['price'],
                                'special_price' => (isset($row['variants'][$k]['special_price']) && !empty($row['variants'][$k]['special_price'])) ? $row['variants'][$k]['special_price'] : $row['variants'][$k]['price'],
                                'weight' => (isset($row['variants'][$k]['weight'])) ? floatval($row['variants'][$k]['weight']) : 0,
                                'height' => (isset($row['variants'][$k]['height'])) ? $row['variants'][$k]['height'] : 0,
                                'breadth' => (isset($row['variants'][$k]['breadth'])) ? $row['variants'][$k]['breadth'] : 0,
                                'length' => (isset($row['variants'][$k]['length'])) ? $row['variants'][$k]['length'] : 0,
                            ];

                            if ($row['type'] == 'variable_product') {
                                $pro_variance_data += [
                                    'sku' => $row['variants'][$k]['sku'],
                                    'stock' => $row['variants'][$k]['stock'],
                                    'availability' => (isset($row['variants'][$k]['availability']) && !empty($row['variants'][$k]['availability'])) ? $row['variants'][$k]['availability'] : NULL,
                                    'images' => (isset($row['variants'][$k]['images']) && !empty($row['variants'][$k]['images'])) ? json_encode(explode(',', $row['variants'][$k]['images'])) : "[]",
                                ];
                            }

                            $this->db->where('id', $row['variants'][$k]['variant_id'])->update('product_variants', $pro_variance_data);
                        }
                        if (isset($attribute_value_ids) && !empty($attribute_value_ids)) {
                            $product_attributes = explode(',', trim($attribute_value_ids, ','));
                            $attributes_data = implode(',', array_unique($product_attributes));
                            $pro_attr_data = [
                                'product_id' => $row['product_id'],
                                'attribute_value_ids' => strval($attributes_data),
                            ];

                            $this->db->where('product_id', $row['product_id'])->update('product_attributes', $pro_attr_data);
                        }
                    }

                    $this->response['error'] = false;
                    $this->response['message'] = 'All records Updated successfully.';
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    print_r(json_encode($this->response));
                }
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }


    public function bulk_download()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {


            $this->load->model('product_model');
            $productsData = $this->product_model->getProductsAndVariants();

            $csvHeaders = [
                'product id',
                'category_id',
                'tax',
                'type',
                'stock type',
                'name',
                'short_description',
                'indicator',
                'cod_allowed',
                'minimum order quantity',
                'quantity step size',
                'total allowed quantity',
                'is prices inclusive tax',
                'is returnable',
                'is cancelable',
                'cancelable till',
                'image',
                'video_type',
                'video',
                'tags',
                'warranty period',
                'guarantee period',
                'made in',
                'sku',
                'stock',
                'availability',
                'description',
                'deliverable_type',
                'deliverable_zipcodes',
                'variant id',
                'price',
                'special price',
                ' sku',
                ' stock',
                ' availability'
            ];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=download-data.csv');

            $output = fopen('php://output', 'w');
            fputcsv($output, $csvHeaders);

            foreach ($productsData as $product) {
                foreach ($product['variants'] as $variant) {
                    $data = [
                        $product['id'],
                        $product['category_id'],
                        $product['tax'],
                        $product['type'],
                        $product['stock_type'],
                        $product['name'],
                        $product['short_description'],
                        $product['indicator'],
                        $product['cod_allowed'],
                        $product['minimum_order_quantity'],
                        $product['quantity_step_size'],
                        $product['total_allowed_quantity'],
                        $product['is_prices_inclusive_tax'],
                        $product['is_returnable'],
                        $product['is_cancelable'],
                        $product['cancelable_till'],
                        $product['image'],
                        $product['video_type'],
                        $product['video'],
                        $product['tags'],
                        $product['warranty_period'],
                        $product['guarantee_period'],
                        $product['made_in'],
                        $product['sku'],
                        $product['stock'],
                        $product['availability'],
                        $product['description'],
                        $product['deliverable_type'],
                        $product['deliverable_zipcodes'],
                        $variant['id'],
                        $variant['price'],
                        $variant['special_price'],
                        $variant['sku'],
                        $variant['stock'],
                        $variant['availability']
                    ];
                    fputcsv($output, $data);
                }
            }

            fclose($output);
        }
    }

    public function get_digital_product_data()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $status = (isset($_GET['status']) && $_GET['status'] != "") ? $this->input->get('status', true) : NULL;

            return $this->product_model->get_digital_product_details(null, $status);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function get_countries_data()
    {
        $search = $this->input->get('search');
        $response = $this->product_model->get_countries($search);
        echo json_encode($response);
    }

    public function get_sale_product_data()
    {
        $search = $this->input->get('search');
        $response = $this->product_model->get_sale_product_details($search);
        echo json_encode($response);
    }

    public function edit_product_faqs()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->form_validation->set_rules('answer', 'Answer', 'trim|required|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {
                $this->product_model->add_product_faqs($_POST);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $message = (isset($_POST['edit_product_faq'])) ? 'FAQ Updated Successfully' : 'FAQ Added Successfully';
                $this->response['message'] = $message;
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }
}
