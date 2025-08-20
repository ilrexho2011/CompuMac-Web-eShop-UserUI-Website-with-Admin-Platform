<?php
defined('BASEPATH') or exit('No direct script access allowed');

class My_account extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library(['ion_auth', 'form_validation', 'pagination']);


        $this->load->helper(['url', 'language']);
        $this->load->model(['cart_model', 'media_model', 'chat_model', 'category_model', 'address_model', 'order_model', 'Transaction_model', 'Promo_code_model', 'Customer_model', 'Area_model']);
        $this->lang->load('auth');
        $this->data['is_logged_in'] = ($this->ion_auth->logged_in()) ? 1 : 0;
        $this->data['user'] = ($this->ion_auth->logged_in()) ? $this->ion_auth->user()->row() : array();
        $this->data['settings'] = get_settings('system_settings', true);
        $this->data['web_settings'] = get_settings('web_settings', true);
        $this->response['csrfName'] = $this->security->get_csrf_token_name();
        $this->response['csrfHash'] = $this->security->get_csrf_hash();

        if ($this->ion_auth->logged_in()) {
            $user = $this->ion_auth->user()->row();

            if (!$user->active) {
                $this->ion_auth->logout();

                $this->session->set_flashdata('authorize_flag', "Your account is deactivated. Please contact the administrator.");
                redirect('home');
            }
        }
    }


    public function index()
    {
        if ((isset($this->data['settings']['is_web_under_maintenance']) && $this->data['settings']['is_web_under_maintenance'] == 1)) {
            redirect(base_url("maintenance"));
        }
        if ($this->data['is_logged_in']) {
            $this->data['main_page'] = 'dashboard';
            $this->data['title'] = 'Dashboard | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Dashboard, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Dashboard | ' . $this->data['web_settings']['meta_description'];
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function profile()
    {
        if ($this->ion_auth->logged_in()) {
            $identity_column = $this->config->item('identity', 'ion_auth');
            $this->data['users'] = $this->ion_auth->user()->row();
            $this->data['identity_column'] = $identity_column;
            $this->data['main_page'] = 'profile';
            $this->data['title'] = 'Profile | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = $this->data['web_settings']['meta_description'];
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }


    public function orders()
    {
        if ($this->ion_auth->logged_in()) {
            $this->data['main_page'] = 'orders';
            $this->data['title'] = 'Orders | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Orders, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Orders | ' . $this->data['web_settings']['meta_description'];
            $total = fetch_orders(false, $this->data['user']->id, false, false, 1, NULL, NULL, NULL, NULL);
            $total_orders = isset($total['total']) ? (int) $total['total'] : 0;

            $limit = 5;
            $page_no = (int) ($this->uri->segment(3) ?? 1);
            if ($page_no < 1) {
                redirect(base_url('my-account/orders'));
            }
            $offset = min(($page_no - 1) * $limit, max(0, $total_orders - $limit));

            $config['base_url'] = base_url('my-account/orders');
            $config['total_rows'] = $total_orders;
            $config['per_page'] = $limit;
            $config['num_links'] = 2;
            $config['use_page_numbers'] = TRUE;
            $config['reuse_query_string'] = TRUE;
            $config['page_query_string'] = FALSE;
            $config['uri_segment'] = 3;
            $config['attributes'] = array('class' => 'page-link');

            $config['full_tag_open'] = '<ul class="pagination justify-content-center">';
            $config['full_tag_close'] = '</ul>';

            $config['first_tag_open'] = '<li class="page-item">';
            $config['first_link'] = 'First';
            $config['first_tag_close'] = '</li>';

            $config['last_tag_open'] = '<li class="page-item">';
            $config['last_link'] = 'Last';
            $config['last_tag_close'] = '</li>';

            $config['prev_tag_open'] = '<li class="page-item">';
            $config['prev_link'] = '<i class="fa fa-arrow-left"></i>';
            $config['prev_tag_close'] = '</li>';

            $config['next_tag_open'] = '<li class="page-item">';
            $config['next_link'] = '<i class="fa fa-arrow-right"></i>';
            $config['next_tag_close'] = '</li>';

            $config['cur_tag_open'] = '<li class="page-item active"><a class="page-link">';
            $config['cur_tag_close'] = '</a></li>';

            $config['num_tag_open'] = '<li class="page-item">';
            $config['num_tag_close'] = '</li>';

            if (!is_numeric($page_no)) {
                redirect(base_url('my-account/orders'));
            }

            $this->data['orders'] = fetch_orders(false, $this->data['user']->id, false, false, $limit, $offset, 'date_added', 'DESC', NULL);

            $this->pagination->initialize($config);
            $this->data['links'] =  $this->pagination->create_links();
            $this->data['payment_methods'] = get_settings('payment_method', true);
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function order_details()
    {
        if ($this->ion_auth->logged_in()) {
            $bank_transfer = array();
            $this->data['main_page'] = 'order-details';
            $this->data['title'] = 'Orders | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Orders, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Orders | ' . $this->data['web_settings']['meta_description'];
            $order_id = $this->uri->segment(3);
            $order = fetch_orders($order_id, $this->data['user']->id, false, false, 1, NULL, NULL, NULL, NULL);
            $return_reasons = fetch_details('return_reasons');
            $this->data['return_reasons'] = $return_reasons;


            if (!isset($order['order_data']) || empty($order['order_data'])) {
                redirect(base_url('my-account/orders'));
            }
            $this->data['order'] = $order['order_data'][0];
            if ($order['order_data'][0]['payment_method'] == "Bank Transfer") {
                $bank_transfer = fetch_details('order_bank_transfer', ['order_id' => $order['order_data'][0]['id']]);
            }
            $this->data['bank_transfer'] = $bank_transfer;
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function order_invoice($order_id)
    {
        $order_item_id = $this->uri->segment(3);
        $user_id = $this->data['user']->id;
        $oreder_item_data = fetch_details('order_items', ['order_id' => $order_item_id], '*');
        if ($this->ion_auth->logged_in() && $user_id == $oreder_item_data[0]['user_id']) {
            $this->data['main_page'] = VIEW . 'api-order-invoice';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Invoice Management |' . $settings['app_name'];
            $this->data['meta_description'] = 'Invoice Management | ' . $this->data['web_settings']['meta_description'];;
            if (isset($order_id) && !empty($order_id)) {
                $res = $this->order_model->get_order_details(['o.id' => $order_id], true);
                if (!empty($res)) {
                    $items = [];
                    $promo_code = [];
                    if (!empty($res[0]['promo_code'])) {
                        $promo_code = fetch_details('promo_codes', ['promo_code' => trim($res[0]['promo_code'])]);
                    }
                    foreach ($res as $row) {
                        $row = output_escaping($row);
                        $temp['product_id'] = $row['product_id'];
                        $temp['product_variant_id'] = $row['product_variant_id'];
                        $temp['pname'] = $row['pname'];
                        $temp['type'] = $row['type'];
                        $temp['quantity'] = $row['quantity'];
                        $temp['discounted_price'] = $row['discounted_price'];
                        $temp['tax_percent'] = $row['tax_percent'];
                        $temp['tax_amount'] = $row['tax_amount'];
                        $temp['price'] = $row['price'];
                        $temp['delivery_boy'] = $row['delivery_boy'];
                        $temp['active_status'] = $row['oi_active_status'];
                        array_push($items, $temp);
                    }
                    $this->data['order_detls'] = $res;
                    $this->data['items'] = $items;
                    $this->data['promo_code'] = $promo_code;
                    $this->data['print_btn_enabled'] = true;
                    $this->data['settings'] = get_settings('system_settings', true);
                    $this->load->view('admin/invoice-template', $this->data);
                } else {
                    redirect(base_url(), 'refresh');
                }
            } else {
                redirect(base_url(), 'refresh');
            }
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    // public function update_order_item_status()
    // {
    //     $this->form_validation->set_rules('order_item_id', 'Order item id', 'trim|required|numeric|xss_clean');
    //     $this->form_validation->set_rules('status', 'Status', 'trim|required|xss_clean|in_list[cancelled,returned]');
    //     if (!$this->form_validation->run()) {
    //         $this->response['error'] = true;
    //         $this->response['message'] = strip_tags(validation_errors());
    //         $this->response['csrfName'] = $this->security->get_csrf_token_name();
    //         $this->response['csrfHash'] = $this->security->get_csrf_hash();
    //         $this->response['data'] = array();
    //     } else {

    //         $this->response = $this->order_model->update_order_item($_POST['order_item_id'], trim($_POST['status']));

    //         if (trim( $_POST['status']) != 'returned' && $this->response['error'] == false) {
    //             process_refund($_POST['order_item_id'], trim($_POST['status']), 'order_items');
    //         }
    //         if ($this->response['error'] == false && trim($_POST['status']) == 'cancelled') {
    //             $data = fetch_details('order_items', ['id' => $_POST['order_item_id']], 'product_variant_id,quantity');
    //             update_stock($data[0]['product_variant_id'], $data[0]['quantity'], 'plus');
    //         }
    //     }
    //     print_r(json_encode($this->response));
    // }

    public function update_order_item_status()
    {
        $this->form_validation->set_rules('order_item_id', 'Order item id', 'trim|required|numeric|xss_clean');
        $this->form_validation->set_rules('status', 'Status', 'trim|required|xss_clean|in_list[cancelled,returned]');

        if ($_POST['status'] == 'returned') {
            $this->form_validation->set_rules('return_reason', 'Return Reason', 'trim|required|xss_clean');
            if (isset($_POST['return_reason']) && $_POST['return_reason'] == 'other') {
                $this->form_validation->set_rules('other_reason', 'Other Reason', 'trim|required|xss_clean');
            }
        }

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = [];
            print_r(json_encode($this->response));
            return;
        }

        try {
            if ($_POST['status'] == 'returned') {
                if (!file_exists(FCPATH . RETURN_IMAGES)) {
                    mkdir(FCPATH . RETURN_IMAGES, 0777, true);
                }

                $images_new_name_arr = [];
                $images_info_error = "";
                $allowed_media_types = 'jpg|png|jpeg';
                $config = [
                    'upload_path' => FCPATH . RETURN_IMAGES,
                    'allowed_types' => $allowed_media_types,
                    'max_size' => 8000,
                ];

                if (!empty($_FILES['return_item_images']['name'][0])) {
                    $this->load->library('upload', $config);
                    $file_count = count($_FILES['return_item_images']['name']);

                    for ($i = 0; $i < $file_count; $i++) {
                        if (!empty($_FILES['return_item_images']['name'][$i])) {
                            $_FILES['temp_image'] = [
                                'name' => $_FILES['return_item_images']['name'][$i],
                                'type' => $_FILES['return_item_images']['type'][$i],
                                'tmp_name' => $_FILES['return_item_images']['tmp_name'][$i],
                                'error' => $_FILES['return_item_images']['error'][$i],
                                'size' => $_FILES['return_item_images']['size'][$i]
                            ];

                            if (!$this->upload->do_upload('temp_image')) {
                                $images_info_error .= 'Image ' . ($i + 1) . ': ' . $this->upload->display_errors('', '') . ' ';
                            } else {
                                $temp_array = $this->upload->data();
                                resize_review_images($temp_array, FCPATH . RETURN_IMAGES);
                                $images_new_name_arr[] = RETURN_IMAGES . $temp_array['file_name'];
                            }
                        }
                    }

                    if ($images_info_error) {
                        foreach ($images_new_name_arr as $file) {
                            if (file_exists(FCPATH . $file)) {
                                unlink(FCPATH . $file);
                            }
                        }
                        $this->response['error'] = true;
                        $this->response['message'] = $images_info_error;
                        print_r(json_encode($this->response));
                        return;
                    }
                }

                $return_data = $_POST;
                if (isset($return_data['return_reason']) && $return_data['return_reason'] == 'other') {
                    $return_data['return_reason'] = $return_data['other_reason'];
                }
                $return_data['return_item_image'] = !empty($images_new_name_arr) ? implode(',', $images_new_name_arr) : '';
            } else {
                $return_data = []; // No return data needed for cancelled
            }

            $this->response = $this->order_model->update_order_item($_POST['order_item_id'], trim($_POST['status']), true, $return_data);
            $order_id = fetch_details('order_items', ['id' => $_POST['order_item_id']], 'id, order_id');
            $order_details = fetch_details('orders', ['id' => $order_id[0]['order_id']], 'id, payment_method');

            if (trim($_POST['status']) != 'returned' && !in_array(strtolower($order_details[0]['payment_method']), ['cod']) && $this->response['error'] == false) {
                process_refund($_POST['order_item_id'], trim($_POST['status']), 'order_items');
            }
            if ($this->response['error'] == false && trim($_POST['status']) == 'cancelled') {
                $data = fetch_details('order_items', ['id' => $_POST['order_item_id']], 'product_variant_id,quantity');
                update_stock($data[0]['product_variant_id'], $data[0]['quantity'], 'plus');
            }
        } catch (Exception $e) {
            log_message('error', 'Update order item status error: ' . $e->getMessage());
            $this->response['error'] = true;
            $this->response['message'] = 'Server error occurred. Please try again.';
        }

        print_r(json_encode($this->response));
    }

    public function update_order()
    {

        $this->form_validation->set_rules('order_id', 'Order id', 'trim|required|xss_clean');
        $this->form_validation->set_rules('status', 'Status', 'trim|required|xss_clean|in_list[cancelled,returned]');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = validation_errors();
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            $res = validate_order_status($_POST['order_id'], $_POST['status'], 'orders');

            if ($res['error']) {
                $this->response['error'] = (isset($res['return_request_flag'])) ? false : true;
                $this->response['message'] = $res['message'];
                $this->response['data'] = $res['data'];
                print_r(json_encode($this->response));
                return false;
            }
            if ($_POST['status'] == 'returned') {
                $admin_notifi = array(
                    'title' => "Return Request",
                    'message' => "Return Request for order id : " . $_POST['order_id'] . "",
                    'type' => "return_request",
                    'type_id' => $_POST['order_id']
                );
                insert_details($admin_notifi, 'system_notification');

                $_POST['status'] = 'return_request_pending';
            }


            if ($this->order_model->update_order(['status' => $_POST['status']], ['id' => $_POST['order_id']], true)) {
                $this->order_model->update_order(['active_status' => $_POST['status']], ['id' => $_POST['order_id']], false);
                if ($this->order_model->update_order(['status' => $_POST['status']], ['order_id' => $_POST['order_id']], true, 'order_items')) {
                    $this->order_model->update_order(['active_status' => $_POST['status']], ['order_id' => $_POST['order_id']], false, 'order_items');
                    process_refund($_POST['order_id'], $_POST['status'], 'orders');
                    if (trim($_POST['status'] == 'cancelled')) {
                        $data = fetch_details('order_items', ['order_id' => $_POST['order_id']], 'product_variant_id,quantity');
                        $product_variant_ids = [];
                        $qtns = [];
                        foreach ($data as $d) {
                            array_push($product_variant_ids, $d['product_variant_id']);
                            array_push($qtns, $d['quantity']);
                        }
                        $admin_notifi = array(
                            'title' => "Cancel Order",
                            'message' => "Cancel Request for order id : " . $_POST['order_id'] . "",
                            'type' => "return_request",
                            'type_id' => $_POST['order_id']
                        );
                        insert_details($admin_notifi, 'system_notification');

                        update_stock($product_variant_ids, $qtns, 'plus');
                    }

                    $this->response['error'] = false;
                    $this->response['message'] = 'Order Updated Successfully';
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return false;
                }
            }
        }
    }

    public function notifications()
    {
        if ($this->ion_auth->logged_in()) {
            $this->data['main_page'] = 'notifications';
            $this->data['title'] = 'Notification | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Notification, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Notification | ' . $this->data['web_settings']['meta_description'];
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function refer_and_earn()
    {
        if ($this->ion_auth->logged_in()) {
            $_SESSION['user_id'];
            $data = fetch_details('users', ['id' => $_SESSION['user_id']],  'mobile,password');
            if ($this->ion_auth->logged_in()) {
                $this->data['main_page'] = 'refer-and-earn';
                $this->data['title'] = 'Refer and earn | ' . $this->data['web_settings']['site_title'];
                $this->data['keywords'] = 'Refer and earn, ' . $this->data['web_settings']['meta_keywords'];
                $this->data['description'] = 'Refer and earn | ' . $this->data['web_settings']['meta_description'];
                $this->load->view('front-end/' . THEME . '/template', $this->data);
            }
        } else {
            redirect(base_url(), 'refresh');
        }
    }


    public function manage_address()
    {
        if ($this->ion_auth->logged_in()) {
            $this->data['main_page'] = 'address';
            $this->data['title'] = 'Address | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Address, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Address | ' . $this->data['web_settings']['meta_description'];
            $this->data['cities'] = get_cities();
            $this->data['areas'] = fetch_details('areas', NULL);
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }
    public function get_cities()
    {
        $search = $this->input->get('search');
        $response = $this->Area_model->get_cities_list($search);
        echo json_encode($response);
    }
    public function wallet()
    {
        if ($this->ion_auth->logged_in()) {
            $this->data['main_page'] = 'wallet';
            $this->data['title'] = 'Wallet | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Wallet, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Wallet | ' . $this->data['web_settings']['meta_description'];
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function transactions()
    {
        if ($this->ion_auth->logged_in()) {
            $this->data['main_page'] = 'transactions';
            $this->data['title'] = 'Transactions | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Transactions, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Transactions | ' . $this->data['web_settings']['meta_description'];
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function add_address()
    {
        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('type', 'Type', 'trim|xss_clean');
            $this->form_validation->set_rules('country_code', 'Country Code', 'trim|xss_clean');
            $this->form_validation->set_rules('name', 'Name', 'trim|xss_clean|required');
            $this->form_validation->set_rules('mobile', 'Mobile', 'trim|numeric|xss_clean|required');
            $this->form_validation->set_rules('alternate_mobile', 'Alternative Mobile', 'trim|numeric|xss_clean');
            $this->form_validation->set_rules('address', 'Address', 'trim|xss_clean|required');
            $this->form_validation->set_rules('landmark', 'Landmark', 'trim|xss_clean');
            $this->form_validation->set_rules('city_name', 'City', 'trim|xss_clean');
            $this->form_validation->set_rules('area_name', 'Area', 'trim|xss_clean');
            $this->form_validation->set_rules('area_id', 'Area', 'trim|xss_clean');
            $this->form_validation->set_rules('city_id', 'City', 'trim|xss_clean|required');
            $this->form_validation->set_rules('pincode', 'Pincode', 'trim|numeric|xss_clean');
            $this->form_validation->set_rules('state', 'State', 'trim|xss_clean|required');
            $this->form_validation->set_rules('country', 'Country', 'trim|xss_clean|required');
            $this->form_validation->set_rules('latitude', 'Latitude', 'trim|xss_clean');
            $this->form_validation->set_rules('longitude', 'Longitude', 'trim|xss_clean');

            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }

            $arr = $this->input->post(null, true);
            $arr['user_id'] = $this->data['user']->id;
            $this->address_model->set_address($arr);
            $res = $this->address_model->get_address($this->data['user']->id, false, true);
            $this->response['error'] = false;
            $this->response['message'] = 'Address Added Successfully';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = $res;
            print_r(json_encode($this->response));
            return false;
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Unauthorized access is not allowed';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($this->response));
            return false;
        }
    }

    public function edit_address()
    {
        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('id', 'Id', 'trim|required|numeric|xss_clean');
            $this->form_validation->set_rules('type', 'Type', 'trim|xss_clean');
            $this->form_validation->set_rules('country_code', 'Country Code', 'trim|xss_clean');
            $this->form_validation->set_rules('name', 'Name', 'trim|xss_clean');
            $this->form_validation->set_rules('mobile', 'Mobile', 'trim|numeric|xss_clean');
            $this->form_validation->set_rules('alternate_mobile', 'Alternative Mobile', 'trim|numeric|xss_clean');
            $this->form_validation->set_rules('address', 'Address', 'trim|xss_clean');
            $this->form_validation->set_rules('landmark', 'Landmark', 'trim|xss_clean');
            $this->form_validation->set_rules('area_id', 'Area', 'trim|xss_clean');
            $this->form_validation->set_rules('city_id', 'City', 'trim|xss_clean');
            $this->form_validation->set_rules('pincode', 'Pincode', 'trim|numeric|xss_clean');
            $this->form_validation->set_rules('state', 'State', 'trim|xss_clean');
            $this->form_validation->set_rules('country', 'Country', 'trim|xss_clean');

            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
            $this->address_model->set_address($_POST);
            $res = $this->address_model->get_address(null, $_POST['id'], true);
            $this->response['error'] = false;
            $this->response['message'] = 'Address updated Successfully';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = $res;
            print_r(json_encode($this->response));
            return false;
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Unauthorized access is not allowed';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($this->response));
            return false;
        }
    }

    //delete_address
    public function delete_address()
    {
        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('id', 'Id', 'trim|required|numeric|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
            $this->address_model->delete_address($_POST);
            $this->response['error'] = false;
            $this->response['message'] = 'Address Deleted Successfully';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Unauthorized access is not allowed';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($this->response));
            return false;
        }
    }

    //set default_address
    public function set_default_address()
    {
        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('id', 'Id', 'trim|required|numeric|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
            $_POST['is_default'] = true;
            $this->address_model->set_address($_POST);
            $this->response['error'] = false;
            $this->response['message'] = 'Set as default successfully';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Unauthorized access is not allowed';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($this->response));
            return false;
        }
    }

    //get_address
    public function get_address()
    {
        if ($this->ion_auth->logged_in()) {
            $res = $this->address_model->get_address($this->data['user']->id);
            $is_default_counter = array_count_values(array_column($res, 'is_default'));
            if (!empty($res)) {
                $this->response['error'] = false;
                $this->response['message'] = 'Address Retrieved Successfully';
                $this->response['data'] = $res;
            } else {
                $this->response['error'] = true;
                $this->response['message'] = "No Details Found !";
                $this->response['data'] = array();
            }
            print_r(json_encode($this->response));
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Unauthorized access is not allowed';
            print_r(json_encode($this->response));
            return false;
        }
    }
    public function get_promo_codes()
    {
        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
            $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
            $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
            $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $_POST['order'] : 'DESC';
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $_POST['sort'] : 'id';
            $this->promo['error'] = false;
            $this->promo['message'] = 'Promocodes retrived Successfully !';
            $result = $this->Promo_code_model->get_promo_codes($limit, $offset, $sort, $order);
            $this->promo['total'] = $result['total'];
            $this->promo['offset'] = (isset($offset) && !empty($offset)) ? $offset : '0';
            $this->promo['promo_codes'] = $result['data'];
            print_r(json_encode($this->promo));
            return;
        } else {
            $this->promo['error'] = true;
            $this->promo['message'] = 'Unauthorized access is not allowed';
            print_r(json_encode($this->response));
            return false;
        }
    }


    public function get_address_list()
    {
        if ($this->ion_auth->logged_in()) {
            return $this->address_model->get_address_list($this->data['user']->id);
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Unauthorized access is not allowed';
            print_r(json_encode($this->response));
            return false;
        }
    }

    public function get_areas()
    {
        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('city_id', 'City Id', 'trim|required|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
                return false;
            }

            $city_id = $this->input->post('city_id', true);
            $areas = fetch_details('areas', ['city_id' => $city_id]);
            if (empty($areas)) {
                $this->response['error'] = true;
                $this->response['message'] = "No Areas found for this City.";
                print_r(json_encode($this->response));
                return false;
            }
            $this->response['error'] = false;
            $this->response['data'] = $areas;
            print_r(json_encode($this->response));
            return false;
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Unauthorized access is not allowed';
            print_r(json_encode($this->response));
            return false;
        }
    }

    public function favorites()
    {
        if ($this->ion_auth->logged_in()) {
            $this->data['main_page'] = 'favorites';
            $this->data['title'] = 'Dashboard | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Dashboard, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Dashboard | ' . $this->data['web_settings']['meta_description'];
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->data['products'] = get_favorites($this->data['user']->id);
            $this->data['settings'] = get_settings('system_settings', true);
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function manage_favorites()
    {
        if ($this->data['is_logged_in']) {
            $this->form_validation->set_rules('product_id', 'Product Id', 'trim|numeric|required|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
            } else {
                $data = [
                    'user_id' => $this->data['user']->id,
                    'product_id' => $this->input->post('product_id', true),
                ];
                if (is_exist($data, 'favorites')) {
                    $this->db->delete('favorites', $data);
                    $favorite_count = count(get_favorites($this->data['user']->id));
                    $this->response['error']   = false;
                    $this->response['message'] = "Product removed from favorite !";
                    $this->response['data'] = [
                        'favorite_count' => $favorite_count
                    ];
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    print_r(json_encode($this->response));
                    return false;
                }
                $data = escape_array($data);
                $this->db->insert('favorites', $data);
                $favorite_count = count(get_favorites($this->data['user']->id));
                $this->response['error'] = false;
                $this->response['message'] = 'Product Added to favorite';
                $this->response['data'] = [
                    'favorite_count' => $favorite_count
                ];
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            }
        } else {
            $this->response['error'] = true;
            $this->response['message'] = "Login First to Add Products in Favorite List.";
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($this->response));
            return false;
        }
    }

    public function get_transactions()
    {
        if ($this->ion_auth->logged_in()) {
            return $this->Transaction_model->get_transactions_list($this->data['user']->id);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function get_wallet_transactions()
    {
        if ($this->ion_auth->logged_in()) {
            return $this->Transaction_model->get_transactions_list($this->data['user']->id);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function get_withdrawal_request()
    {
        if ($this->ion_auth->logged_in()) {
            return $this->Transaction_model->get_withdrawal_transactions_list($this->data['user']->id);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function get_zipcode()
    {
        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('city_id', 'City Id', 'trim|required|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
                return false;
            }

            $city_id = $this->input->post('city_id', true);

            //if zipcode table is not sync with area table then gte zipcode list from area table 
            if ($this->db->field_exists('minimum_free_delivery_order_amount', 'zipcodes')) {
                $zipcodes = fetch_details('zipcodes', ['city_id' => $city_id], 'zipcode,id');
            } else {
                $array = $this->db->select('z.zipcode,z.id as id')->join('zipcodes z', 'z.id=a.zipcode_id')->where('city_id', $city_id)->get('areas a')->result_array();
                //remove duplicate value from $array
                $zipcodes = array_map("unserialize", array_unique(array_map("serialize", $array)));
            }
            if (empty($zipcodes)) {
                $this->response['error'] = true;
                $this->response['message'] = "No Zipcodes found for this area.";
                print_r(json_encode($this->response));
                return false;
            }
            $this->response['error'] = false;
            $this->response['data'] = $zipcodes;
            print_r(json_encode($this->response));
            return false;
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Unauthorized access is not allowed';
            print_r(json_encode($this->response));
            return false;
        }
    }

    public function delete_user()
    {
        if ($this->ion_auth->logged_in()) {
            $_SESSION['user_id'];
            $data = fetch_details('users', ['id' => $_SESSION['user_id']],  'mobile,password');
            if ($this->ion_auth->logged_in()) {
                $this->data['main_page'] = 'delete account';
                $this->data['title'] = 'Delete account | ' . $this->data['web_settings']['site_title'];
                $this->data['keywords'] = 'Delete account, ' . $this->data['web_settings']['meta_keywords'];
                $this->data['description'] = 'Delete account | ' . $this->data['web_settings']['meta_description'];
                $this->load->view('front-end/' . THEME . '/template', $this->data);
            }
        }
    }

    public function delete_account()
    {

        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('mobile', 'Mobile', 'trim|xss_clean');
            $this->form_validation->set_rules('password', 'Password', 'trim|xss_clean|required');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            } else {
                $user_id = $_SESSION['user_id'];
                $data = fetch_details('users', ['id' => $user_id],  'mobile,password');

                $user_data = fetch_details('users', ['id' => $user_id, 'mobile' => $data[0]['mobile']], 'id,username,password,active,mobile');
                if ($user_data) {

                    $login = $this->ion_auth->login($user_data[0]['mobile'], $_POST['password']);
                    if ($login) {
                        $user_group = fetch_details('users_groups', ['user_id' => $user_id], 'group_id');
                        if ($user_group[0]['group_id'] == '2') {
                            $status =   'awaiting,received,processed,shipped';
                            $multiple_status = explode(',', $status);
                            $orders = fetch_orders('', $user_id,  $multiple_status);

                            foreach ($orders['order_data'] as $order) {

                                if ($this->order_model->update_order(['status' => 'cancelled'], ['id' => $order['id']], true)) {
                                    $this->order_model->update_order(['active_status' => 'cancelled'], ['id' => $order['id']], false);
                                    if ($this->order_model->update_order(['status' => 'cancelled'], ['order_id' => $order['id']], true, 'order_items')) {
                                        $this->order_model->update_order(['active_status' => 'cancelled'], ['order_id' => $order['id']], false, 'order_items');
                                        process_refund($order['id'], 'cancelled', 'orders');
                                        $data = fetch_details('order_items', ['order_id' => $order['id']], 'product_variant_id,quantity');
                                        $product_variant_ids = [];
                                        $qtns = [];
                                        foreach ($data as $d) {
                                            array_push($product_variant_ids, $d['product_variant_id']);
                                            array_push($qtns, $d['quantity']);
                                        }

                                        update_stock($product_variant_ids, $qtns, 'plus');
                                    }
                                }
                            }
                            delete_details(['id' => $user_id], 'users');
                            delete_details(['user_id' => $user_id], 'users_groups');
                            $response['error'] = false;
                            $response['message'] = 'User Deleted Successfully';
                            $this->session->sess_destroy();
                        } else if ($user_group[0]['group_id'] == '3') {
                            delete_details(['id' => $user_id], 'users');
                            delete_details(['user_id' => $user_id], 'users_groups');
                            $response['error'] = false;
                            $response['message'] = 'Account Deleted  Deleted Successfully';
                            $this->session->sess_destroy();
                        } else {
                            $response['error'] = true;
                            $response['message'] = 'Details do not match';
                        }
                    } else {
                        $response['error'] = true;
                        $response['message'] = 'Details do not match';
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = 'User Not Found';
                }
            }
            print_r(json_encode($response));
            return;
        }
    }

    public function delete_social_account()
    {
        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('session_user_id', 'user id', 'trim|xss_clean|required');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            } else {
                $user_id = $_SESSION['user_id'];
                $user_data = fetch_details('users', ['id' => $user_id], 'id,username');
                if ($user_data) {
                    $user_group = fetch_details('users_groups', ['user_id' => $user_id], 'group_id');
                    if ($user_group[0]['group_id'] == '2') {
                        $status =   'awaiting,received,processed,shipped';
                        $multiple_status = explode(',', $status);
                        $orders = fetch_orders('', $user_id,  $multiple_status);

                        foreach ($orders['order_data'] as $order) {
                            if ($this->order_model->update_order(['status' => 'cancelled'], ['id' => $order['id']], true)) {
                                $this->order_model->update_order(['active_status' => 'cancelled'], ['id' => $order['id']], false);
                                if ($this->order_model->update_order(['status' => 'cancelled'], ['order_id' => $order['id']], true, 'order_items')) {
                                    $this->order_model->update_order(['active_status' => 'cancelled'], ['order_id' => $order['id']], false, 'order_items');
                                    process_refund($order['id'], 'cancelled', 'orders');
                                    $data = fetch_details('order_items', ['order_id' => $order['id']], 'product_variant_id,quantity');
                                    $product_variant_ids = [];
                                    $qtns = [];
                                    foreach ($data as $d) {
                                        array_push($product_variant_ids, $d['product_variant_id']);
                                        array_push($qtns, $d['quantity']);
                                    }

                                    update_stock($product_variant_ids, $qtns, 'plus');
                                }
                            }
                        }
                        delete_details(['id' => $user_id], 'users');
                        delete_details(['user_id' => $user_id], 'users_groups');
                        $response['error'] = false;
                        $response['message'] = 'User Deleted Successfully';
                        $this->session->sess_destroy();
                    } else if ($user_group[0]['group_id'] == '3') {
                        delete_details(['id' => $user_id], 'users');
                        delete_details(['user_id' => $user_id], 'users_groups');
                        $response['error'] = false;
                        $response['message'] = 'Delivery Boy  Deleted Successfully';
                        $this->session->sess_destroy();
                    } else {
                        $response['error'] = true;
                        $response['message'] = 'Details do not match';
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = 'User Not Found';
                }
            }
            print_r(json_encode($response));
            return;
        }
    }
    public function tickets()
    {
        if ($this->ion_auth->logged_in()) {
            $limits = ($this->input->get('per-page')) ? $this->input->get('per-page', true) : 4;

            $total_row = fetch_details('tickets', ['user_id' => $_SESSION['user_id']], 'id');

            $total = count($total_row);

            $config['base_url'] = base_url('my_account/tickets');
            $config['total_rows'] = $total;
            $config['per_page'] = $limits;
            $config['use_page_numbers'] = TRUE;
            $config['uri_segment'] = 3;
            $config['num_links'] = 7;
            $config['use_page_numbers'] = TRUE;
            $config['reuse_query_string'] = TRUE;
            $config['page_query_string'] = FALSE;

            $config['attributes'] = array('class' => 'page-link');
            $config['full_tag_open'] = '<ul class="pagination justify-content-center">';
            $config['full_tag_close'] = '</ul>';

            $config['first_tag_open'] = '<li class="page-item">';
            $config['first_link'] = 'First';
            $config['first_tag_close'] = '</li>';

            $config['last_tag_open'] = '<li class="page-item">';
            $config['last_link'] = 'Last';
            $config['last_tag_close'] = '</li>';

            $config['prev_tag_open'] = '<li class="page-item">';
            $config['prev_link'] = '<i class="fa fa-arrow-left"></i>';
            $config['prev_tag_close'] = '</li>';

            $config['next_tag_open'] = '<li class="page-item">';
            $config['next_link'] = '<i class="fa fa-arrow-right"></i>';
            $config['next_tag_close'] = '</li>';

            $config['cur_tag_open'] = '<li class="page-item active"><a class="page-link">';
            $config['cur_tag_close'] = '</a></li>';

            $config['num_tag_open'] = '<li class="page-item">';
            $config['num_tag_close'] = '</li>';
            $page_no = (empty($this->uri->segment(3))) ? 1 : $this->uri->segment(3);
            if (!is_numeric($page_no)) {
                redirect(base_url('my_account/tickets'));
            }
            $offset = ($page_no - 1) * $limits;
            $this->pagination->initialize($config);
            $this->data['links'] =  $this->pagination->create_links();

            $this->data['main_page'] = 'tickets';
            $this->data['title'] = 'Customer Support | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Customer Support, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Customer Support | ' . $this->data['web_settings']['meta_description'];
            $this->data['meta_description'] = 'Customer Support | ' . $this->data['web_settings']['site_title'];
            $this->data['ticket_types'] = fetch_details('ticket_types');
            $this->data['tickets'] = fetch_details('tickets', ['user_id' => $_SESSION['user_id']], '*', $limits, $offset);
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function withdraw_money()
    {
        $this->form_validation->set_rules('user_id', 'User Id', 'trim|numeric|required|xss_clean');
        $this->form_validation->set_rules('payment_address', 'Payment Address', 'trim|required|xss_clean');
        $this->form_validation->set_rules('amount', 'Amount', 'trim|required|xss_clean|numeric|greater_than[0]');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $user_id = $this->input->post('user_id', true);
            $payment_address = $this->input->post('payment_address', true);
            $amount = $this->input->post('amount', true);
            $userData = fetch_details('users', ['id' => $_POST['user_id']], 'balance');

            if (!empty($userData)) {

                if ($_POST['amount'] <= $userData[0]['balance']) {

                    $data = [
                        'user_id' => $user_id,
                        'payment_address' => $payment_address,
                        'payment_type' => 'customer',
                        'amount_requested' => $amount,
                    ];


                    if (insert_details($data, 'payment_requests')) {
                        $this->Customer_model->update_balance_customer($amount, $user_id, 'deduct');
                        $userData = fetch_details('users', ['id' => $_POST['user_id']], 'balance');
                        $this->response['error'] = false;
                        $this->response['message'] = 'Withdrawal Request Send Successfully';
                        $this->response['data'] = $userData[0]['balance'];
                    } else {
                        $this->response['error'] = true;
                        $this->response['message'] = 'Cannot send Withdrawal Request.Please Try again later.';
                        $this->response['data'] = array();
                    }
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = 'You don\'t have enough balance to send the withdraw request.';
                    $this->response['data'] = array();
                }

                print_r(json_encode($this->response));
            }
        }
    }

    public function chat()
    {

        if ($this->ion_auth->logged_in()) {
            $this->data['main_page'] = 'chat';
            $this->data['title'] = 'Chat | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Chat, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Chat | ' . $this->data['web_settings']['meta_description'];

            // $settings = get_settings('system_settings', true);
            // $this->data['title'] = 'Update Notification Settings | ' . $settings['app_name'];
            // $this->data['meta_description'] = ' Update Notification Settings  | ' . $settings['app_name'];
            $this->data['fcm_server_key'] = get_settings('fcm_server_key');
            $users = $this->chat_model->get_chat_history($_SESSION['user_id'], 10, 0);
            $user = array();
            $i = 0;
            $type = 'person';
            $to_id = $this->session->userdata('user_id');

            foreach ($users as $row) {


                $from_id = $row['id'];

                if (isset($from_id) && !empty($from_id)) {
                    $unread_meg = $this->chat_model->get_unread_msg_count($type, $from_id, $to_id);
                }

                $user[$i] = $row;
                $user[$i]['unread_msg'] = $unread_meg;
                $user[$i]['picture']  = $row['username'];

                $date = strtotime('now');
                if ($to_id == $row['id']) {
                    $user[$i]['is_online'] = 1;
                } else {
                    if ($row['last_online'] > $date) {
                        $user[$i]['is_online'] = 1;
                    } else {
                        $user[$i]['is_online'] = 0;
                    }
                }
                $i++;
            }

            // if ($this->ion_auth->is_admin()) {
            //     $this->data['not_in_groups'] = $this->chat_model->get_groups_all($to_id);
            // } else {
            //     $this->data['not_in_groups'] = '';
            // }

            // $this->data['groups'] = $this->chat_model->get_groups($to_id);
            $this->data['supporters'] = $this->chat_model->get_supporters();
            $this->data['users'] = $user;
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function floating_chat_modern()
    {
        $web_doctor_brown = get_settings('web_doctor_brown', true);
        $system_settings = get_settings('system_settings', true);

        if ((isset($system_settings['is_web_under_maintenance']) && $system_settings['is_web_under_maintenance'] == 1)) {
            /* redirect him to the page where he can enter the purchase code */
            redirect(base_url("maintenance"));
        }
        if ($this->ion_auth->logged_in()) {
            $this->data['title'] = 'Transactions | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Transactions, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Transactions | ' . $this->data['web_settings']['meta_description'];

            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Update Notification Settings | ' . $settings['app_name'];
            $this->data['meta_description'] = ' Update Notification Settings  | ' . $settings['app_name'];
            $this->data['fcm_server_key'] = get_settings('fcm_server_key');

            $user = array();
            $users = $this->chat_model->get_chat_history($_SESSION['user_id'], 10, 0);
            $i = 0;
            $type = 'person';
            $to_id = $this->session->userdata('user_id');
            if (isset($users) && $users !== '') {
                foreach ($users as $row) {


                    $from_id = $row['id'];

                    if (isset($from_id) && !empty($from_id)) {
                        $unread_meg = $this->chat_model->get_unread_msg_count($type, $from_id, $to_id);
                    }

                    $user[$i] = $row;
                    $user[$i]['unread_msg'] = $unread_meg;
                    $user[$i]['picture']  = $row['username'];

                    $date = strtotime('now');
                    if ($to_id == $row['id']) {
                        $user[$i]['is_online'] = 1;
                    } else {
                        if ($row['last_online'] > $date) {
                            $user[$i]['is_online'] = 1;
                        } else {
                            $user[$i]['is_online'] = 0;
                        }
                    }
                    $i++;
                }
            }

            $this->data['supporters'] = $this->chat_model->get_supporters();
            $this->data['users'] = $user;
            $this->load->view('front-end/modern/pages/floating_chat', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }
    public function search_user()
    {
        $this->db->select('users.id, users.username');
        $this->db->from('users');
        $this->db->join('users_groups as ug', 'users.id = ug.user_id');
        $this->db->where("ug.group_id", 1);

        if (isset($_GET['search'])) {
            $this->db->like('users.username', $_GET['search']);
        }

        // Fetch users
        $fetched_records = $this->db->get();
        $users = $fetched_records->result_array();

        // Initialize Array with fetched data
        $data = array();
        foreach ($users as $user) {
            $data[] = array("id" => $user['id'], "text" => $user['username']);
        }

        echo json_encode($data);
    }

    public function update_web_fcm()
    {
        $fcm = $this->input->post('web_fcm');
        $user_id = $this->session->userdata('user_id');
        if ($this->chat_model->update_web_fcm($user_id, $fcm)) {

            $response['error'] = false;
            $response['csrfName'] = $this->security->get_csrf_token_name();
            $response['csrfHash'] = $this->security->get_csrf_hash();
            $response['message'] = 'Successful';
            return print_r(json_encode($response));
        } else {
            $response['error'] = true;
            $response['csrfName'] = $this->security->get_csrf_token_name();
            $response['csrfHash'] = $this->security->get_csrf_hash();
            $response['message'] = 'Not Successful';
            return print_r(json_encode($response));
        }
    }

    public function send_msg()
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth', 'refresh');
        } else {
            $user_id = $this->session->userdata('user_id');
            $data = array(
                'type' => $this->input->post('chat_type'),
                'from_id' => $this->session->userdata('user_id'),
                'to_id' => $this->input->post('opposite_user_id'),
                'message' => $this->input->post('chat-input-textarea'),
                'media' => '',
            );



            $msg_id = $this->chat_model->send_msg($data);

            if (!empty($_FILES['documents']['name'])) {

                $year = date('Y');
                $target_path = FCPATH . CHAT_MEDIA_PATH  . '/';
                $sub_directory = CHAT_MEDIA_PATH  . '/';

                if (!file_exists($target_path)) {
                    mkdir($target_path, 0777, true);
                }

                $temp_array = $media_ids = $other_images_new_name = array();
                $files = $_FILES;
                $other_image_info_error = "";
                $allowed_media_types = implode('|', allowed_media_types());
                $config['upload_path'] = $target_path;
                $config['allowed_types'] = $allowed_media_types;
                $other_image_cnt = count($_FILES['documents']['name']);
                $other_img = $this->upload;
                $other_img->initialize($config);
                for ($i = 0; $i < $other_image_cnt; $i++) {
                    if (!empty($_FILES['documents']['name'][$i])) {

                        $_FILES['temp_image']['name'] = $files['documents']['name'][$i];
                        $_FILES['temp_image']['type'] = $files['documents']['type'][$i];
                        $_FILES['temp_image']['tmp_name'] = $files['documents']['tmp_name'][$i];
                        $_FILES['temp_image']['error'] = $files['documents']['error'][$i];
                        $_FILES['temp_image']['size'] = $files['documents']['size'][$i];
                        if (!$other_img->do_upload('temp_image')) {
                            $other_image_info_error = $other_image_info_error . ' ' . $other_img->display_errors();
                        } else {
                            $temp_array = $other_img->data();
                            $temp_array['sub_directory'] = $sub_directory;
                            $media_ids[] = $media_id = $this->media_model->set_media($temp_array); /* set media in database */
                            if (strtolower($temp_array['image_type']) != 'gif')
                                resize_image($temp_array,  $target_path, $media_id);
                            $other_images_new_name[$i] = $temp_array['file_name'];
                        }
                        $data = array(
                            'original_file_name' => $_FILES['temp_image']['name'],
                            'file_name' => $_FILES['temp_image']['tmp_name'],
                            'file_extension' => $_FILES['temp_image']['type'],
                            'file_size' => $_FILES['temp_image']['size'],
                            'user_id' => $this->session->userdata('user_id'),
                            'message_id' => $msg_id
                        );
                        $file_id = $this->chat_model->add_file($data);
                        $this->chat_model->add_media_ids_to_msg($msg_id, $file_id);
                    } else {

                        $_FILES['temp_image']['name'] = $files['documents']['name'][$i];
                        $_FILES['temp_image']['type'] = $files['documents']['type'][$i];
                        $_FILES['temp_image']['tmp_name'] = $files['documents']['tmp_name'][$i];
                        $_FILES['temp_image']['error'] = $files['documents']['error'][$i];
                        $_FILES['temp_image']['size'] = $files['documents']['size'][$i];
                        if (!$other_img->do_upload('temp_image')) {
                            $other_image_info_error = $other_img->display_errors();
                        }
                        $data = array(
                            'original_file_name' => $_FILES['temp_image']['name'],
                            'file_name' => $_FILES['temp_image']['tmp_name'],
                            'file_extension' => $_FILES['temp_image']['type'],
                            'file_size' => $_FILES['temp_image']['size'],
                            'user_id' => $this->session->userdata('user_id'),
                            'message_id' => $msg_id
                        );
                        $file_id = $this->chat_model->add_file($data);
                        $this->chat_model->add_media_ids_to_msg($msg_id, $file_id);
                    }
                }

                // Deleting Uploaded Images if any overall error occured
                if ($other_image_info_error != NULL) {
                    if (isset($other_images_new_name) && !empty($other_images_new_name)) {
                        foreach ($other_images_new_name as $key => $val) {
                            unlink($target_path . $other_images_new_name[$key]);
                        }
                    }
                }
            }


            $messages = $this->chat_model->get_msg_by_id($msg_id, $this->input->post('opposite_user_id'), $this->session->userdata('user_id'), $this->input->post('chat_type'));
            $message = array();
            $i = 0;
            foreach ($messages as $row) {
                $message[$i] = $row;
                $media_files = $this->chat_model->get_media($row['id']);
                $message[$i]['media_files'] = !empty($media_files) ? $media_files : '';
                $message[$i]['text'] = $row['message'];
                $i++;
            }
            $new_msg = $message;

            if (!empty($msg_id)) {

                $to_id = $this->input->post('opposite_user_id');
                $from_id = $this->session->userdata('user_id');

                // single user msg
                if (($this->input->post('chat_type') == 'person') || ($this->input->post('chat_type') == 'supporter')) {
                    // this is the user who going to recive FCM msg
                    $user = fetch_details('users', ['active' => 1, 'id' => $to_id]);

                    // this is the user who going to send FCM msg 
                    $senders_info = fetch_details('users', ['active' => 1, 'id' => $this->session->userdata('user_id')]);

                    $data = $notification = array();
                    $notification['title'] = $senders_info[0]['username'];

                    $notification['senders_name'] = $senders_info[0]['username'];

                    $notification['type'] = 'message';
                    $notification['message_type'] = 'person';
                    $notification['from_id'] = $from_id;
                    $notification['to_id'] = $to_id;
                    $notification['msg_id'] = $msg_id;
                    $notification['new_msg'] = json_encode($new_msg);
                    $notification['body'] = $this->input->post('chat-input-textarea');
                    $notification['base_url'] = base_url('chat');
                    $data['data']['data'] = $notification;
                    $data['data']['webpush']['fcm_options']['link'] = base_url('chat');
                    $data['to'] = $user[0]['web_fcm'];

                    $ch = curl_init();
                    $fcm_key = get_settings('fcm_server_key');


                    $fcm_key = !empty($fcm_key) ? $fcm_key : '';

                    curl_setopt($ch, CURLOPT_POST, 1);
                    $headers = array();
                    $headers[] = "Authorization: key = " . $fcm_key;
                    $headers[] = "Content-Type: application/json";
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/fcm/send");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

                    $result['error'] = false;
                    $result['response'] = curl_exec($ch);
                    if (curl_errno($ch))
                        echo 'Error:' . curl_error($ch);

                    curl_close($ch);
                } else {

                    // group user msg
                    $group_id = $this->input->post('opposite_user_id');

                    $users = $this->chat_model->get_group_members($group_id);
                    foreach ($users as $user) {
                        $userdata = fetch_details('users', ['active' => 1, 'id' => $user['user_id']]);
                        if ($user['user_id'] != $this->session->userdata('user_id')) {
                            $fcm_ids[] = $userdata[0]['web_fcm'];
                        }
                    }

                    $registrationIDs = $fcm_ids;

                    // this is the user who going to send FCM msg
                    $senders_info = fetch_details('users', ['active' => 1, 'id' => $this->session->userdata('user_id')]);

                    $data = $notification = array();
                    $notification['title'] = '#' . $users[0]['title'] . ' - ' . $senders_info[0]['username'];

                    $notification['senders_name'] = $senders_info[0]['username'];
                    $notification['type'] = 'message';
                    $notification['message_type'] = 'group';
                    $notification['from_id'] = $from_id;
                    $notification['to_id'] = $group_id;
                    $notification['msg_id'] = $msg_id;
                    $notification['registrationIDs'] = $registrationIDs;
                    $notification['new_msg'] = json_encode($new_msg);
                    $notification['body'] = $this->input->post('chat-input-textarea');
                    $notification['base_url'] = base_url('chat');
                    $data['data']['data'] = $notification;
                    $data['data']['webpush']['fcm_options']['link'] = base_url('chat');
                    $data['registration_ids'] = $registrationIDs;

                    $ch = curl_init();
                    $fcm_key = get_settings('firebase_settings');

                    $fcm_key = !empty($fcm_key) ? json_decode($fcm_key) : '';

                    $fcm_key = !empty($fcm_key->fcm_server_key) ? $fcm_key->fcm_server_key : '';

                    curl_setopt($ch, CURLOPT_POST, 1);
                    $headers = array();
                    $headers[] = "Authorization: key = " . $fcm_key;

                    $headers[] = "Content-Type: application/json";
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/fcm/send");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

                    $result['error'] = false;

                    $this->chat_model->set_group_msg_as_unread($group_id, $this->session->userdata('user_id'));

                    $result['response'] = curl_exec($ch);
                    if (curl_errno($ch))
                        echo 'Error:' . curl_error($ch);

                    curl_close($ch);
                }

                $response['error'] = false;
                $response['message'] = 'Successful';
                $response['csrfName'] = $this->security->get_csrf_token_name();
                $response['csrfHash'] = $this->security->get_csrf_hash();
                $response['msg_id'] = $msg_id;
                $response['new_msg'] = $new_msg;

                echo json_encode($response);
            } else {
                $response['error'] = true;
                $response['message'] = 'Not Successful';
                $response['csrfName'] = $this->security->get_csrf_token_name();
                $response['csrfHash'] = $this->security->get_csrf_hash();
                echo json_encode($response);
            }
        }
    }

    public function load_chat()
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth', 'refresh');
        } else {
            $user_id = $this->session->userdata('user_id');

            $type = $this->input->post('type');
            $to_id = $this->session->userdata('user_id');
            $from_id = $this->input->post('from_id');

            $offset = (!empty($_POST['offset'])) ? $this->input->post('offset') : 0;
            $limit = (!empty($_POST['limit'])) ? $this->input->post('limit') : 100;

            $sort = (!empty($_POST['sort'])) ? $this->input->post('sort') : 'id';
            $order = (!empty($_POST['order'])) ? $this->input->post('order') : 'DESC';

            $search = (!empty($_POST['search'])) ? $this->input->post('search') : '';

            $message = array();

            $messages = $this->chat_model->load_chat($from_id, $to_id, $type,  $offset, $limit, $sort, $order, $search);
            if ($messages['total_msg'] == 0) {

                $message['error'] = true;
                $message['error_msg'] = 'No Chat OR Msg Found';
                print_r(json_encode($message));
                return false;
            }

            $i = 0;
            $message['total_msg'] = $messages['total_msg'];
            foreach ($messages['msg'] as $row) {
                $message['msg'][$i] = $row;
                $media_files = $this->chat_model->get_media($row['id']);
                $message['msg'][$i]['media_files'] = !empty($media_files) ? $media_files : '';
                $message['msg'][$i]['text'] = $row['message'];
                if ($row['from_id'] == $to_id) {
                    $message['msg'][$i]['position'] = 'right';
                } else {
                    $message['msg'][$i]['position'] = 'left';
                }
                $i++;
            }
            $message['csrfName'] = $this->security->get_csrf_token_name();
            $message['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($message));
        }
    }

    public function switch_chat()
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth', 'refresh');
        } else {
            $type = $this->input->post('type');
            $id = $this->input->post('from_id');
            $users = $this->chat_model->switch_chat($id, $type);

            $user = array();
            $i = 0;
            foreach ($users as $row) {

                $user[$i] = $row;
                if (($type == 'person') || ($type == 'supporter')) {
                    $user[$i]['picture'] = $row['username'];

                    $date = strtotime('now');

                    if ($row['last_online'] > $date) {
                        $user[$i]['is_online'] = 1;
                    } else {
                        $user[$i]['is_online'] = 0;
                    }
                }

                $i++;
            }
            $user['csrfName'] = $this->security->get_csrf_token_name();
            $user['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($user));
        }
    }

    public function mark_msg_read()
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth', 'refresh');
        } else {

            $type = $this->input->post('type');
            $to_id = $this->session->userdata('user_id');
            $from_id = $this->input->post('from_id');
            if ($this->chat_model->mark_msg_read($type, $from_id, $to_id)) {
                $response['error'] = false;
                $response['message'] = 'Successful';
                echo json_encode($response);
            } else {
                $response['error'] = true;
                $response['message'] = 'Not Successful';
                echo json_encode($response);
            }
        }
    }
    public function get_system_settings()
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth', 'refresh');
        } else {
            $response = get_settings('firebase_settings');

            echo json_encode($response);
        }
    }
    public function get_system_settings_for_web()
    {

        $vapidKey = get_settings("vap_id_Key");
        $firebase_settings = get_settings('firebase_settings');
        $response = [
            'firebase_settings' => $firebase_settings,
            'vapidKey' => $vapidKey,

        ];

        echo json_encode($response);
    }

    public function floating_chat_classic()
    {
        $web_doctor_brown = get_settings('web_doctor_brown', true);
        $system_settings = get_settings('system_settings', true);

        if ($this->ion_auth->logged_in()) {
            $this->data['title'] = 'Transactions | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Transactions, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Transactions | ' . $this->data['web_settings']['meta_description'];

            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Update Notification Settings | ' . $settings['app_name'];
            $this->data['meta_description'] = ' Update Notification Settings  | ' . $settings['app_name'];
            $this->data['fcm_server_key'] = get_settings('fcm_server_key');
            $user = array();
            $users = $this->chat_model->get_chat_history($_SESSION['user_id'], 10, 0);
            $i = 0;
            $type = 'person';
            $to_id = $this->session->userdata('user_id');

            foreach ($users as $row) {


                $from_id = $row['id'];

                if (isset($from_id) && !empty($from_id)) {
                    $unread_meg = $this->chat_model->get_unread_msg_count($type, $from_id, $to_id);
                }

                $user[$i] = $row;
                $user[$i]['unread_msg'] = $unread_meg;
                $user[$i]['picture']  = $row['username'];

                $date = strtotime('now');
                if ($to_id == $row['id']) {
                    $user[$i]['is_online'] = 1;
                } else {
                    if ($row['last_online'] > $date) {
                        $user[$i]['is_online'] = 1;
                    } else {
                        $user[$i]['is_online'] = 0;
                    }
                }
                $i++;
            }
            $this->data['supporters'] = $this->chat_model->get_supporters();
            $this->data['users'] = $user;
            $this->load->view('front-end/classic/pages/floating_chat', $this->data);
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function get_online_members()
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth', 'refresh');
        } else {
            $user_id = $this->session->userdata('user_id');

            $date = strtotime('now');
            $date = $date + 15;
            $data = array(
                'last_online' => $date
            );

            $this->chat_model->make_me_online($user_id, $data);

            $users = $this->chat_model->get_chat_history($user_id, 20, 0);


            $user_ids = explode(',', $users[0]['id']);
            $section = array_map('trim', $user_ids);
            $user_ids = $section;


            $members = $this->chat_model->get_members($user_ids);
            $member = array();
            $i = 0;

            $type = 'person';
            $to_id = $this->session->userdata('user_id');

            foreach ($members as $row) {

                $from_id = $row['id'];

                $unread_meg = $this->chat_model->get_unread_msg_count($type, $from_id, $to_id);

                $member[$i] = $row;
                $member[$i]['unread_msg'] = $unread_meg;
                $member[$i]['picture']  = isset($row['image']) ? $row['image'] : '';
                $date = strtotime('now');

                if ($row['last_online'] > $date) {
                    $member[$i]['is_online'] = 1;
                } else {
                    $member[$i]['is_online'] = 0;
                }
                $i++;
            }

            $data1['members'] = $member;

            if (!empty($member)) {
                $response['error'] = false;
                $response['data'] = $data1;
                echo json_encode($response);
            } else {
                $response['error'] = true;
                $response['message'] = 'Not Successful';
                echo json_encode($response);
            }
        }
    }
}
