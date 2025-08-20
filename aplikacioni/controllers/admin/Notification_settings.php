    <?php
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    defined('BASEPATH') or exit('No direct script access allowed');

    class Notification_settings extends CI_Controller
    {

        public function __construct()
        {
            parent::__construct();
            $this->load->database();
            $this->load->helper(['url', 'language', 'timezone_helper']);
            $this->load->model(['Setting_model', 'notification_model', 'category_model']);
        }

        public function index()
        {
            if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
                if (!has_permissions('read', 'notification_setting')) {
                    $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                    redirect('admin/home', 'refresh');
                }
                $this->data['main_page'] = FORMS . 'notification-settings';
                $settings = get_settings('system_settings', true);
                $this->data['title'] = 'Update Notification Settings | ' . $settings['app_name'];
                $this->data['meta_description'] = ' Update Notification Settings  | ' . $settings['app_name'];
                $this->data['fcm_server_key'] = get_settings('fcm_server_key');
                $this->data['vap_id_Key'] = get_settings('vap_id_Key');
                $this->data['firebase_project_id'] = get_settings('firebase_project_id');
                $this->data['service_account_file'] = get_settings('service_account_file');
                $this->load->view('admin/template', $this->data);
            } else {
                redirect('admin/login', 'refresh');
            }
        }

        public function manage_notifications()
        {
            if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
                if (!has_permissions('read', 'send_notification')) {
                    $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                    redirect('admin/home', 'refresh');
                }

                $this->data['main_page'] = TABLES . 'manage-notifications';
                $settings = get_settings('system_settings', true);
                $this->data['title'] = 'Send Notification | ' . $settings['app_name'];
                $this->data['meta_description'] = ' Send Notification | ' . $settings['app_name'];
                $this->data['categories'] = $this->category_model->get_categories();
                $this->data['flash_sales'] = $this->db->select('*')->where_in('status', ['1', '2'])->get('flash_sale')->result_array();
                if (isset($_GET['edit_id'])) {
                    $this->data['fetched_data'] = fetch_details('notifications', ['id' => $_GET['edit_id']]);
                }
                $this->load->view('admin/template', $this->data);
            } else {
                redirect('admin/login', 'refresh');
            }
        }

        public function get_notification_list()
        {
            if ($this->ion_auth->logged_in()) {
                return $this->notification_model->get_notification_list();
            } else {
                redirect('admin/login', 'refresh');
            }
        }

        public function get_notifications_data()
        {
            if ($this->ion_auth->logged_in()) {
                return $this->notification_model->get_notifications_data();
            } else {
                redirect('admin/login', 'refresh');
            }
        }

        public function manage_ststem_notifications()
        {
            if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
                if (!has_permissions('read', 'send_notification')) {
                    $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                    redirect('admin/home', 'refresh');
                }

                $this->data['main_page'] = TABLES . 'manage-system-notification';
                $settings = get_settings('system_settings', true);
                $this->data['title'] = 'System Notification | ' . $settings['app_name'];
                $this->data['meta_description'] = ' System Notification | ' . $settings['app_name'];

                $this->load->view('admin/template', $this->data);
            } else {
                redirect('admin/login', 'refresh');
            }
        }

        public function delete_notification()
        {
            if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

                if (print_msg(!has_permissions('delete', 'send_notification'), PERMISSION_ERROR_MSG, 'send_notification', false)) {
                    return true;
                }

                if (delete_details(['id' => $_GET['id']], 'notifications')) {
                    $response['error'] = false;
                    $response['message'] = 'Deleted Successfully';
                } else {
                    $response['error'] = true;
                    $response['message'] = 'Something Went Wrong';
                }
                echo json_encode($response);
            } else {
                redirect('admin/login', 'refresh');
            }
        }

        public function delete_system_notification()
        {
            if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

                if (print_msg(!has_permissions('delete', 'send_notification'), PERMISSION_ERROR_MSG, 'send_notification', false)) {
                    return true;
                }

                if (delete_details(['id' => $_GET['id']], 'system_notification')) {
                    $response['error'] = false;
                    $response['message'] = 'Deleted Successfully';
                } else {
                    $response['error'] = true;
                    $response['message'] = 'Something Went Wrong';
                }
                echo json_encode($response);
            } else {
                redirect('admin/login', 'refresh');
            }
        }

        public function update_notification_settings()
        {
            if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
                if (!has_permissions('read', 'notification_setting')) {
                    $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
                    redirect('admin/home', 'refresh');
                }
        
                if (print_msg(!has_permissions('update', 'notification_setting'), PERMISSION_ERROR_MSG, 'notification_setting')) {
                    return false;
                }
        
                $this->form_validation->set_rules('vap_id_Key', 'Vap Id Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('firebase_project_id', 'Firebase Project Id', 'trim|required|xss_clean');
        
                if (!$this->form_validation->run()) {
                    $this->response['error'] = true;
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['message'] = validation_errors();
                    print_r(json_encode($this->response));
                } else {
                    $file_uploaded = false;
                    $file_name = "";
        
                    if (isset($_FILES['service_account_file']) && $_FILES['service_account_file']['size'] > 0) {
                        // Check if file was uploaded without errors
                        if ($_FILES['service_account_file']['error'] === UPLOAD_ERR_OK) {
                            // Get file details
                            $fileTmpPath = $_FILES['service_account_file']['tmp_name'];
                            $fileName = $_FILES['service_account_file']['name'];
                            $fileSize = $_FILES['service_account_file']['size'];
                            $fileType = $_FILES['service_account_file']['type'];
                            $fileNameCmps = explode(".", $fileName);
                            $fileExtension = strtolower(end($fileNameCmps));
        
                            // Check if the file has a JSON extension
                            if ($fileExtension === 'json') {
                                // Move the uploaded file to a directory on the server
                                $uploadFileDir = FIREBASE_PATH;
                                $dest_path = $uploadFileDir . $fileName;
        
                                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                                    $this->response['message'] = "File is successfully uploaded.";
                                    $file_uploaded = true;
                                    $file_name = $fileName; // Store filename for updating in DB
        
                                    // Read and process the JSON file
                                    $jsonData = file_get_contents($dest_path);
                                    $data = json_decode($jsonData, true);
        
                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                        $this->response['message'] = "Error decoding JSON file.";
                                    }
                                } else {
                                    $this->response['message'] = "Error moving the uploaded file.";
                                }
                            } else {
                                $this->response['message'] = "Uploaded file is not a valid JSON file.";
                            }
                        } else {
                            $this->response['message'] = "Error during file upload: " . $_FILES['service_account_file']['error'];
                        }
                    }
        
                    // Update settings
                    $this->Setting_model->update_vapkey($_POST);
                    $this->Setting_model->update_firebase_project_id($_POST);
        
                    // Only update the service_account_file in the database if a file was actually uploaded
                    if ($file_uploaded) {
                        $this->Setting_model->update_service_account_file($file_name);
                    }
        
                    $this->response['error'] = false;
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['message'] = 'Notification Setting Updated Successfully';
                    print_r(json_encode($this->response));
                }
            } else {
                redirect('admin/login', 'refresh');
            }
        }
        
        public function send_notifications()
        {
            if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {

                if (print_msg(!has_permissions('create', 'send_notification'), PERMISSION_ERROR_MSG, 'send_notification')) {
                    return false;
                }
                $image_checkbox = $this->input->post('image_checkbox', true);
                $is_image_included = (isset($image_checkbox) && $image_checkbox == 'on') ? TRUE : FALSE;
                if ($is_image_included) {
                    $this->form_validation->set_rules('image', 'Image', 'trim|required|xss_clean', array('required' => 'Image is required'));
                }

                $this->form_validation->set_rules('title', 'Title', 'trim|required|xss_clean');
                $this->form_validation->set_rules('type', 'Type', 'trim|required|xss_clean');
                $this->form_validation->set_rules('send_to', 'Send To', 'trim|required|xss_clean');
                $this->form_validation->set_rules('message', 'Message', 'trim|required|xss_clean');

                $type = $this->input->post('type', true);
                if (isset($type) && $type == 'categories') {
                    $this->form_validation->set_rules('category_id', 'Category', 'trim|required|xss_clean');
                }
                if (isset($type) && $type ==  'products') {
                    $this->form_validation->set_rules('product_id', 'Product', 'trim|required|xss_clean');
                }
                if (isset($type) && $type == 'notification_url') {
                    $this->form_validation->set_rules('link', 'Link', 'trim|required|xss_clean|valid_url');
                }
                if (isset($type) && $type ==  'flash_sale') {
                    $this->form_validation->set_rules('sale_id', 'Sale', 'trim|required|xss_clean');
                }
                // send to specific user
                $send_to = $this->input->post('send_to', true);

                if (isset($send_to) && $send_to == 'specific_user') {
                    $this->form_validation->set_rules('select_user_id[]', 'User', 'trim|required|xss_clean', ["required" => "Please select atleast one user"]);
                }


                if (!$this->form_validation->run()) {
                    $this->response['error'] = true;
                    $this->response['message'] = validation_errors();
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    print_r(json_encode($this->response));
                    return;
                }
                $fcm_key = get_settings('fcm_server_key');

                if (empty($fcm_key)) {
                    $this->response['error'] = true;
                    $this->response['message'] = "No FCM Key Found";
                    print_r(json_encode($this->response));
                    return;
                }

                //creating a new push
                $data = $this->input->post(null, true);
                $title = $this->input->post('title', true);
                $type = $this->input->post('type', true);
                $send_to = $this->input->post('send_to', true);
                $message = $this->input->post('message', true);

                $users = 'all';
                $type_ids = '';

                $type = $this->input->post('type', true);

                if (isset($type) && $type == 'categories') {
                    $type_id = $this->input->post('category_id', true);
                } elseif (isset($type) && $type ==  'products') {
                    $type_id = $this->input->post('product_id', true);
                } elseif (isset($type) && $type ==  'flash_sale') {
                    $type_id = $this->input->post('sale_id', true);
                } else {
                    $type_id = '';
                    $type_ids = '';
                }

                if (isset($send_to) && $send_to == 'specific_user') {
                    /* select user's FCM IDs */
                    $user_ids = $this->input->post("select_user_id[]", true);
                    $results = fetch_details('users', null, 'fcm_id', 10000, 0, '', '', "id", $user_ids);
                    $result = array();
                    for ($i = 0; $i <= count($results); $i++) {
                        if (isset($results[$i]['fcm_id']) && !empty($results[$i]['fcm_id']) && ($results[$i]['fcm_id'] != 'NULL')) {
                            $res = array_merge($result, $results);
                        }
                    }
                } else {
                    /* To all users */

                    $this->ion_auth->select(["fcm_id"]);
                    $user_fcm1 = fetch_details('user_fcm', NULL, 'fcm_id');
                    $user_fcm2 = $this->ion_auth->users('members')->result_array();
                    $res = array_merge($user_fcm1, $user_fcm2);
                }

                if (empty($res)) {
                    $this->response['notification'] = [];
                    $this->response['data'] = [];
                    $this->response['error'] = true;
                    $this->response['message'] = 'There is no valid users to send notification.';
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    echo json_encode($this->response);
                    return;
                }
                $fcm_ids = array();
                foreach ($res as $fcm_id) {
                    if (!empty($fcm_id)) {
                        $fcm_ids[] = $fcm_id['fcm_id'];
                    }
                }
                $registrationIDs = $fcm_ids;

                $send_to = $this->input->post('send_to', true);
                if (isset($send_to) && $send_to == 'specific_user') {
                    $data['select_user_id'] = (isset($data['select_user_id'])) ? json_encode($data['select_user_id']) : json_encode([]);
                }

                if ($is_image_included) {
                    $notification_image_name =  $this->input->post('image', true);
                    $data['image'] = $this->input->post('image', true);
                    $this->notification_model->add_notification($data);
                } else {
                    $this->notification_model->add_notification($data);
                }

                //first check if the push has an image with it
                if ($is_image_included) {
                    $fcmMsg = array(
                        'title' => "$title",
                        'body' => "$message",
                        'type' => "$type",
                        'type_id' => "$type_id",
                        'image' => base_url()  . $notification_image_name,
                        'link' => (isset($data['link']) && !empty($data['link']) ? $data['link'] : ''),
                    );
                } else {
                    //if the push don't have an image give null in place of image
                    $fcmMsg = array(
                        'title' => "$title",
                        'body' => "$message",
                        'image' => 'null',
                        'type' => "$type",
                        'type_id' => "$type_id",
                        'link' => (isset($data['link']) && !empty($data['link']) ? $data['link'] : '')
                    );
                }

                $registrationIDs_chunks = array_chunk($registrationIDs, 1000);
                $fcmFields = send_notification($fcmMsg, $registrationIDs_chunks, $fcmMsg);

                $this->response['error'] = false;
                $this->response['message'] = 'Notification Sended Successfully';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                echo json_encode($this->response);
                return;
            } else {
                redirect('admin/login', 'refresh');
            }
        }

        public function mark_all_as_read()
        {
            if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
                return $this->notification_model->mark_all_as_read();
            } else {
                $response_data['error'] =  true;
                $response_data['message'] =  'You are not authorized to perform this action.';
                print_r(json_encode($response_data));
            }
        }
    }
