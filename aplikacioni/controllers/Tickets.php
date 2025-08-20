<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Tickets extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper']);
        $this->load->model(['ticket_model']);
        $this->data['is_logged_in'] = ($this->ion_auth->logged_in()) ? 1 : 0;
        $this->data['user'] = ($this->ion_auth->logged_in()) ? $this->ion_auth->user()->row() : array();
        $this->data['settings'] = get_settings('system_settings', true);
        $this->data['web_settings'] = get_settings('web_settings', true);
        $this->response['csrfName'] = $this->security->get_csrf_token_name();
        $this->response['csrfHash'] = $this->security->get_csrf_hash();
    }

    public function index()
    {
        if ((isset($this->data['settings']['is_web_under_maintenance']) && $this->data['settings']['is_web_under_maintenance'] == 1)) {
            redirect(base_url("maintenance"));
        }
        $this->data['main_page'] = 'home';
        $this->data['title'] = 'Home | ' . $this->data['web_settings']['site_title'];
        $this->data['keywords'] = 'Home, ' . $this->data['web_settings']['meta_keywords'];
        $this->data['description'] = 'Home | ' . $this->data['web_settings']['meta_description'];

        $limit =  12;
        $offset =  0;
        $sort = 'row_order';
        $order =  'ASC';
        $has_child_or_item = 'false';
        $filters = [];
        /* Fetching Categories Sections */
        $categories = $this->category_model->get_categories('', $limit, $offset, $sort, $order, 'false');
        $sub_category = $this->category_model->sub_categories('', $limit, $offset, $sort, $order, 'false');

        /* Fetching Featured Sections */

        $sections = $this->db->limit($limit, $offset)->order_by('row_order')->get('sections')->result_array();
        $user_id = NULL;
        if ($this->data['is_logged_in']) {
            $user_id = $this->data['user']->id;
        }
        $filters['show_only_active_products'] = true;
        if (!empty($sections)) {
            for ($i = 0; $i < count($sections); $i++) {
                $product_ids = explode(',', (string)$sections[$i]['product_ids']);
                $product_ids = explode(',', (string)$sections[$i]['product_ids']);
                $product_ids = array_filter($product_ids);
                $product_categories = (isset($sections[$i]['categories']) && !empty($sections[$i]['categories']) && $sections[$i]['categories'] != NULL) ? explode(',', $sections[$i]['categories'] ?? '') : null;
                if (isset($sections[$i]['product_type']) && !empty($sections[$i]['product_type'])) {
                    $filters['product_type'] = (isset($sections[$i]['product_type'])) ? $sections[$i]['product_type'] : null;
                }

                if ($sections[$i]['style'] == "default") {
                    $limit = 10;
                } elseif ($sections[$i]['style'] == "style_1" || $sections[$i]['style'] == "style_2") {
                    $limit = 7;
                } elseif ($sections[$i]['style'] == "style_3" || $sections[$i]['style'] == "style_4") {
                    $limit = 5;
                } else {
                    $limit = null;
                }
                $products = fetch_product($user_id, (isset($filters)) ? $filters : null, (isset($product_ids)) ? $product_ids : null, $product_categories, $limit, null, null, null);



                $sections[$i]['title'] =  output_escaping($sections[$i]['title']);
                $sections[$i]['slug'] =  url_title($sections[$i]['title'], 'dash', true);
                $sections[$i]['short_description'] =  output_escaping($sections[$i]['short_description']);
                $sections[$i]['filters'] = (isset($products['filters'])) ? $products['filters'] : [];
                $sections[$i]['product_details'] =  $products['product'];
                unset($sections[$i]['product_details'][0]['total']);
                $sections[$i]['product_details'] = $products['product'];
                unset($product_details);
            }
        }
        // fetch offers data
        $offer_slider = fetch_details('offer_sliders', '', '');
        // fetch flash_sale data
        $flash_sale = fetch_details('flash_sale', ['status' => 1]);
        if (!empty($flash_sale)) {
            for ($i = 0; $i < count($flash_sale); $i++) {
                $product_ids = explode(',', $flash_sale[$i]['product_ids'] ?? '');
                $product_ids = array_filter($product_ids);

                $sale_products = fetch_product(null, null, (isset($product_ids)) ? $product_ids : null, null, null, null, null, null);
                $flash_sale[$i]['product_details'] =  $sale_products['product'];
            }
        }
        $this->data['sections'] = $sections;
        $this->data['flash_sale'] = $flash_sale;
        $this->data['offer_slider'] = $offer_slider;
        $this->data['categories'] = $categories;
        $this->data['username'] = $this->session->userdata('username');
        $this->data['sliders'] = get_sliders();
        $this->load->view('front-end/' . THEME . '/template', $this->data);
    }



    public function tickets()
    {
        
        $this->data['main_page'] = 'tickets';
        $this->data['title'] = 'Customer Support | ' . $this->data['web_settings']['site_title'];
        $this->data['keywords'] = 'Customer Support, ' . $this->data['web_settings']['meta_keywords'];
        $this->data['description'] = 'Customer Support | ' . $this->data['web_settings']['meta_description'];
        $this->data['meta_description'] = 'Customer Support | ' . $this->data['web_settings']['site_title'];
        $this->data['ticket_types'] = fetch_details('ticket_types');
        $this->data['tickets'] = fetch_details('tickets', ['user_id' => $_SESSION['user_id']]);
        $this->load->view('front-end/' . THEME . '/template', $this->data);
    }

    public function add_ticket()
    {
        $this->form_validation->set_rules('ticket_type_id', 'Ticket Type', 'trim|required|xss_clean');
        $this->form_validation->set_rules('user_id', 'User id', 'trim|required|numeric|xss_clean');
        $this->form_validation->set_rules('subject', 'Subject', 'trim|required|xss_clean');
        $this->form_validation->set_rules('email', 'email', 'trim|required|xss_clean');
        $this->form_validation->set_rules('description', 'description', 'trim|required|xss_clean');


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = array();
        } else {
            $ticket_type_id = $this->input->post('ticket_type_id', true);
            $user_id = $this->input->post('user_id', true);
            $subject = $this->input->post('subject', true);
            $email = $this->input->post('email', true);
            $description = $this->input->post('description', true);
            $user = fetch_users($user_id);
            if (empty($user)) {
                $this->response['error'] = true;
                $this->response['message'] = "User not found!";
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = [];
                print_r(json_encode($this->response));
                return false;
            }
            $data = array(
                'ticket_type_id' => $ticket_type_id,
                'user_id' => $user_id,
                'subject' => $subject,
                'email' => $email,
                'description' => $description,
                'status' => PENDING,
            );
            $insert_id = $this->ticket_model->add_ticket($data);
            if (!empty($insert_id)) {
                $result = $this->ticket_model->get_tickets($insert_id, $ticket_type_id, $user_id);
                $this->response['error'] = false;
                $this->response['message'] =  'Question Added Successfully';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = $result['data'];
            } else {
                $this->response['error'] = true;
                $this->response['message'] =  'Ticket Not Added';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = (!empty($this->response['data'])) ? $this->response['data'] : [];
            }
        }
        print_r(json_encode($this->response));
    }

    public function update_ticket()
    {
        $ticket_status = fetch_details('tickets', ['id' =>  $_POST['edit_id']], 'status');

        $this->form_validation->set_rules('ticket_type_id', 'Ticket Type Id', 'trim|required|xss_clean');
        $this->form_validation->set_rules('id', 'Ticket Id', 'trim|xss_clean');
        $this->form_validation->set_rules('user_id', 'User id', 'trim|required|numeric|xss_clean');
        $this->form_validation->set_rules('subject', 'Subject', 'trim|required|xss_clean');
        $this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean');
        $this->form_validation->set_rules('description', 'Description', 'trim|required|xss_clean');


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            $status = $ticket_status[0]['status'];
            $ticket_id = $_POST['edit_id'];
            $user_id = $_POST['user_id'];
            $res = fetch_details('tickets', 'id=' . $ticket_id . ' and user_id=' . $user_id,  '*');
            if (empty($res)) {
                $this->response['error'] = true;
                $this->response['message'] = "User id is changed you can not udpate the ticket.";
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
            if ($status == RESOLVED && $res[0]['status'] == CLOSED) {
                $this->response['error'] = true;
                $this->response['message'] = "Current status is closed.";
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
            if ($status == REOPEN && ($res[0]['status'] == PENDING || $res[0]['status'] == OPENED)) {
                $this->response['error'] = true;
                $this->response['message'] = "Current status is pending or opened.";
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
            $ticket_type_id = $_POST['ticket_type_id'];
            $user_id = $_POST['user_id'];
            $subject = $_POST['subject'];
            $email = $_POST['email'];
            $description = $_POST['description'];
            $user = fetch_users($user_id);
            if (empty($user)) {
                $this->response['error'] = true;
                $this->response['message'] = "User not found!";
                $this->response['data'] = [];
                print_r(json_encode($this->response));
                return false;
            }
            $data = array(
                'ticket_type_id' => $ticket_type_id,
                'user_id' => $user_id,
                'subject' => $subject,
                'email' => $email,
                'description' => $description,
                'status' => $status,
                'ticket_id' => $ticket_id,
                'edit_ticket' => $ticket_id
            );
            if (!$this->ticket_model->add_ticket($data)) {
                $result = $this->ticket_model->get_tickets($ticket_id, $ticket_type_id, $user_id);
                $this->response['error'] = false;
                $this->response['message'] =  'Question updated Successfully';
                $this->response['data'] = $result['data'];
            } else {
                $this->response['error'] = true;
                $this->response['message'] =  'Ticket Not Added';
                $this->response['data'] = (!empty($this->response['data'])) ? $this->response['data'] : [];
            }
        }
        print_r(json_encode($this->response));
    }

    public function ticket_chat($ticket_id)
    {
        $tickets = fetch_details('tickets', ['id' => $ticket_id], 'user_id');
        foreach ($tickets as $user_id) {
            if ($this->ion_auth->logged_in() && $_SESSION['user_id'] == $user_id['user_id']) {

                $user_id = $_SESSION['user_id'];
                $data = $this->config->item('type');
                $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
                $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 200;
                $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
                $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $_POST['order'] : 'ASC';
                $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $_POST['sort'] : 'id';
                $result = $this->ticket_model->get_messages($ticket_id, '', $search, $offset, $limit, $sort, $order, $data, "");
                $this->data['main_page'] = 'ticket_chat';
                $this->data['title'] = 'Chat | ' . $this->data['web_settings']['site_title'];
                $this->data['keywords'] = 'Chat, ' . $this->data['web_settings']['meta_keywords'];
                $this->data['description'] = 'Chat | ' . $this->data['web_settings']['meta_description'];
                $this->data['meta_description'] = 'Chat | ' . $this->data['web_settings']['site_title'];
                $this->data['ticket_types'] = fetch_details('ticket_types');
                $this->data['tickets'] = fetch_details('tickets', ['user_id' => $_SESSION['user_id']]);
                $this->data['ticket_chats'] = $result;
                $this->data['ticket_id'] = $ticket_id;
                $this->load->view('front-end/' . THEME . '/template', $this->data);
            } else {
                redirect('home', 'refresh');
            }
        }
    }
    public function send_message()
    {
        if ($this->ion_auth->logged_in()) {
            $this->form_validation->set_rules('user_type', 'User Type', 'trim|required|xss_clean');
            $this->form_validation->set_rules('user_id', 'User id', 'trim|required|numeric|xss_clean');
            $this->form_validation->set_rules('ticket_id', 'Ticket id', 'trim|required|numeric|xss_clean');
            $this->form_validation->set_rules('message', 'message', 'trim|xss_clean'); 
            
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = strip_tags(validation_errors());
                $this->response['data'] = array();
            } else {
                $user_type = $this->input->post('user_type', true);
                $user_id = $this->input->post('user_id', true);
                $ticket_id = $this->input->post('ticket_id', true);


                $message = (isset($_POST['message']) && !empty(trim($_POST['message']))) ? $_POST['message'] : "";

                $user = fetch_users($user_id);
                if (empty($user)) {
                    $this->response['error'] = true;
                    $this->response['message'] = "User not found!";
                    $this->response['data'] = [];
                    print_r(json_encode($this->response));
                    return false;
                }

                if (!file_exists(FCPATH . TICKET_IMG_PATH)) {
                    mkdir(FCPATH . TICKET_IMG_PATH, 0777);
                }

                $temp_array = array();
                $files = $_FILES;
                $images_new_name_arr = array();
                $images_info_error = "";
                $allowed_media_types = implode('|', allowed_media_types());
                $config = [
                    'upload_path' =>  FCPATH . TICKET_IMG_PATH,
                    'allowed_types' => $allowed_media_types,
                    'max_size' => 8000,
                ];

                if (!empty($_FILES['attachments']['name'][0]) && isset($_FILES['attachments']['name'])) {
                    $other_image_cnt = count($_FILES['attachments']['name']);
                    $other_img = $this->upload;
                    $other_img->initialize($config);

                    for ($i = 0; $i < $other_image_cnt; $i++) {

                        if (!empty($_FILES['attachments']['name'][$i])) {

                            $_FILES['temp_image']['name'] = $files['attachments']['name'][$i];
                            $_FILES['temp_image']['type'] = $files['attachments']['type'][$i];
                            $_FILES['temp_image']['tmp_name'] = $files['attachments']['tmp_name'][$i];
                            $_FILES['temp_image']['error'] = $files['attachments']['error'][$i];
                            $_FILES['temp_image']['size'] = $files['attachments']['size'][$i];
                            if (!$other_img->do_upload('temp_image')) {
                                $images_info_error = 'attachments :' . $images_info_error . ' ' . $other_img->display_errors();
                            } else {
                                $temp_array = $other_img->data();
                                resize_review_images($temp_array, FCPATH . TICKET_IMG_PATH);
                                $images_new_name_arr[$i] = TICKET_IMG_PATH . $temp_array['file_name'];
                            }
                        } else {
                            $_FILES['temp_image']['name'] = $files['attachments']['name'][$i];
                            $_FILES['temp_image']['type'] = $files['attachments']['type'][$i];
                            $_FILES['temp_image']['tmp_name'] = $files['attachments']['tmp_name'][$i];
                            $_FILES['temp_image']['error'] = $files['attachments']['error'][$i];
                            $_FILES['temp_image']['size'] = $files['attachments']['size'][$i];
                            if (!$other_img->do_upload('temp_image')) {
                                $images_info_error = $other_img->display_errors();
                            }
                        }
                    }

                    //Deleting Uploaded attachments if any overall error occured
                    if ($images_info_error != NULL || !$this->form_validation->run()) {
                        if (isset($images_new_name_arr) && !empty($images_new_name_arr || !$this->form_validation->run())) {
                            foreach ($images_new_name_arr as $key => $val) {
                                unlink(FCPATH . TICKET_IMG_PATH . $images_new_name_arr[$key]);
                            }
                        }
                    }
                }

                if ($images_info_error != NULL) {
                    $this->response['error'] = true;
                    $this->response['message'] =  $images_info_error;
                    print_r(json_encode($this->response));
                    return false;
                }

                $data = array(
                    'user_type' => $user_type,
                    'user_id' => $user_id,
                    'ticket_id' => $ticket_id,
                    'message' => $message
                );
                if (!empty($_FILES['attachments']['name'][0]) && isset($_FILES['attachments']['name'])) {
                    $data['attachments'] = $images_new_name_arr;
                }

                $insert_id = $this->ticket_model->add_ticket_message($data);
                $app_settings = get_settings('system_settings', true);

                if (!empty($insert_id)) {
                    $data1 = $this->config->item('type');
                    $result = $this->ticket_model->get_messages($ticket_id, $user_id, "", "", "1", "", "", $data1, $insert_id);                    if (!empty($result)) {
                        //send custom notification message
                          $fcm_ids = array(); // Initialize the fcm_ids array
                        $user_roles = fetch_details("user_permissions", "", '*', '',  '', '', '');
                        if (!empty($user_roles)) {
                            foreach ($user_roles as $user) {
                                $user_res = fetch_details('users', ['id' => $user['user_id']],  'fcm_id');
                                if (!empty($user_res) && isset($user_res[0]['fcm_id']) && !empty($user_res[0]['fcm_id'])) {
                                    $fcm_ids[0][] = $user_res[0]['fcm_id'];
                                }
                            }
                        }

                        $custom_notification =  fetch_details('custom_notifications', ['type' => "ticket_message"], '');

                        $hashtag_application_name = '< application_name >';

                        $string = isset($custom_notification[0]['message'])?json_encode($custom_notification[0]['message'], JSON_UNESCAPED_UNICODE) :'';
                        $hashtag = html_entity_decode($string);

                        $data = str_replace($hashtag_application_name, $app_settings['app_name'], $hashtag);
                        $message = output_escaping(trim($data, '"'));

                        $fcm_admin_subject = (!empty($custom_notification)) ? $custom_notification[0]['title'] : "Attachments";
                        $fcm_admin_msg = (!empty($custom_notification)) ? $message : "Ticket Message";

                        if (!empty($fcm_ids)) {
                            $fcmMsg = array(
                                'title' => $fcm_admin_subject,
                                'body' => $fcm_admin_msg,
                                'type' => "ticket_message",
                                'type_id' => $ticket_id,
                                'chat' => json_encode($result['data']),
                            );
                            send_notification($fcmMsg, $fcm_ids, $fcmMsg);
                        }
                    }
                    $this->response['error'] = false;
                    $this->response['message'] =  'Ticket Message Added Successfully!';
                    $this->response['data'] = $result['data'][0];
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] =  'Ticket Message Not Added';
                    $this->response['data'] = (!empty($this->response['data'])) ? $this->response['data'] : [];
                }
            }
        } else {
            redirect('home', 'refresh');
        }
        print_r(json_encode($this->response));
    }

    public function get_ticket_messages()
    {
        $this->form_validation->set_data($this->input->get());
        $this->form_validation->set_rules('ticket_id', 'Ticket ID', 'trim|numeric|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $ticket_id = (isset($_GET['ticket_id']) && is_numeric($_GET['ticket_id']) && !empty(trim($_GET['ticket_id']))) ? $this->input->get('ticket_id', true) : "";
            $user_id = (isset($_GET['user_id']) && is_numeric($_GET['user_id']) && !empty(trim($_GET['user_id']))) ? $this->input->get('user_id', true) : "";
            $search = (isset($_GET['search']) && !empty(trim($_GET['search']))) ? $this->input->get('search', true) : "";
            $limit = (isset($_GET['limit']) && is_numeric($_GET['limit']) && !empty(trim($_GET['limit']))) ? $this->input->get('limit', true) : 50;
            $offset = (isset($_GET['offset']) && is_numeric($_GET['offset']) && !empty(trim($_GET['offset']))) ? $this->input->get('offset', true) : 0;
            $order = (isset($_GET['order']) && !empty(trim($_GET['order']))) ? $this->input->get('order', true) : 'DESC';
            $sort = (isset($_GET['sort']) && !empty(trim($_GET['sort']))) ? $this->input->get('sort', true) : 'id';
            $data = $this->config->item('type');
            $this->response =  $this->ticket_model->get_message_list($ticket_id, $user_id, $search, $offset, $limit, $sort, $order, $data, "");
        }
    }
}
