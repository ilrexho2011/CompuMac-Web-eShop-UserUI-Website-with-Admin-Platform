<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Api extends CI_Controller
{

    /*
---------------------------------------------------------------------------
Defined Methods:-
---------------------------------------------------------------------------
1. login
2. get_orders
3. update_order_status
4. update_order_item_status
5. get_categories
6. get_products
7. get_customers
8. get_transactions
9. get_statistics
10. forgot_password
11. delete_order
12. get_delivery_boys
13. verify_user
14. get_settings
15. update_fcm
16. send_message
17. edit_ticket
18. get_ticket_types
19. get_tickets
20. get_messages
21. get_cities
22. get_areas_by_city_id
23. delete_order_receipt
24. get_order_tracking
25. edit_order_tracking
26. update_receipt_status
27. get_return_requests
28. update_return_request
29. manage_delivery_boy_cash_collection
30. add_product
31. upload_media
32. get_media
33. get_zipcodes
34. get_attribute_set
35. get_attributes
36. get_attribute_values
37. get_taxes
38. delete_product
39. get_countries_data
40. add_brand
41. get_brands_data
42. delete_brand
43. send_digital_product_mail
44. get_digital_order_mails
45. manage_stock
---------------------------------------------------------------------------
*/


    private  $user_details = [];

    protected $excluded_routes =
    [
        "admin/app/v1/api/login",
        "admin/app/v1/api/get_categories",
        "admin/app/v1/api/get_products",
        "admin/app/v1/api/get_customers",
        "admin/app/v1/api/forgot_password",
        "admin/app/v1/api/verify_otp",
        "admin/app/v1/api/resend_otp",
        "admin/app/v1/api/get_delivery_boys",
        "admin/app/v1/api/verify_user",
        "admin/app/v1/api/get_settings",
        "admin/app/v1/api/get_orders",
        "admin/app/v1/api/get_ticket_types",
        "admin/app/v1/api/get_cities",
        "admin/app/v1/api/get_areas_by_city_id",
        "admin/app/v1/api/get_zipcodes",
        "admin/app/v1/api/get_attribute_set",
        "admin/app/v1/api/get_attributes",
        "admin/app/v1/api/get_attribute_values",
        "admin/app/v1/api/get_taxes",
        "admin/app/v1/api/get_countries_data",
        "admin/app/v1/api/get_brands_data",
        "admin/app/v1/api/get_slider_list",
        "admin/app/v1/api/get_flash_sale",
        "admin/app/v1/api/get_offer_images",
        "admin/app/v1/api/upload_media",

    ];

    public function __construct()
    {
        parent::__construct();
        header("Content-Type: application/json");
        header("Expires: 0");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        $this->load->library(['jwt', 'ion_auth', 'form_validation', 'Key']);
        $this->load->model(['order_model', 'category_model', 'transaction_model', 'Home_model', 'customer_model', 'ticket_model', 'delivery_boy_model', 'return_request_model', 'Delivery_boy_model', 'media_model', 'Area_model', 'Attribute_model', 'product_model', 'brand_model', 'Tax_model', 'Slider_model', 'Pickup_location_model', 'faq_model', 'Flash_sale_model', 'Offer_model', 'offer_slider_model', 'Promo_code_model']);
        $this->load->helper([]);
        $this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));
        $this->lang->load('auth');
        // date_default_timezone_set('America/New_York');
        $response = $temp = $bulkdata = array();
        $this->identity_column = $this->config->item('identity', 'ion_auth');
        // initialize db tables data
        $this->tables = $this->config->item('tables', 'ion_auth');

        $current_uri =  uri_string();
        if (!in_array($current_uri, $this->excluded_routes)) {
            $token = verify_app_request();
            if ($token['error']) {
                header('Content-Type: application/json');
                http_response_code($token['status']);
                print_r(json_encode($token));
                die();
            }
            $this->user_details = $token['data'];
        }
    }


    public function index()
    {
        $this->load->helper('file');
        $this->output->set_content_type(get_mime_by_extension(base_url('admin-api-doc.txt')));
        $this->output->set_output(file_get_contents(base_url('admin-api-doc.txt')));
    }

    public function generate_token()
    {
        $payload = [
            'iat' => time(), /* issued at time */
            'iss' => 'eshop',
            'exp' => time() + (60 * 60 * 24 * 365), /* expires after 1 minute */
            'sub' => 'eshop Authentication'
        ];
        $token = $this->jwt->encode($payload, JWT_SECRET_KEY);
        print_r(json_encode($token));
    }

    public function verify_token()
    {
        try {
            $token = $this->jwt->getBearerToken();
        } catch (Exception $e) {
            $response['error'] = true;
            $response['message'] = $e->getMessage();
            print_r(json_encode($response));
            return false;
        }

        if (!empty($token)) {
            $api_keys = fetch_details('client_api_keys', ['status' => 1]);
            if (empty($api_keys)) {
                $response['error'] = true;
                $response['message'] = 'No Client(s) Data Found !';
                print_r(json_encode($response));
                return false;
            }
            JWT::$leeway = 60;
            $flag = true; //For payload indication that it return some data or throws an expection.
            $error = true; //It will indicate that the payload had verified the signature and hash is valid or not.

            $message = '';
            try {
                $payload = $this->jwt->decode($token, new Key(JWT_SECRET_KEY, 'HS256'));
                if (isset($payload->iss) && $payload->iss == 'eshop') {
                    $error = false;
                    $flag = false;
                } else {
                    $error = true;
                    $flag = false;
                    $message = 'Invalid Hash';
                }
            } catch (Exception $e) {
                $message = $e->getMessage();
            }


            if ($flag) {
                $response['error'] = true;
                $response['message'] = $message;
                print_r(json_encode($response));
                return false;
            } else {
                if ($error == true) {
                    $response['error'] = true;
                    $response['message'] = $message;
                    print_r(json_encode($response));
                    return false;
                } else {
                    return true;
                }
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Unauthorized access not allowed";
            print_r(json_encode($response));
            return false;
        }
    }

    public function login()
    {
        /* Parameters to be passed
            mobile: 6974565478
            password: 12345678
            fcm_id: FCM_ID //{ optional }
        */

        $identity_column = $this->config->item('identity', 'ion_auth');
        if ($identity_column == 'mobile') {
            $this->form_validation->set_rules('mobile', 'Mobile', 'trim|numeric|required|xss_clean');
        } elseif ($identity_column == 'email') {
            $this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean|valid_email');
        } else {
            $this->form_validation->set_rules('identity', 'Identity', 'trim|required|xss_clean');
        }
        $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
        $this->form_validation->set_rules('fcm_id', 'FCM ID', 'trim|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
            return false;
        }

        $login = $this->ion_auth->login($this->input->post('mobile'), $this->input->post('password'), false);
        if ($login) {
            $data = fetch_details('users', ['mobile' => $this->input->post('mobile', true)]);

            if ($this->ion_auth->in_group('admin', $data[0]['id'])) {
                if (isset($_POST['fcm_id']) && $_POST['fcm_id'] != '') {
                    update_details(['fcm_id' => $_POST['fcm_id']], ['mobile' => $_POST['mobile']], 'users');
                }

                /** set user jwt token  */

                $existing_token = ($data[0]['apikey'] !== null && !empty($data[0]['apikey'])) ? $data[0]['apikey'] : "";
                unset($data[0]['password']);

                /** set user jwt token  */
                if ($existing_token == '') {
                    $token = generate_token($this->input->post('mobile'));
                    update_details(['apikey' => $token], ['mobile' => $this->input->post('mobile')], "users");
                } else if (!empty($existing_token)) {

                    $api_keys = JWT_SECRET_KEY;
                    try {
                        $get_token = $this->jwt->decode($existing_token, new Key($api_keys, 'HS256'));
                        $error = false;
                        $flag = false;
                    } catch (Exception $e) {
                        $token = generate_token($this->input->post('mobile'));
                        update_details(['apikey' => $token], ['mobile' => $this->input->post('mobile')], "users");
                        $error = true;
                        $flag = false;
                        $message = 'Token Expired, new token generated';
                        $status_code = 403;
                    }
                }

                $data = array_map(function ($value) {
                    return $value === NULL ? "" : $value;
                }, $data[0]);
                //if the login is successful
                $response['error'] = false;
                $response['message'] = strip_tags($this->ion_auth->messages());
                $response['token'] =
                    $existing_token !== "" ? $existing_token : $token;
                $response['data'] = $data;
                echo json_encode($response);
                return false;
            } else {
                $response['error'] = true;
                $response['message'] = 'Mobile Number or Password is wrong.';
                echo json_encode($response);
                return false;
            }
        } else {
            // if the login was un-successful
            // just print json message
            $response['error'] = true;
            $response['message'] = 'Mobile Number or Password is wrong.';
            echo json_encode($response);
            return false;
        }
    }
    /* 2.get_orders

        id:101 { optional }
        city_id:1 { optional }
        area_id:1 { optional }
        user_id:101 { optional }
        active_status: received  {received,delivered,cancelled,processed,returned}     // optional
        start_date : 2020-09-07 or 2020/09/07 { optional }
        end_date : 2021-03-15 or 2021/03/15 { optional }
        search:keyword          // optional
        limit:25                // { default - 25 } optional
        offset:0                // { default - 0 } optional
        sort: id / date_added   // { default - id } optional
        order:DESC/ASC          // { default - DESC } optional
        download_invoice:0      // { default - 0 } optional        

    */

    public function get_orders()
    {
        $this->form_validation->set_rules('limit', 'limit', 'trim|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|xss_clean');
        $this->form_validation->set_rules('download_invoice', 'Invoice', 'trim|numeric|xss_clean');


        $limit = (isset($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 35;
        $offset = (isset($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
        $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'o.id';
        $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
        $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : '';


        $this->form_validation->set_rules('active_status', 'status', 'trim|xss_clean');


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            if (isset($_POST['active_status']) && !empty($_POST['active_status'])) {
                $where['active_status'] = $_POST['active_status'];
            }
            $id = (isset($_POST['id']) && !empty($_POST['id'])) ? $this->input->post('id', true) : false;
            $start_date = (isset($_POST['start_date']) && !empty($_POST['start_date'])) ? $this->input->post('start_date', true) : false;
            $end_date = (isset($_POST['end_date']) && !empty($_POST['end_date'])) ? $this->input->post('end_date', true) : false;
            $multiple_status =   (isset($_POST['active_status']) && !empty($_POST['active_status'])) ? explode(',', $this->input->post('active_status', true)) : false;
            $download_invoice =   (isset($_POST['download_invoice']) && !empty($_POST['download_invoice'])) ? $this->input->post('download_invoice', true) : 1;
            $city_id =   (isset($_POST['city_id']) && !empty($_POST['city_id'])) ? $this->input->post('city_id', true) : null;
            $area_id =   (isset($_POST['area_id']) && !empty($_POST['area_id'])) ? $this->input->post('area_id', true) : null;
            $order_type =   (isset($_POST['order_type']) && !empty($_POST['order_type'])) ? strtolower($this->input->post('order_type', true)) : '';


            $order_details = fetch_orders($id, "", $multiple_status, false, trim($limit), trim($offset), $sort, $order, $download_invoice, $start_date, $end_date, $search, $city_id, $area_id, $order_type);
            if (!empty($order_details['order_data'])) {
                $this->response['error'] = false;
                $this->response['message'] = 'Data retrieved successfully';
                $this->response['total'] = $order_details['total'];
                $this->response['awaiting'] = strval(orders_count("awaiting"));
                $this->response['received'] = strval(orders_count("received"));
                $this->response['processed'] = strval(orders_count("processed"));
                $this->response['shipped'] = strval(orders_count("shipped"));
                $this->response['delivered'] = strval(orders_count("delivered"));
                $this->response['cancelled'] = strval(orders_count("cancelled"));
                $this->response['returned'] = strval(orders_count("returned"));
                $this->response['data'] = $order_details['order_data'];
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Data Does Not Exists';
                $this->response['total'] = "0";
                $this->response['awaiting'] = "0";
                $this->response['received'] = "0";
                $this->response['processed'] = "0";
                $this->response['shipped'] = "0";
                $this->response['delivered'] = "0";
                $this->response['cancelled'] = "0";
                $this->response['returned'] = "0";
                $this->response['data'] = array();
            }
        }
        print_r(json_encode($this->response));
    }

    /* to update the status of complete order */
    public function update_order_status()
    {
        /*
            order_id:1
            status : received / processed / shipped / delivered / cancelled / returned
            delivery_boy_id: 15 {optional}
         */

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('order_id', 'Order Id', 'trim|numeric|required|xss_clean');
        $this->form_validation->set_rules('delivery_boy_id', 'Delvery Boy Id', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('status', 'Status', 'trim|required|xss_clean|in_list[received,processed,shipped,delivered,cancelled,returned]');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }
        $order = fetch_details('orders', ['id' => $_POST['order_id']], '*');

        if (empty($order)) {
            $this->response['error'] = true;
            $this->response['message'] = 'No Order Found';
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }
        // check for bank receipt if available
        $order_method = fetch_details('orders', ['id' => $_POST['order_id']], 'payment_method');
        if ($order_method[0]['payment_method'] == 'bank_transfer') {
            $bank_receipt = fetch_details('order_bank_transfer', ['order_id' => $_POST['order_id']]);
            $transaction_status = fetch_details(['order_id' => $_POST['order_id']], 'transactions', 'status');
            if ($this->input->post('status', true) != 'cancelled' && (empty($bank_receipt) || strtolower($transaction_status[0]['status']) != 'success' || $bank_receipt[0]['status'] == "0" || $bank_receipt[0]['status'] == "1")) {
                $this->response['eror'] = true;
                $this->response['message'] = "Order Status can not update, Bank verification is remain from transactions.";
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
        }
        $message = '';
        $delivery_boy_updated = 0;
        $delivery_boy_id = $this->input->post('delivery_boy_id');
        if (!empty($delivery_boy_id)) {
            $delivery_boy = fetch_details('users', ['id' => $this->input->post('delivery_boy_id')], '*');
            if (empty($delivery_boy)) {
                $this->response['error'] = true;
                $this->response['message'] = "Invalid Delivery boy id";
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            } else {
                $where = "id = " . $_POST['order_id'] . "";
                $current_delivery_boy = fetch_details('orders', $where, 'delivery_boy_id');
                $settings = get_settings('system_settings', true);
                $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                $user_res = fetch_details('users', ['id' => $_POST['delivery_boy_id']], 'fcm_id,username');
                $fcm_ids = array();
                if (isset($user_res[0]) && !empty($user_res[0])) {
                    // send custom notification message
                    if (isset($current_delivery_boy[0]['delivery_boy_id']) && $current_delivery_boy[0]['delivery_boy_id'] == $this->input->post('delivery_boy_id', true)) {

                        if ($this->input->post('status', true) == 'received') {
                            $type = ['type' => "customer_order_received"];
                        } elseif ($this->input->post('status', true) == 'processed') {
                            $type = ['type' => "customer_order_processed"];
                        } elseif ($this->input->post('status', true) == 'shipped') {
                            $type = ['type' => "customer_order_shipped"];
                        } elseif ($this->input->post('status', true) == 'delivered') {
                            $type = ['type' => "customer_order_delivered"];
                        } elseif ($this->input->post('status', true) == 'cancelled') {
                            $type = ['type' => "customer_order_cancelled"];
                        } elseif ($this->input->post('status', true) == 'returned') {
                            $type = ['type' => "customer_order_returned"];
                        }

                        $custom_notification = fetch_details('custom_notifications', $type, '');

                        $hashtag_cutomer_name = '< cutomer_name >';
                        $hashtag_order_id = '< order_id >';
                        $hashtag_application_name = '< application_name >';

                        $string = json_encode($custom_notification[0]['message'], JSON_UNESCAPED_UNICODE);
                        $hashtag = html_entity_decode($string);

                        $data = str_replace(array($hashtag_cutomer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]['username'], $_POST['orderid'], $app_name), $hashtag);
                        $message = output_escaping(trim($data, '"'));

                        $customer_msg = (!empty($custom_notification)) ? $message :  'Hello Dear ' . $user_res[0]['username'] . ' order status updated to ' . $_POST['status'] . ' for order ID #' . $_POST['order_id'] . ' assigned to you please take note of it! Thank you. Regards ' . $app_name . '';

                        $fcmMsg = array(
                            'title' => (!empty($custom_notification)) ? $custom_notification[0]['title'] : "Order status updated",
                            'body' => $customer_msg,
                            'type' => "order"
                        );
                    } else {

                        $custom_notification =  fetch_details('custom_notifications', ['type' => "delivery_boy_order_deliver"], '');

                        $hashtag_cutomer_name = '< cutomer_name >';
                        $hashtag_order_id = '< order_id >';
                        $hashtag_application_name = '< application_name >';

                        $string = json_encode($custom_notification[0]['message'], JSON_UNESCAPED_UNICODE);
                        $hashtag = html_entity_decode($string);

                        $data = str_replace(array($hashtag_cutomer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]['username'], $_POST['orderid'], $app_name), $hashtag);
                        $message = output_escaping(trim($data, '"'));

                        $customer_msg = (!empty($custom_notification)) ? $message : 'Hello Dear ' . $user_res[0]['username'] . ' you have new order to be deliver order ID #' . $_POST['order_id'] . ' please take note of it! Thank you. Regards ' . $app_name . '';

                        $fcmMsg = array(
                            'title' => (!empty($custom_notification)) ? $custom_notification[0]['title'] : "You have new order to deliver",
                            'body' => $customer_msg,
                            'type' => "order"
                        );
                        $message = 'Delivery Boy Updated ';
                        $delivery_boy_updated = 1;
                    }
                }
                if (!empty($user_res[0]['fcm_id'])) {
                    $fcm_ids[0][] = $user_res[0]['fcm_id'];
                    send_notification($fcmMsg, $fcm_ids, $fcmMsg);
                }
                $where = [
                    'id' => $this->input->post('order_id', true)
                ];
                if ($this->order_model->update_order(['delivery_boy_id' => $_POST['delivery_boy_id']], $where)) {
                    $delivery_error = false;
                }
            }
        }

        $res = validate_order_status($this->input->post('order_id', true), $this->input->post('status', true), 'orders');
        if ($res['error']) {
            $this->response['error'] = $delivery_boy_updated == 1 ? false : true;
            $this->response['message'] = $message . $res['message'];
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }


        $priority_status = [
            'received' => 0,
            'processed' => 1,
            'shipped' => 2,
            'delivered' => 3,
            'cancelled' => 4,
            'returned' => 5,
        ];

        $update_status = 1;
        $error = TRUE;
        $message = '';

        $where_id = "id = " . $this->input->post('order_id', true) . " and (active_status != 'cancelled' and active_status != 'returned' ) ";
        $where_order_id = "order_id = " . $_POST['order_id'] . " and (active_status != 'cancelled' and active_status != 'returned' ) ";

        $order_items_details = fetch_details('order_items', $where_order_id,  'active_status');
        $counter = count($order_items_details);
        $cancel_counter = 0;
        foreach ($order_items_details as $row) {
            if ($row['active_status'] == 'cancelled') {
                ++$cancel_counter;
            }
        }
        if ($cancel_counter == $counter) {
            $update_status = 0;
        }

        if (isset($_POST['order_id']) && isset($_POST['status'])) {
            if ($update_status == 1) {

                $order = fetch_details('orders', $where_id,  'user_id,active_status');
                $user_id = $order[0]['user_id'];
                $current_orders_status = $order[0]['active_status'];

                if ($priority_status[$this->input->post('status', true)] > $priority_status[$current_orders_status]) {
                    $set = [
                        'status' => $this->input->post('status', true) // status => 'proceesed'
                    ];

                    // Update Active Status of Order Table										
                    if ($this->order_model->update_order($set, $where_id, true)) {
                        if ($this->order_model->update_order(['active_status' => $this->input->post('status', true)], $where_id)) {
                            if ($this->order_model->update_order($set, $where_order_id, true, 'order_items')) {
                                if ($this->order_model->update_order(['active_status' => $this->input->post('status', true)], $where_order_id, false, 'order_items')) {
                                    $error = false;
                                }
                            }
                        }
                    }
                    if ($error == false) {
                        /* Send custom notification message */
                        $settings = get_settings('system_settings', true);
                        $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';

                        if ($this->input->post('status', true) == 'received') {
                            $type = ['type' => "customer_order_received"];
                        } elseif ($this->input->post('status', true) == 'processed') {
                            $type = ['type' => "customer_order_processed"];
                        } elseif ($this->input->post('status', true) == 'shipped') {
                            $type = ['type' => "customer_order_shipped"];
                        } elseif ($this->input->post('status', true) == 'delivered') {
                            $type = ['type' => "customer_order_delivered"];
                        } elseif ($this->input->post('status', true) == 'cancelled') {
                            $type = ['type' => "customer_order_cancelled"];
                        } elseif ($this->input->post('status', true) == 'returned') {
                            $type = ['type' => "customer_order_returned"];
                        }

                        $custom_notification = fetch_details('custom_notifications', $type, '');

                        $hashtag_cutomer_name = '< cutomer_name >';
                        $hashtag_order_id = '< order_id >';
                        $hashtag_application_name = '< application_name >';

                        $string = json_encode($custom_notification[0]['message'], JSON_UNESCAPED_UNICODE);
                        $hashtag = html_entity_decode($string);

                        $data = str_replace(array($hashtag_cutomer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]['username'], $_POST['orderid'], $app_name), $hashtag);
                        $message = output_escaping(trim($data, '"'));

                        $customer_msg = (!empty($custom_notification)) ? $message :  'Hello Dear ' . $user_res[0]['username'] . ' order status updated to ' . $_POST['status'] . ' for your order ID #' . $_POST['order_id'] . ' please take note of it! Thank you for shopping with us. Regards ' . $app_name . '';

                        $user_res = fetch_details('users', ['id' => $user_id],  'username,fcm_id');
                        $fcm_ids = array();
                        if (!empty($user_res[0]['fcm_id'])) {
                            $fcmMsg = array(
                                'title' => (!empty($custom_notification)) ? $custom_notification[0]['title'] : "Order status updated",
                                'body' => $customer_msg,
                                'type' => "order"
                            );

                            $fcm_ids[0][] = $user_res[0]['fcm_id'];
                            send_notification($fcmMsg, $fcm_ids, $fcmMsg);
                        }
                        /* Process refer and earn bonus */
                        process_refund($_POST['order_id'], $_POST['status'], 'orders');
                        if (trim($_POST['status'] == 'cancelled')) {
                            $data = fetch_details('order_items', ['order_id' => $_POST['order_id']],  'product_variant_id,quantity');
                            $product_variant_ids = [];
                            $qtns = [];
                            foreach ($data as $d) {
                                array_push($product_variant_ids, $d['product_variant_id']);
                                array_push($qtns, $d['quantity']);
                            }

                            update_stock($product_variant_ids, $qtns, 'plus');
                        }
                        $response = process_referral_bonus($user_id, $this->input->post('order_id', true), $this->input->post('status', true));
                        $message = 'Status Updated Successfully';
                    }
                }
            }
            if ($error == true) {
                $message = 'Status Updation Failed';
            }
        }
        $response['error'] = $error;
        $response['message'] = $message;
        $response['total_amount'] = (!empty($data) ? $data : '');
        print_r(json_encode($response));
    }

    /* to update the status of an individual status */
    public function update_order_item_status()
    {
        /*
            order_item_id:1
            status : received / processed / shipped / delivered / cancelled / returned
         */

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('order_item_id', 'Order Item ID', 'trim|numeric|required|xss_clean');
        $this->form_validation->set_rules('is_sent', 'Mail sent', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('status', 'Status', 'trim|xss_clean|in_list[received,processed,shipped,delivered,cancelled,returned]');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }
        $order_item = fetch_details('order_items', ['id' => $this->input->post('order_item_id', true)],  '*');
        $is_sent = $this->input->post('is_sent', true);
        if (isset($is_sent) && !empty($is_sent)) {
            update_details(['is_sent' => $is_sent], ['id' => $this->input->post('order_item_id', true)], 'order_items');
            $this->response['error'] = false;
            $this->response['message'] = 'Mail status Updated Successfully';
            print_r(json_encode($this->response));
            return false;
        }
        if (empty($order_item)) {
            $this->response['error'] = true;
            $this->response['message'] = 'No Order Item Found';
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }
        $res = validate_order_status($this->input->post('order_item_id', true), $this->input->post('status', true));
        if ($res['error']) {
            $this->response['error'] = true;
            $this->response['message'] = $res['message'];
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }


        $order_method = fetch_details('orders', ['id' => $order_item[0]['order_id']],  'payment_method');
        if ($order_method[0]['payment_method'] == 'bank_transfer') {
            $bank_receipt = fetch_details('order_bank_transfer', ['order_id' => $order_item[0]['order_id']]);
            $transaction_status = fetch_details('transactions', ['order_id' => $order_item[0]['order_id']],  'status');
            if ($this->input->post('status', true) != 'cancelled' && (empty($bank_receipt) || strtolower($transaction_status[0]['status']) != 'success' || $bank_receipt[0]['status'] == "0" || $bank_receipt[0]['status'] == "1")) {
                $this->response['error'] = true;
                $this->response['message'] = "Order Status can not update, Bank verification is remain from transactions.";
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
        }

        $order_item_res = $this->db->select(' * , (Select count(id) from order_items where order_id = oi.order_id ) as order_counter ,(Select count(active_status) from order_items where active_status ="cancelled" and order_id = oi.order_id ) as order_cancel_counter , (Select count(active_status) from order_items where active_status ="returned" and order_id = oi.order_id ) as order_return_counter,(Select count(active_status) from order_items where active_status ="delivered" and order_id = oi.order_id ) as order_delivered_counter , (Select count(active_status) from order_items where active_status ="processed" and order_id = oi.order_id ) as order_processed_counter , (Select count(active_status) from order_items where active_status ="shipped" and order_id = oi.order_id ) as order_shipped_counter , (Select status from orders where id = oi.order_id ) as order_status ')
            ->where(['id' => $this->input->post('order_item_id', true)])
            ->get('order_items oi')->result_array();

        if ($this->order_model->update_order(['status' => $this->input->post('status', true)], ['id' => $order_item_res[0]['id']], true, 'order_items')) {
            $this->order_model->update_order(['active_status' => $this->input->post('status', true)], ['id' => $order_item_res[0]['id']], false, 'order_items');
            process_refund($order_item_res[0]['id'], $this->input->post('status', true), 'order_items');
            if (($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_cancel_counter']) + 1 && $this->input->post('status', true) == 'cancelled') ||  ($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_return_counter']) + 1 && $_POST['status'] == 'returned') || ($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_delivered_counter']) + 1 && $_POST['status'] == 'delivered') || ($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_processed_counter']) + 1 && $_POST['status'] == 'processed') || ($order_item_res[0]['order_counter'] == intval($order_item_res[0]['order_shipped_counter']) + 1 && $_POST['status'] == 'shipped')) {
                if ($this->order_model->update_order(['status' => $this->input->post('status', true)], ['id' => $order_item_res[0]['order_id']], true)) {
                    $this->order_model->update_order(['active_status' => $this->input->post('status', true)], ['id' => $order_item_res[0]['order_id']]);

                    /* process the refer and earn */
                    $user = fetch_details('orders', ['id' => $order_item_res[0]['order_id']],  'user_id');
                    $user_id = $user[0]['user_id'];
                    if (trim($this->input->post('status', true)) == 'cancelled' || trim($this->input->post('status', true)) == 'returned') {
                        $data = fetch_details('order_items', ['id' => $this->input->post('order_item_id', true)],  'product_variant_id,quantity');
                        update_stock($data[0]['product_variant_id'], $data[0]['quantity'], 'plus');
                    }
                    $response = process_referral_bonus($user_id, $order_item_res[0]['order_id'], $this->input->post('status', true));
                    $settings = get_settings('system_settings', true);
                    $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                    $user_res = fetch_details('users', ['id' => $user_id], 'username,fcm_id');

                    // send custom notification message
                    if ($this->input->post('status', true) == 'received') {
                        $type = ['type' => "customer_order_received"];
                    } elseif ($this->input->post('status', true) == 'processed') {
                        $type = ['type' => "customer_order_processed"];
                    } elseif ($this->input->post('status', true) == 'shipped') {
                        $type = ['type' => "customer_order_shipped"];
                    } elseif ($this->input->post('status', true) == 'delivered') {
                        $type = ['type' => "customer_order_delivered"];
                    } elseif ($this->input->post('status', true) == 'cancelled') {
                        $type = ['type' => "customer_order_cancelled"];
                    } elseif ($this->input->post('status', true) == 'returned') {
                        $type = ['type' => "customer_order_returned"];
                    }

                    $custom_notification = fetch_details('custom_notifications', $type, '');

                    $hashtag_cutomer_name = '< cutomer_name >';
                    $hashtag_order_id = '< order_id >';
                    $hashtag_application_name = '< application_name >';

                    $string = json_encode($custom_notification[0]['message'], JSON_UNESCAPED_UNICODE);
                    $hashtag = html_entity_decode($string);

                    $data = str_replace(array($hashtag_cutomer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]['username'], $order_item_res[0]['order_id'], $app_name), $hashtag);
                    $message = output_escaping(trim($data, '"'));

                    $customer_msg = (!empty($custom_notification)) ? $message :   'Hello Dear ' . $user_res[0]['username'] . ' order status updated to ' . $this->input->post('status', true) . ' for your order ID #' . $order_item_res[0]['order_id'] . ' please take note of it! Thank you for shopping with us. Regards ' . $app_name . '';

                    $fcm_ids = array();
                    if (!empty($user_res[0]['fcm_id'])) {
                        $fcmMsg = array(
                            'title' => (!empty($custom_notification)) ? $custom_notification[0]['title'] : "Order status updated",
                            'body' => $customer_msg,
                            'type' => "order"
                        );

                        $fcm_ids[0][] = $user_res[0]['fcm_id'];
                        send_notification($fcmMsg, $fcm_ids, $fcmMsg);
                    }
                }
            }
            $this->response['error'] = false;
            $this->response['message'] = 'Status Updated Successfully';
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }
    }

    public function get_categories()
    {
        /*
            id:15               // optional
            limit:25            // { default - 25 } optional
            offset:0            // { default - 0 } optional
            sort:               id / name
                                // { default -row_id } optional
            order:DESC/ASC      // { default - ASC } optional
            has_child_or_item:false { default - true}  optional
        */


        $this->form_validation->set_rules('id', 'Category Id', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        $this->form_validation->set_rules('has_child_or_item', 'Child or Item', 'trim|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return;
        }
        $limit = (isset($_POST['limit'])  && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
        $offset = (isset($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
        $sort = (isset($_POST['sort(array)']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'row_order';
        $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'ASC';
        $has_child_or_item = (isset($_POST['has_child_or_item']) && !empty(trim($_POST['has_child_or_item']))) ? $this->input->post('has_child_or_item', true) : 'true';

        $this->response['message'] = "Cateogry(s) retrieved successfully!";
        $id = (!empty($_POST['id']) && isset($_POST['id'])) ? $this->input->post('id', true) : '';
        $cat_res = $this->category_model->get_categories($id, $limit, $offset, $sort, $order, strval(trim($has_child_or_item)));
        $this->response['error'] = (empty($cat_res)) ? true : false;
        $this->response['message'] = (empty($cat_res)) ? 'Category does not exist' : 'Category retrieved successfully';
        $this->response['data'] = $cat_res;


        print_r(json_encode($this->response));
    }

    public function get_products()
    {
        /*
        id:101                  // optional
        category_id:29          // optional
        user_id:15              // optional
        search:keyword          // optional
        tags:multiword tag1, tag2, another tag      // optional
        flag:low/sold           // optional
        attribute_value_ids : 34,23,12 // { Use only for filteration } optional
        limit:25                // { default - 25 } optional
        offset:0                // { default - 0 } optional
        sort:p.id / p.date_added / pv.price
                                // { default - p.id } optional
        order:DESC/ASC          // { default - DESC } optional
        is_similar_products:1   // { default - 0 } optional
        top_rated_product: 1    // { default - 0 } optional
        show_only_active_products:false { default - true } optional

        */



        $this->form_validation->set_rules('id', 'Product ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('search', 'Search', 'trim|xss_clean');
        $this->form_validation->set_rules('category_id', 'Category id', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('attribute_value_ids', 'Attr Ids', 'trim|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean|alpha');
        $this->form_validation->set_rules('is_similar_products', 'Similar Products', 'trim|xss_clean|numeric');
        $this->form_validation->set_rules('top_rated_product', ' Top Rated Product ', 'trim|xss_clean|numeric');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $limit = (isset($_POST['limit'])) ? $this->input->post('limit', true) : 25;
            $offset = (isset($_POST['offset'])) ? $this->input->post('offset', true) : 0;
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'ASC';
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'p.row_order';
            $filters['search'] =  (isset($_POST['search'])) ? $this->input->post('search', true) : null;
            $filters['tags'] =  (isset($_POST['tags'])) ? $this->input->post('tags', true) : "";
            $filters['flag'] =  (isset($_POST['flag']) && !empty($_POST['flag'])) ? $this->input->post('flag', true) : "";
            $filters['attribute_value_ids'] = (isset($_POST['attribute_value_ids'])) ? $this->input->post('attribute_value_ids', true) : null;
            $filters['is_similar_products'] = (isset($_POST['is_similar_products'])) ? $this->input->post('is_similar_products', true) : null;
            $filters['product_type'] = (isset($_POST['top_rated_product']) && $_POST['top_rated_product'] == 1) ? 'top_rated_product_including_all_products' : null;
            $filters['show_only_active_products'] = (isset($_POST['show_only_active_products'])) ? $this->input->post('show_only_active_products', true) : true;
            $filters['show_only_stock_product'] = (isset($_POST['show_only_stock_product'])) ? $this->input->post('show_only_stock_product', true) : false;
            
            $category_id = (isset($_POST['category_id'])) ? $this->input->post('category_id', true) : null;
            $product_id = (isset($_POST['id'])) ? $this->input->post('id', true) : null;
            $user_id = (isset($_POST['user_id'])) ? $this->input->post('user_id', true) : null;
// print_r($filters);

            $products = fetch_product($user_id, (isset($filters)) ? $filters : null, $product_id, $category_id, $limit, $offset, $sort, $order);            if (!empty($products['product'])) {
                // Subtract tax for admin app to show correct prices when tax inclusive setting is OFF
                for ($i = 0; $i < count($products['product']); $i++) {
                    if (isset($products['product'][$i]['is_prices_inclusive_tax']) && $products['product'][$i]['is_prices_inclusive_tax'] == 0) {
                        $tax_percentage = isset($products['product'][$i]['tax_percentage']) ? $products['product'][$i]['tax_percentage'] : '0';
                        if ($tax_percentage > 0) {
                            $tax_percentage_array = explode(',', $tax_percentage);
                            $total_tax_percentage = array_sum($tax_percentage_array);
                            
                            // Subtract tax from variant prices
                            for ($k = 0; $k < count($products['product'][$i]['variants']); $k++) {
                                if (isset($products['product'][$i]['variants'][$k]['price']) && $products['product'][$i]['variants'][$k]['price'] > 0) {
                                    $original_price = floatval($products['product'][$i]['variants'][$k]['price']);
                                    $price_without_tax = $original_price / (1 + ($total_tax_percentage / 100));
                                    $products['product'][$i]['variants'][$k]['price'] = strval(round($price_without_tax, 2));
                                }
                                
                                if (isset($products['product'][$i]['variants'][$k]['special_price']) && $products['product'][$i]['variants'][$k]['special_price'] > 0) {
                                    $original_special_price = floatval($products['product'][$i]['variants'][$k]['special_price']);
                                    $special_price_without_tax = $original_special_price / (1 + ($total_tax_percentage / 100));
                                    $products['product'][$i]['variants'][$k]['special_price'] = strval(round($special_price_without_tax, 2));
                                }
                            }
                        }
                    }
                }
                
                $this->response['error'] = false;
                $this->response['message'] = "Products retrieved successfully !";
                $this->response['filters'] = (isset($products['filters']) && !empty($products['filters'])) ? $products['filters'] : [];
                $this->response['total'] = (isset($products['total'])) ? strval($products['total']) : '';
                $this->response['offset'] = (isset($_POST['offset']) && !empty($_POST['offset'])) ? $this->input->post('offset', true) : '0';
                $this->response['data'] = $products['product'];
            } else {
                $this->response['error'] = true;
                $this->response['message'] = "Products Not Found !";
                $this->response['data'] = array();
            }
        }
        print_r(json_encode($this->response));
    }

    public function get_customers()
    {
        /*
            id: 1001                // { optional}
            search : Search keyword // { optional }
            limit:25                // { default - 25 } optional
            offset:0                // { default - 0 } optional
            sort: id/username/email/mobile/area_name/city_name/date_created // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */

        $this->form_validation->set_rules('id', 'ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('search', 'Search keyword', 'trim|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $id = (isset($_POST['id']) && is_numeric($_POST['id']) && !empty(trim($_POST['id']))) ? $this->input->post('id', true) : "";
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
            $this->customer_model->get_customers($id, $search, $offset, $limit, $sort, $order);
        }
    }

    public function get_transactions()
    {
        /*
            user_id:73              // { optional}
            id: 1001                // { optional}
            transaction_type:transaction / wallet // { default - transaction } optional
            type : COD / stripe / razorpay / paypal / paystack / flutterwave - for transaction | credit / debit - for wallet |  // { optional }
                        {for cash collection : delivery_boy_cash (received cash) , delivery_boy_cash_collection(admin collected cash)}
            search : Search keyword // { optional }
            limit:25                // { default - 25 } optional
            offset:0                // { default - 0 } optional
            sort: id / date_created // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('user_id', 'User ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('transaction_type', 'Transaction Type', 'trim|xss_clean');
        $this->form_validation->set_rules('type', 'Type', 'trim|xss_clean');
        $this->form_validation->set_rules('search', 'Search keyword', 'trim|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $user_id = (isset($_POST['user_id']) && is_numeric($_POST['user_id']) && !empty(trim($_POST['user_id']))) ? $this->input->post('user_id', true) : "";
            $id = (isset($_POST['id']) && is_numeric($_POST['id']) && !empty(trim($_POST['id']))) ? $this->input->post('id', true) : "";
            $transaction_type = (isset($_POST['transaction_type']) && !empty(trim($_POST['transaction_type']))) ? $this->input->post('transaction_type', true) : "transaction";
            $type = (isset($_POST['type']) && !empty(trim($_POST['type']))) ? $this->input->post('type', true) : "";
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
            $res = $this->transaction_model->get_transactions($id, $user_id, $transaction_type, $type, $search, $offset, $limit, $sort, $order);
            $this->response['error'] = !empty($res['data']) ? false : true;
            $this->response['message'] = !empty($res['data']) ? 'Transactions Retrieved Successfully' : 'Transactions does not exists';
            $this->response['total'] = !empty($res['data']) ? $res['total'] : 0;
            $this->response['data'] = !empty($res['data']) ? $res['data'] : [];
        }

        print_r(json_encode($this->response));
    }

    public function get_statistics()
    {
        if (!$this->verify_token()) {
            return false;
        }
        $user_id = isset($this->user_details['id']) && $this->user_details['id'] !== null ? $this->user_details['id'] : '';
        $currency_symbol = get_settings('currency');
        $bulkData =  $rows = $tempRow =  $tempRow1 = $tempRow2 = array();
        $bulkData['error'] = false;
        $bulkData['message'] = 'Data retrieved successfully';
        $bulkData['currency_symbol'] = !empty($currency_symbol) ? $currency_symbol : '';
        $permissions = fetch_details('user_permissions', ['user_id' => $user_id], 'permissions');
        
        if ($permissions[0]['permissions'] == null) {
            $permissions =
                '{"orders":{"read":"on","update":"on","delete":"on"},
                "categories":{"create":"on","read":"on","update":"on","delete":"on"},
                "category_order":{"read":"on","update":"on"},
                "product":{"create":"on","read":"on","update":"on","delete":"on"},
                "media":{"create":"on","read":"on","update":"on","delete":"on"},
                "product_order":{"read":"on","update":"on"},
                "tax":{"create":"on","read":"on","update":"on","delete":"on"},
                "attribute":{"create":"on","read":"on","update":"on","delete":"on"},
                "attribute_set":{"create":"on","read":"on","update":"on","delete":"on"},
                "attribute_value":{"create":"on","read":"on","update":"on","delete":"on"},
                "home_slider":{"create":"on","read":"on","update":"on","delete":"on"},
                "offer":{"create":"on","read":"on","delete":"on"},
                "promo_code":{"create":"on","read":"on","update":"on","delete":"on"},
                "featured_section":{"create":"on","read":"on","update":"on","delete":"on"},
                "customers":{"read":"on","update":"on"},"return_request":{"read":"on","update":"on"},
                "delivery_boy":{"create":"on","read":"on","update":"on","delete":"on"},
                "fund_transfer":{"create":"on","read":"on","update":"on","delete":"on"},
                "send_notification":{"create":"on","read":"on","delete":"on"},
                "notification_setting":{"read":"on","update":"on"},
                "client_api_keys":{"create":"on","read":"on","update":"on","delete":"on"},
                "area":{"create":"on","read":"on","update":"on","delete":"on"},
                "city":{"create":"on","read":"on","update":"on","delete":"on"},
                "faq":{"create":"on","read":"on","update":"on","delete":"on"},
                "system_update":{"update":"on"},
                "support_tickets":{"create":"on","read":"on","update":"on","delete":"on"},
                "zipcodes":{"create":"on","read":"on","update":"on","delete":"on"},
                "settings":{"read":"on","update":"on"}}';
        } else {
            $permissions = $permissions[0]['permissions'];
        }

        $permits_key = array_keys($this->config->item('system_modules'));
        $permits = json_decode($permissions, true);
        foreach ($permits as $per) {

            if (!array_key_exists('create', $per)) {
                $per['create'] = "off";
            }
            if (!array_key_exists('read', $per)) {
                $per['read'] = "off";
            }
            if (!array_key_exists('update', $per)) {
                $per['update'] = "off";
            }
            if (!array_key_exists('delete', $per)) {
                $per['delete'] = "off";
            }
            $permission[] = $per;
        }
        $final_permissions = array_combine(array_keys($permits), $permission);
        $permit_array = ["create" => "off", "read" => "off", "update" => "off", "delete" => "off"];
        foreach ($permits_key as $key1) {
            if (!array_key_exists($key1, $final_permissions)) {
                $final_permissions[$key1] = $permit_array;
            }
        }
        $bulkData['permissions'] = $final_permissions;
        $res = $this->db->select('c.name as name,count(c.id) as counter')->where(['p.status' => '1', 'c.status' => '1'])->join('products p', 'p.category_id=c.id')->group_by('c.id')->get('categories c')->result_array();
        foreach ($res as $row) {
            $tempRow['cat_name'][] = $row['name'];
            $tempRow['counter'][] = $row['counter'];
        }

        $rows[] = $tempRow;
        $bulkData['category_wise_product_count'] = $tempRow;
        $overall_sale = $this->db->select("SUM(final_total) as overall_sale")->get('`orders`')->result_array();
        $overall_sale = !empty($overall_sale[0]['overall_sale']) ? intval($overall_sale[0]['overall_sale']) : 0;
        $tempRow1['overall_sale'] = $overall_sale;

        $day_res = $this->db->select("DAY(date_added) as date, SUM(final_total) as total_sale")
            ->where('date_added >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)')
            ->group_by('day(date_added)')->get('`orders`')->result_array();
        $day_wise_sales['total_sale'] = array_map('intval', array_column($day_res, 'total_sale'));
        $day_wise_sales['day'] = array_column($day_res, 'date');
        $tempRow1['daily_earnings'] = $day_wise_sales;

        $d = strtotime("today");
        $start_week = strtotime("last sunday midnight", $d);
        $end_week = strtotime("next saturday", $d);
        $start = date("Y-m-d", $start_week);
        $end = date("Y-m-d", $end_week);
        $week_res = $this->db->select("DATE_FORMAT(date_added, '%d-%b') as date, SUM(final_total) as total_sale")
            ->where("date(date_added) >='$start' and date(date_added) <= '$end' ")
            ->group_by('day(date_added)')->get('`orders`')->result_array();


        $week_wise_sales['total_sale'] = array_map('intval', array_column($week_res, 'total_sale'));
        $week_wise_sales['week'] = array_column($week_res, 'date');
        $tempRow1['weekly_earnings'] = $week_wise_sales;

        $month_res = $this->db->select('SUM(final_total) AS total_sale,DATE_FORMAT(date_added,"%b") AS month_name ')
            ->group_by('year(CURDATE()),MONTH(date_added)')
            ->order_by('year(CURDATE()),MONTH(date_added)')
            ->get('`orders`')->result_array();
        $month_wise_sales['total_sale'] = array_map('intval', array_column($month_res, 'total_sale'));
        $month_wise_sales['month_name'] = array_column($month_res, 'month_name');
        $tempRow1['monthly_earnings'] = $month_wise_sales;
        $rows1[] = $tempRow1;
        $bulkData['earnings'] = $rows1;
        $count_products_low_status = $this->Home_model->count_products_stock_low_status();
        $count_products_sold_out_status = $this->Home_model->count_products_availability_status();
        $tempRow2['order_counter'] = $this->Home_model->count_new_orders('api');
        $tempRow2['delivered_orders_counter'] = $this->Home_model->count_orders_by_status('delivered');
        $tempRow2['cancelled_orders_counter'] = $this->Home_model->count_orders_by_status('cancelled');
        $tempRow2['returned_orders_counter'] = $this->Home_model->count_orders_by_status('returned');
        $tempRow2['received_orders_counter'] = $this->Home_model->count_orders_by_status('received');
        $tempRow2['user_counter'] = $this->Home_model->count_new_users();
        $tempRow2['delivery_boy_counter'] = $this->Home_model->count_delivery_boys();
        $tempRow2['product_counter'] = $this->Home_model->count_products();
        $tempRow2['count_products_low_status'] = "$count_products_low_status";
        $tempRow2['count_products_sold_out_status'] = "$count_products_sold_out_status";
        $rows2[] = $tempRow2;
        $bulkData['counts'] = $rows2;
        print_r(json_encode($bulkData));
    }

    public function forgot_password()
    {
        /* Parameters to be passed
            mobile_no:7894561235            
            new: pass@123
        */

        $this->form_validation->set_rules('mobile_no', 'Mobile No', 'trim|numeric|required|xss_clean|max_length[16]');
        $this->form_validation->set_rules('new', 'New Password', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
            return false;
        }

        $identity_column = $this->config->item('identity', 'ion_auth');
        $res = fetch_details('users', ['mobile' => $this->input->post('mobile_no', true)]);
        if (!empty($res)) {
            $identity = ($identity_column  == 'email') ? $res[0]['email'] : $res[0]['mobile'];
            if (!$this->ion_auth->reset_password($identity, $this->input->post('new', true))) {
                $response['error'] = true;
                $response['message'] = strip_tags($this->ion_auth->messages());;
                $response['data'] = array();
                echo json_encode($response);
                return false;
            } else {
                $response['error'] = false;
                $response['message'] = 'Reset Password Successfully';
                $response['data'] = array();
                echo json_encode($response);
                return false;
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'User does not exists !';
            $response['data'] = array();
            echo json_encode($response);
            return false;
        }
    }

    public function delete_order()
    {
        /*
            order_id:1
        */
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('order_id', 'Order ID', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $order_id = $this->input->post('order_id', true);
            delete_details(['id' => $order_id], 'orders');
            delete_details(['order_id' => $order_id], 'order_items');

            $this->response['error'] = false;
            $this->response['message'] = 'Order deleted successfully';
            $this->response['data'] = array();
        }
        print_r(json_encode($this->response));
    }

    public function get_delivery_boys()
    {
        /*
            id: 1001                // { optional}
            search : Search keyword // { optional }
            limit:25                // { default - 25 } optional
            offset:0                // { default - 0 } optional
            sort: id/username/email/mobile/area_name/city_name/date_created // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */


        $this->form_validation->set_rules('id', 'ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('search', 'Search keyword', 'trim|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $id = (isset($_POST['id']) && is_numeric($_POST['id']) && !empty(trim($_POST['id']))) ? $this->input->post('id', true) : "";
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
            $this->delivery_boy_model->get_delivery_boys($id, $search, $offset, $limit, $sort, $order);
        }
    }

    //verify-user
    public function verify_user()
    {
        /* Parameters to be passed
            mobile: 9874565478
            email: test@gmail.com // { optional }
        */

        $this->form_validation->set_rules('mobile', 'Mobile', 'trim|numeric|required|xss_clean');
        $this->form_validation->set_rules('email', 'Email', 'trim|xss_clean|valid_email');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
            return;
        } else {
            if (isset($_POST['mobile']) && is_exist(['mobile' => $this->input->post('mobile', true)], 'users')) {
                $user_id = fetch_details('users', ['mobile' => $this->input->post('mobile', true)], 'id');

                //Check if this mobile no. is registered as a admin or not.
                if (!$this->ion_auth->in_group('admin', $user_id[0]['id'])) {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Mobile number / email could not be found!';
                    print_r(json_encode($this->response));
                    return;
                } else {
                    $this->response['error'] = false;
                    $this->response['message'] = 'Mobile number is registered. ';
                    print_r(json_encode($this->response));
                    return;
                }
            }
            if (isset($_POST['email']) && is_exist(['email' => $this->input->post('email', true)], 'users')) {
                $this->response['error'] = false;
                $this->response['message'] = 'Email is registered.';
                print_r(json_encode($this->response));
                return;
            }

            $this->response['error'] = true;
            $this->response['message'] = 'Mobile number / email could not be found!';
            print_r(json_encode($this->response));
            return;
        }
    }

    public function get_settings()
    {
        /*
            type : payment_method // { default : all  } optional            
            user_id:  15 { optional }
        */

        $type = (isset($_POST['type']) && $_POST['type'] == 'payment_method') ? 'payment_method' : 'all';
        $this->form_validation->set_rules('type', 'Setting Type', 'trim|xss_clean');


        if (!$this->form_validation->run()) {

            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $general_settings = array();

            if ($type == 'all' || $type == 'payment_method') {


                $settings = [
                    'logo' => 0,
                    'admin_privacy_policy' => 0,
                    'admin_terms_conditions' => 0,
                    'fcm_server_key' => 0,
                    'contact_us' => 0,
                    'payment_method' => 1,
                    'about_us' => 0,
                    'currency' => 0,
                    'time_slot_config' => 1,
                    'user_data' => 0,
                    'system_settings' => 1,
                    'shipping_policy' => 0,
                    'return_policy' => 0,
                    'vap_id_Key' => 0,
                    'authentication_settings' => 1,
                    'send_notification_settings' => 1,
                    'sms_gateway_settings' => 1,
                ];

                if ($type == 'payment_method') {

                    $settings_res['payment_method'] = get_settings($type, $settings[$_POST['type']]);
                    $time_slot_config = get_settings('time_slot_config', $settings['time_slot_config']);

                    if (!empty($time_slot_config) && isset($time_slot_config)) {
                        $time_slot_config['delivery_starts_from'] = $time_slot_config['delivery_starts_from'] - 1;
                        $time_slot_config['starting_date'] = date('Y-m-d', strtotime(date('d-m-Y') . ' + ' . intval($time_slot_config['delivery_starts_from']) . ' days'));
                    }

                    $settings_res['time_slot_config'] = $time_slot_config;
                    $time_slots = fetch_details('time_slots', '', '*', '', '', 'from_time', 'ASC');

                    if (!empty($time_slots)) {
                        for ($i = 0; $i < count($time_slots); $i++) {

                            $datetime = DateTime::createFromFormat("h:i:s a", $time_slots[$i]['from_time']);
                        }
                    }

                    $settings_res['time_slots'] = array_values($time_slots);
                    $general_settings = $settings_res;
                } else {

                    foreach ($settings as $type => $isjson) {
                        if ($type == 'payment_method') {
                            continue;
                        }
                        $general_settings[$type] = [];
                        $settings_res = get_settings($type, $isjson);

                        if ($type == 'logo') {
                            $settings_res = base_url() . $settings_res;
                        }
                        if ($type == 'user_data' && isset($_POST['user_id'])) {
                            $cart_total_response = get_cart_total($_POST['user_id'], false, 0);
                            $settings_res = fetch_users($_POST['user_id']);
                            $settings_res[0]['cities'] =  (isset($settings_res[0]['cities']) && $settings_res[0]['cities'] != null) ? $cart_total_response[0]['cities'] : '';
                            $settings_res[0]['street'] =  (isset($settings_res[0]['street']) && $settings_res[0]['street'] != null) ? $cart_total_response[0]['street'] : '';
                            $settings_res[0]['area'] =  (isset($settings_res[0]['area']) && $settings_res[0]['area'] != null) ? $cart_total_response[0]['area'] : '';
                            $settings_res[0]['cart_total_items'] = (isset($cart_total_response[0]) && $cart_total_response[0]['cart_count'] > 0) ? $cart_total_response[0]['cart_count'] : '0';
                            $settings_res = $settings_res[0];
                        } elseif ($type == 'user_data' && !isset($_POST['user_id'])) {
                            $settings_res = '';
                        }

                        array_push($general_settings[$type], $settings_res);
                    }
                    $general_settings['privacy_policy'] = $general_settings['admin_privacy_policy'];
                    unset($general_settings['admin_privacy_policy']);
                    $general_settings['terms_conditions'] = $general_settings['admin_terms_conditions'];
                    unset($general_settings['admin_terms_conditions']);
                }
                $this->response['error'] = false;
                $this->response['message'] = 'Settings retrieved successfully';
                $this->response['data'] = $general_settings;
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Settings Not Found';
                $this->response['data'] = array();
            }
            print_r(json_encode($this->response));
        }
    }

    // 15. update_fcm
    public function update_fcm()
    {

        /* Parameters to be passed
             user_id:12
             fcm_id: FCM_ID
         */

        if (!$this->verify_token()) {
            return false;
        }



        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
            return false;
        }
        $user_id = isset($this->user_details['id']) && $this->user_details['id'] !== null ? $this->user_details['id'] : '';
        $user_res = update_details(['fcm_id' => $_POST['fcm_id']], ['id' => $user_id], 'users');

        if ($user_res) {
            $response['error'] = false;
            $response['message'] = 'Updated Successfully';
            $response['data'] = array();
            echo json_encode($response);
            return false;
        } else {
            $response['error'] = true;
            $response['message'] = 'Updation Failed !';
            $response['data'] = array();
            echo json_encode($response);
            return false;
        }
    }

    // 16. send_message
    public function send_message()
    {
        /*
            user_type:admin
            user_id:1
            ticket_id:1	
            message:test	
            attachments[]:files  {optional} {type allowed -> image,video,document,spreadsheet,archive}
        */

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('user_type', 'User Type', 'trim|required|xss_clean');

        $this->form_validation->set_rules('ticket_id', 'Ticket id', 'trim|required|numeric|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $user_type = $this->input->post('user_type', true);
            $user_id = isset($this->user_details['id']) && $this->user_details['id'] !== null ? $this->user_details['id'] : '';
            $ticket_id = $this->input->post('ticket_id', true);
            $message = (isset($_POST['message']) && !empty(trim($_POST['message']))) ? $this->input->post('message', true) : "";


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
            $system_settings = get_settings('system_settings', true);
            if (!empty($insert_id)) {
                $data1 = $this->config->item('type');
                $result = $this->ticket_model->get_messages($ticket_id, $user_id, "", "", "1", "", "", $data1, $insert_id);
                if (!empty($result)) {
                    /* Send custom notification message */

                    $ticket_res = fetch_details('ticket_messages', ['user_type' => 'user', 'ticket_id' => $ticket_id], 'user_id');

                    $user_res = fetch_details("users", ['id' => $ticket_res[0]['user_id']], 'fcm_id', '',  '', '', '');
                    $fcm_ids[0][] = $user_res[0]['fcm_id'];

                    $custom_notification =  fetch_details('custom_notifications', ['type' => "ticket_message"], '');

                    $hashtag_application_name = '< application_name >';

                    $string = json_encode($custom_notification[0]['message'], JSON_UNESCAPED_UNICODE);
                    $hashtag = html_entity_decode($string);

                    $data = str_replace($hashtag_application_name, $system_settings['app_name'], $hashtag);
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
        print_r(json_encode($this->response));
    }

    // 17. edit_ticket
    public function edit_ticket()
    {
        /*
            ticket_id:1
            status:1 or 2 or 3 or 4 or 5  [1 -> pending, 2 -> opened, 3 -> resolved, 4 -> closed, 5 -> reopened]
        */

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('ticket_id', 'Ticket Id', 'trim|required|xss_clean');
        $this->form_validation->set_rules('status', 'Status', 'trim|required|xss_clean');


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $status = $this->input->post('status', true);
            $ticket_id = $this->input->post('ticket_id', true);
            $res = fetch_details('tickets', 'id=' . $ticket_id,  '*');
            if (empty($res)) {
                $this->response['error'] = true;
                $this->response['message'] = "User id is changed you can not udpate the ticket.";
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
            if ($status == PENDING && $res[0]['status'] == OPENED) {
                $this->response['error'] = true;
                $this->response['message'] = "Current status is opened.";
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
            if ($status == OPENED && ($res[0]['status'] == RESOLVED || $res[0]['status'] == CLOSED)) {
                $this->response['error'] = true;
                $this->response['message'] = "Can't be OPEN but you can REOPEN the ticket.";
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

            $data = array(
                'status' => $status,
                'edit_ticket_status' => $ticket_id
            );

            $system_settings = get_settings('system_settings', true);
            if (!$this->ticket_model->add_ticket($data)) {
                $result = $this->ticket_model->get_tickets($ticket_id);
                if (!empty($result)) {
                    /* Send custom notification message */
                    $ticket_res = fetch_details('ticket_messages', ['user_type' => 'user', 'ticket_id' => $ticket_id],  'user_id');

                    $user_res = fetch_details("users", ['id' => $ticket_res[0]['user_id']],  'fcm_id', '',  '', '', '');
                    $fcm_ids[0][] = $user_res[0]['fcm_id'];

                    $custom_notification =  fetch_details('custom_notifications', ['type' => "ticket_status"], '');

                    $hashtag_application_name = '< application_name >';

                    $string = json_encode($custom_notification[0]['message'], JSON_UNESCAPED_UNICODE);
                    $hashtag = html_entity_decode($string);

                    $data = str_replace($hashtag_application_name, $system_settings['app_name'], $hashtag);
                    $message = output_escaping(trim($data, '"'));

                    $fcm_admin_subject =  (!empty($custom_notification)) ? $custom_notification[0]['title'] : "Your Ticket status has been changed";
                    $fcm_admin_msg = (!empty($custom_notification)) ? $message : "Ticket Message";

                    if (!empty($fcm_ids)) {
                        $fcmMsg = array(
                            'title' => $fcm_admin_subject,
                            'body' => $fcm_admin_msg,
                            'type' => "ticket_status",
                            'type_id' => $ticket_id
                        );
                        send_notification($fcmMsg, $fcm_ids, $fcmMsg);
                    }
                }
                $this->response['error'] = false;
                $this->response['message'] =  'Ticket updated Successfully';
                $this->response['data'] = $result['data'];
            } else {
                $this->response['error'] = true;
                $this->response['message'] =  'Ticket Not Added';
                $this->response['data'] = (!empty($this->response['data'])) ? $this->response['data'] : [];
            }
        }
        print_r(json_encode($this->response));
    }

    //18. get_ticket_types
    public function get_ticket_types()
    {


        $this->db->select('*');
        $types = $this->db->get('ticket_types')->result_array();
        if (!empty($types)) {
            for ($i = 0; $i < count($types); $i++) {
                $types[$i] = output_escaping($types[$i]);
            }
        }
        $this->response['error'] = false;
        $this->response['message'] = 'Ticket types fetched successfully';
        $this->response['data'] = $types;
        print_r(json_encode($this->response));
    }

    //19. get_tickets
    public function get_tickets()
    {
        /*
        19. get_tickets
            ticket_id: 1001                     // { optional}
            ticket_type_id: 1001                // { optional}
            user_id: 1001                       // { optional}
            status:   [1 -> pending, 2 -> opened, 3 -> resolved, 4 -> closed, 5 -> reopened]// { optional}
            search : Search keyword             // { optional }
            limit:25                            // { default - 25 } optional
            offset:0                            // { default - 0 } optional
            sort: id | date_created | last_updated                // { default - id } optional
            order:DESC/ASC                      // { default - DESC } optional
        */
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('ticket_id', 'Ticket ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('ticket_type_id', 'Ticket Type ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('user_id', 'User ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('status', 'User ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('search', 'Search keyword', 'trim|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $ticket_id = (isset($_POST['ticket_id']) && is_numeric($_POST['ticket_id']) && !empty(trim($_POST['ticket_id']))) ? $this->input->post('ticket_id', true) : "";
            $ticket_type_id = (isset($_POST['ticket_type_id']) && is_numeric($_POST['ticket_type_id']) && !empty(trim($_POST['ticket_type_id']))) ? $this->input->post('ticket_type_id', true) : "";
            $user_id = (isset($_POST['user_id']) && is_numeric($_POST['user_id']) && !empty(trim($_POST['user_id']))) ? $this->input->post('user_id', true) : "";
            $status = (isset($_POST['status']) && is_numeric($_POST['status']) && !empty(trim($_POST['status']))) ? $this->input->post('status', true) : "";
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 10;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
            $result = $this->ticket_model->get_tickets($ticket_id, $ticket_type_id, $user_id, $status, $search, $offset, $limit, $sort, $order);
            print_r(json_encode($result));
        }
    }

    public function get_messages()
    {
        /*
    20. get_messages
        ticket_id: 1001            
        user_type: 1001                     // { optional}
        user_id: 1001                       // { optional}
        search : Search keyword             // { optional }
        limit:25                            // { default - 25 } optional
        offset:0                            // { default - 0 } optional
        sort: id | date_created | last_updated                // { default - id } optional
        order:DESC/ASC                      // { default - DESC } optional
    */
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('ticket_id', 'Ticket ID', 'trim|numeric|required|xss_clean');
        $this->form_validation->set_rules('user_id', 'User ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('status', 'User ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('search', 'Search keyword', 'trim|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $ticket_id = (isset($_POST['ticket_id']) && is_numeric($_POST['ticket_id']) && !empty(trim($_POST['ticket_id']))) ? $this->input->post('ticket_id', true) : "";
            $user_id = (isset($_POST['user_id']) && is_numeric($_POST['user_id']) && !empty(trim($_POST['user_id']))) ? $this->input->post('user_id', true) : "";
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 10;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
            $data = $this->config->item('type');
            $result = $this->ticket_model->get_messages($ticket_id, $user_id, $search, $offset, $limit, $sort, $order, $data, "");
            print_r(json_encode($result));
        }
    }

    //21.get_cities
    public function get_cities()
    {
        /*
            sort:               // { c.name / c.id } optional
            order:DESC/ASC      // { default - ASC } optional
            search:value        // {optional} 
            */

        $this->form_validation->set_rules('sort', 'sort', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'c.name';
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'ASC';
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $id = $this->input->post('id', true);
            $result = $this->Area_model->get_cities($sort, $order, $search, $limit, $offset);
            print_r(json_encode($result));
        }
    }

    //22. get_areas_by_city_id
    public function get_areas_by_city_id()
    {
        /* id='57' */

        $this->form_validation->set_rules('id', 'City Id', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $areas = fetch_details('areas', ['city_id' => $this->input->post('id', true)]);
            if (!empty($areas)) {
                for ($i = 0; $i < count($areas); $i++) {
                    $areas[$i] = output_escaping($areas[$i]);
                }
            }
            $this->response['error'] = false;
            $this->response['data'] = $areas;
        }
        print_r(json_encode($this->response));
    }

    //23. delete_order_receipt
    public function delete_order_receipt()
    {
        /* id = 57 */

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            if (delete_details(['id' => $this->input->post('id', true)], "order_bank_transfer")) {
                $this->response['error'] = false;
                $this->response['message'] = 'Deleted Successfully';
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Something went wrong';
            }
            $this->response['data'] = array();
        }
        print_r(json_encode($this->response));
    }

    public function get_order_tracking()
    {
        /* 
        limit:25            // { default - 25 } optional
        offset:0            // { default - 0 } optional
        sort:               // { id } optional
        order:DESC/ASC      // { default - DESC } optional
        search:value        // {optional} 
        */
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        $this->form_validation->set_rules('search', 'search', 'trim|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : '';
            $tmpRow = $rows = array();
            $data = $this->order_model->get_order_tracking($limit, $offset, $sort, $order, $search);
            if (isset($data['data']) && !empty($data['data'])) {
                foreach ($data['data'] as $row) {
                    $tmpRow['id'] = $row['id'];
                    $tmpRow['order_id'] = $row['order_id'];
                    $tmpRow['courier_agency'] = $row['courier_agency'];
                    $tmpRow['tracking_id'] = $row['tracking_id'];
                    $tmpRow['url'] = $row['url'];
                    $order_data = fetch_orders($row['order_id']);
                    $tmpRow['order_details'] = $order_data['order_data'][0];
                    $rows[] = $tmpRow;
                }
                if ($data['error'] == false) {
                    $data['data'] = $rows;
                } else {
                    $data['data'] = array();
                }
            }
            print_r(json_encode($data));
        }
    }

    public function edit_order_tracking()
    {
        /*
            order_id:57 
            courier_agency:asd agency
            tracking_id:t_id123
            url:http://test.com
        */


        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('courier_agency', 'courier_agency', 'trim|required|xss_clean');
        $this->form_validation->set_rules('tracking_id', 'tracking_id', 'trim|required|xss_clean');
        $this->form_validation->set_rules('url', 'url', 'trim|required|xss_clean');
        $this->form_validation->set_rules('order_id', 'order_id', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $order_id = $this->input->post('order_id', true);
            $courier_agency = $this->input->post('courier_agency', true);
            $tracking_id = $this->input->post('tracking_id', true);
            $url = $this->input->post('url', true);
            $data = array(
                'order_id' => $order_id,
                'courier_agency' => $courier_agency,
                'tracking_id' => $tracking_id,
                'url' => $url,
            );
            if (is_exist(['order_id' => $order_id], 'order_tracking', null)) {
                if (update_details($data, ['order_id' => $order_id], 'order_tracking') == TRUE) {
                    $this->response['error'] = false;
                    $this->response['message'] = "Tracking details Update Successfuly.";
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = "Not Updated. Try again later.";
                }
            } else {
                if (insert_details($data, 'order_tracking')) {
                    $this->response['error'] = false;
                    $this->response['message'] = "Tracking details Insert Successfuly.";
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = "Not Inserted. Try again later.";
                }
            }
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        }
    }
    public function update_receipt_status()
    {
        /*
            order_id:57 
            user_id:123
            status:1        // { 0:pending|1:rejected|2:accepted }  
           
        */
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('order_id', 'Order Id', 'trim|required|xss_clean');
        $this->form_validation->set_rules('user_id', 'User Id', 'trim|required|xss_clean');
        $this->form_validation->set_rules('status', 'status', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $order_id = $this->input->post('order_id', true);
            $user_id = isset($this->user_details['id']) && $this->user_details['id'] !== null ? $this->user_details['id'] : '';
            $status = $this->input->post('status', true);
            $rcpt_status = fetch_details("order_bank_transfer", ['order_id' => $order_id],  "status");
            if ($rcpt_status[0]['status'] == 2) {
                $this->response['error'] = true;
                $this->response['message'] = 'Already accepted the bank receipt';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            }
            if (update_details(['status' => $status], ['order_id' => $order_id], 'order_bank_transfer')) {
                if ($status == 1) {
                    $status = "Rejected";
                } else if ($status == 2) {
                    $status = "Accepted";
                } else {
                    $status = "Pending";
                }

                //send custom notification message
                $custom_notification =  fetch_details('custom_notifications', ['type' => "bank_transfer_receipt_status"], '');

                $hashtag_status = '< status  >';
                $hashtag_order_id = '< order_id >';

                $string = json_encode($custom_notification[0]['message'], JSON_UNESCAPED_UNICODE);
                $hashtag = html_entity_decode($string);

                $data = str_replace(array($hashtag_status, $hashtag_order_id), array($status, $order_id), $hashtag);
                $message = output_escaping(trim($data, '"'));

                $title = (!empty($custom_notification)) ? $custom_notification[0]['title'] : 'Bank Transfer Receipt Status';
                $customer_msg = (!empty($custom_notification)) ? $message : 'Bank Transfer Receipt ' . $status . ' for order ID: ' . $order_id;

                $user = fetch_details("users", ['id' => $user_id], 'email,fcm_id');
                send_mail($user[0]['email'], 'Bank Transfer Receipt Status.', 'Bank Transfer Receipt ' . $status . ' for order ID: ' . $order_id);
                $fcm_ids[0][] = $user[0]['fcm_id'];

                if (!empty($fcm_ids)) {
                    $fcmMsg = array(
                        'title' => $title,
                        'body' =>  $customer_msg,
                        'type' => "order"
                    );
                    send_notification($fcmMsg, $fcm_ids, $fcmMsg);
                }
                $this->response['error'] = false;
                $this->response['message'] = 'Updated Successfully';
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Something went wrong';
            }
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        }
    }

    public function get_return_requests()
    {
        /* 
        limit:25            // { default - 25 } optional
        offset:0            // { default - 0 } optional
        sort:               // { id } optional
        order:DESC/ASC      // { default - DESC } optional
        search:value        // {optional} 
        */
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        $this->form_validation->set_rules('search', 'search', 'trim|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : '';
            $tmpRow = $rows = array();
            $this->return_request_model->get_return_requests($limit, $offset, $sort, $order, $search, $where = NULL);
        }
    }

    public function update_return_request()
    {
        /*
            return_request_id:57 
            order_item_id:123 
            status:1        // { 0:pending|1:accepted|2:rejected }  
            update_remarks:  //{optional}

        */
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('return_request_id', 'id', 'trim|required|numeric|xss_clean');
        $this->form_validation->set_rules('status', 'Status', 'trim|required|numeric|xss_clean');
        $this->form_validation->set_rules('update_remarks', 'Remarks ', 'trim|xss_clean');
        $this->form_validation->set_rules('order_item_id', 'Order Item Id ', 'trim|required|numeric|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $this->return_request_model->update_return_request($_POST);
            $this->response['error'] = false;
            $this->response['message'] = 'Return request updated successfully';
            print_r(json_encode($this->response));
        }
    }

    public function manage_delivery_boy_cash_collection()
    {
        /*
            delivery_boy_id:57
            amount:123
            transaction_date: 2021-12-08T16:13  // {optional}
            message:test  //{optional}
        */
        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('delivery_boy_id', 'Delivery Boy', 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('amount', 'Amount', 'trim|required|xss_clean|numeric|greater_than[0]');
        $this->form_validation->set_rules('message', 'Message', 'trim|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['message'] = strip_tags(validation_errors());
            echo json_encode($this->response);
            return false;
        } else {
            $delivery_boy_id = $this->input->post('delivery_boy_id', true);
            if (!is_exist(['id' => $delivery_boy_id], 'users')) {
                $this->response['error'] = true;
                $this->response['message'] = 'Delivery Boy is not exist in your database';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            }
            $res = fetch_details('users', ['id' => $delivery_boy_id], 'cash_received');
            $amount = $this->input->post('amount', true);
            $date = (isset($_POST['transaction_date']) && !empty($_POST['transaction_date'])) ? $this->input->post('transaction_date', true) : date("Y-m-d H:i:s");
            $message = (isset($_POST['message']) && !empty($_POST['message'])) ? $this->input->post('message', true) : "Delivery boy cash collection by admin";
            if ($res[0]['cash_received'] < $amount) {
                $this->response['error'] = true;
                $this->response['message'] = 'Amount must be not be greater than cash';
                echo json_encode($this->response);
                return false;
            }
            if ($res[0]['cash_received'] > 0 && $res[0]['cash_received'] != null) {
                update_cash_received($amount, $delivery_boy_id, "deduct");
                $this->load->model("transaction_model");
                $transaction_data = [
                    'transaction_type' => "transaction",
                    'user_id' => $delivery_boy_id,
                    'order_id' => "",
                    'type' => "delivery_boy_cash_collection",
                    'txn_id' => "",
                    'amount' => $amount,
                    'status' => "1",
                    'message' => $message,
                    'transaction_date' => $date,
                ];
                $this->transaction_model->add_transaction($transaction_data);
                $this->response['error'] = false;
                $this->response['message'] = 'Amount Successfully Collected';
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Cash should be greater than 0';
            }
            echo json_encode($this->response);
            return false;
        }
    }

    public function get_delivery_boy_cash_collection()
    {
        /* 
        delivery_boy_id:15  // {optional}
        status:             // {delivery_boy_cash (delivery boy collected) | delivery_boy_cash_collection (admin collected)}
        limit:25            // { default - 25 } optional
        offset:0            // { default - 0 } optional
        sort:               // { id } optional
        order:DESC/ASC      // { default - DESC } optional
        search:value        // {optional} 
        */
        if (!$this->verify_token()) {
            return false;
        }

        
        $this->form_validation->set_rules('delivery_boy_id', 'Delivery Boy', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        $this->form_validation->set_rules('search', 'search', 'trim|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $filters['delivery_boy_id'] = (isset($_POST['delivery_boy_id']) && is_numeric($_POST['delivery_boy_id']) && !empty(trim($_POST['delivery_boy_id']))) ? $this->input->post('delivery_boy_id', true) : '';
            $filters['status'] = (isset($_POST['status']) && !empty(trim($_POST['status']))) ? $this->input->post('status', true) : '';
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 10;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'transactions.id';
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : '';
            $tmpRow = $rows = array();
            $data = $this->Delivery_boy_model->get_delivery_boy_cash_collection($limit, $offset, $sort, $order, $search, (isset($filters)) ? $filters : null);
            if (isset($data['data']) && !empty($data['data'])) {
                foreach ($data['data'] as $row) {
                    $tmpRow['id'] = $row['id'];
                    $tmpRow['name'] = $row['name'];
                    $tmpRow['mobile'] = $row['mobile'];
                    $tmpRow['order_id'] = (isset($row['order_id']) && !empty($row['order_id'])) ? $row['order_id'] : "";
                    $tmpRow['cash_received'] = $row['cash_received'];
                    $tmpRow['type'] = $row['type'];
                    $tmpRow['amount'] = $row['amount'];
                    $tmpRow['message'] = $row['message'];
                    $tmpRow['transaction_date'] = $row['transaction_date'];
                    $tmpRow['date'] = $row['date'];
                    if (isset($row['order_id']) && !empty($row['order_id']) && $row['order_id'] != "") {
                        $order_data = fetch_orders($row['order_id']);
                        $tmpRow['order_details'] = $order_data['order_data'][0];
                    } else {
                        $tmpRow['order_details'] = "";
                    }
                    $rows[] = $tmpRow;
                }
                if ($data['error'] == false) {
                    $data['data'] = $rows;
                } else {
                    $data['data'] = array();
                }
            }
            print_r(json_encode($data));
        }
    }

    public function add_product()
    {

        /*
            user_id:1
            pro_input_name: product name
            short_description: description
            tags:tag1,tag2,tag3     //{comma saprated}
            pro_input_tax:tax_id
            indicator:1             //{ 0 - none | 1 - veg | 2 - non-veg }
            made_in: india          //{optional}
            brand: adidas          //{optional}
            total_allowed_quantity:100
            minimum_order_quantity:12
            quantity_step_size:1
            warranty_period:1 month     {optional}
            guarantee_period:1 month   {optional}
            deliverable_type:1        //{0:none, 1:all, 2:include, 3:exclude}
            deliverable_zipcodes:1,2,3  //{NULL: if deliverable_type = 0 or 1}
            deliverable_city_type:1        //{0:none, 1:all, 2:include, 3:exclude}
            deliverable_cities[]:1,2,3  //{NULL: if deliverable_type = 0 or 1}
            is_prices_inclusive_tax:0   //{1: inclusive | 0: exclusive}
            cod_allowed:1               //{ 1:allowed | 0:not-allowed }

            download_allowed:1               //{ 1:allowed | 0:not-allowed }
            download_link_type:self_hosted             //{ values : self_hosted | add_link }
            pro_input_zip:file              //when download type is self_hosted add file for download
            download_link : url             //{URL of download file}

            is_returnable:1             // { 1:returnable | 0:not-returnable } 
            is_cancelable:1             //{1:cancelable | 0:not-cancelable}
            cancelable_till:            //{received,processed,shipped}
            pro_input_image:file
            other_images: files
            video_type:                 // {values: vimeo | youtube}
            video:                      //{URL of video}
            pro_input_video: file
            pro_input_description:product's description 
            category_id:99
            attribute_values:1,2,3,4,5
            --------------------------------------------------------------------------------
            till above same params
            --------------------------------------------------------------------------------
            --------------------------------------------------------------------------------
            common param for simple and variable product
            --------------------------------------------------------------------------------          
          product_type:simple_product | variable_product  |  digital_product
            variant_stock_level_type:product_level | variable_level
            
            if(product_type == variable_product):
                variants_ids:3 5,4 5,1 2
                variant_price:100,200
                variant_special_price:90,190
                variant_images:files              //{optional}

                sku_variant_type:test            //{if (variant_stock_level_type == product_level)}
                total_stock_variant_type:100     //{if (variant_stock_level_type == product_level)}
                variant_status:1                 //{if (variant_stock_level_type == product_level)}

                variant_sku:test,test             //{if(variant_stock_level_type == variable_level)}
                variant_total_stock:120,300       //{if(variant_stock_level_type == variable_level)}
                variant_level_stock_status:1,1    //{if(variant_stock_level_type == variable_level)}

            if(product_type == simple_product):
                simple_product_stock_status:null|0|1   {1=in stock | 0=out stock}
                simple_price:100
                simple_special_price:90
                product_sku:test                    {optional}
                product_total_stock:100             {optional}
                variant_stock_status: 0             {optional}//{0 =>'Simple_Product_Stock_Active' 1 => "Product_Level" 2 => "Variable_Level"	}
        if(product_type == digital_product):
                simple_price:100
                simple_special_price:90
       
                */
        if (!$this->verify_token()) {
            return false;
        }
        $user_id = isset($this->user_details['id']) && $this->user_details['id'] !== null ? $this->user_details['id'] : '';
        if (isset($_POST['edit_product_id'])) {
            if (print_msg(!has_permissions('update', 'product', $user_id), PERMISSION_ERROR_MSG, 'product')) {
                return false;
            }
        } else {
            if (print_msg(!has_permissions('create', 'product', $user_id), PERMISSION_ERROR_MSG, 'product')) {
                return false;
            }
        }
        if (isset($_POST['edit_product_id'])) {
            $this->form_validation->set_rules('edit_product_id', 'Edit Product Id', 'trim|numeric|required|xss_clean');
        } else {
            $this->form_validation->set_rules('product_id', 'Product Id', 'trim|numeric|xss_clean');
        }

        $this->form_validation->set_rules('pro_input_name', 'Product Name', 'trim|required|xss_clean');
        $this->form_validation->set_rules('short_description', 'Short Description', 'trim|required|xss_clean');
        $this->form_validation->set_rules('category_id', 'Category Id', 'trim|required|xss_clean', array('required' => 'Category is required'));
        $this->form_validation->set_rules('pro_input_tax[]', 'Tax', 'trim|xss_clean');
        $this->form_validation->set_rules('pro_input_image', 'Image', 'trim|xss_clean');
        $this->form_validation->set_rules('made_in', 'Made In', 'trim|xss_clean');
        $this->form_validation->set_rules('brand', 'Brand', 'trim|xss_clean');
        $this->form_validation->set_rules('product_type', 'Product type', 'trim|required|xss_clean');
        $this->form_validation->set_rules('total_allowed_quantity', 'Total Allowed Quantity', 'trim|xss_clean');
        $this->form_validation->set_rules('minimum_order_quantity', 'Minimum Order Quantity', 'trim|xss_clean');
        $this->form_validation->set_rules('quantity_step_size', 'Quantity Step Size', 'trim|xss_clean');
        $this->form_validation->set_rules('warranty_period', 'Warranty Period', 'trim|xss_clean');
        $this->form_validation->set_rules('guarantee_period', 'Guarantee Period', 'trim|xss_clean');
        $this->form_validation->set_rules('video', 'Video', 'trim|xss_clean');
        $this->form_validation->set_rules('video_type', 'Video Type', 'trim|xss_clean');
        $this->form_validation->set_rules('deliverable_type', 'Deliverable Type', 'trim|xss_clean');
        $this->form_validation->set_rules('product_identity', 'product_identity', 'trim|xss_clean');

        if (isset($_POST['video_type']) && $_POST['video_type'] != '') {
            if ($_POST['video_type'] == 'youtube' || $_POST['video_type'] == 'vimeo') {
                $this->form_validation->set_rules('video', 'Video link', 'trim|required|xss_clean', array('required' => " Please paste a %s in the input box. "));
            } else {
                $this->form_validation->set_rules('pro_input_video', 'Video file', 'trim|required|xss_clean', array('required' => " Please choose a %s to be set. "));
            }
        }

        if (isset($_POST['download_allowed']) && $_POST['download_allowed'] != '' && !empty($_POST['download_allowed']) && $_POST['download_allowed'] == '1') {
            $this->form_validation->set_rules('download_link_type', 'Download Link Type', 'required|xss_clean');
            if (isset($_POST['download_link_type']) && $_POST['download_link_type'] != '' && !empty($_POST['download_link_type']) && $_POST['download_link_type'] == 'self_hosted') {
                $this->form_validation->set_rules('pro_input_zip', 'pro_input_zip', 'required|xss_clean');
            }
            if (isset($_POST['download_link_type']) && $_POST['download_link_type'] != '' && !empty($_POST['download_link_type']) && $_POST['download_link_type'] == 'add_link') {
                $this->form_validation->set_rules('download_link', 'Digital Product URL/Link', 'required|xss_clean');
            }
        }

        $_POST['variants_ids'] = (isset($_POST['variants_ids']) && !empty($_POST['variants_ids'])) ?  explode(",", $this->input->post('variants_ids', true)) : NULL;
        $_POST['variant_price'] = (isset($_POST['variant_price']) && !empty($_POST['variant_price'])) ?  explode(",", $this->input->post('variant_price', true)) : NULL;
        $_POST['variant_special_price'] = (isset($_POST['variant_special_price']) && !empty($_POST['variant_special_price'])) ?  explode(",", $this->input->post('variant_special_price', true)) : NULL;
        $_POST['variant_images'] = (isset($_POST['variant_images']) && !empty($_POST['variant_images'])) ? explode(",", $this->input->post('variant_images', true)) : [];
        $_POST['variant_sku'] = (isset($_POST['variant_sku']) && !empty($_POST['variant_sku'])) ?  explode(",", $this->input->post('variant_sku', true)) : NULL;
        $_POST['variant_total_stock'] = (isset($_POST['variant_total_stock']) && !empty($_POST['variant_total_stock'])) ?  explode(",", $this->input->post('variant_total_stock', true)) : NULL;
        $_POST['variant_level_stock_status'] = (isset($_POST['variant_level_stock_status']) && !empty($_POST['variant_level_stock_status'])) ?  explode(",", $this->input->post('variant_level_stock_status', true)) : NULL;
        $_POST['other_images'] = (isset($_POST['other_images']) && !empty($_POST['other_images'])) ? explode(",", $this->input->post('other_images', true)) : [];
        $_POST['status'] = (isset($_POST['status']) && ($_POST['status'] != '')) ? $this->input->post('status', true) : 1;
        $_POST['edit_variant_id'] = (isset($_POST['edit_variant_id']) && !empty($_POST['edit_variant_id'])) ? explode(",", $this->input->post('edit_variant_id', true)) : [];


        if (isset($_POST['tags']) && $_POST['tags'] != '') {
            $_POST['tags'] = json_decode($_POST['tags'], 1);
            $tags = is_array($_POST['tags']) ? array_column($this->input->post('tags', true) ?? '', 'value') : [];
            $_POST['tags'] = implode(",", $tags);
        }

        if (isset($_POST['is_cancelable']) && $_POST['is_cancelable'] == '1') {
            $this->form_validation->set_rules('cancelable_till', 'Till which status', 'trim|required|xss_clean|in_list[received,processed,shipped]');
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
        // If product type is simple or digital	 		
        if (isset($_POST['product_type']) && $_POST['product_type'] == 'simple_product' || $_POST['product_type'] == 'digital_product') {

            $this->form_validation->set_rules('simple_price', 'Price', 'trim|required|numeric|greater_than_equal_to[' . $this->input->post('simple_special_price') . ']|xss_clean');
            $this->form_validation->set_rules('simple_special_price', 'Special Price', 'trim|numeric|less_than_equal_to[' . $this->input->post('simple_price') . ']|xss_clean');

            if (
                isset($_POST['simple_product_stock_status']) && in_array($_POST['simple_product_stock_status'], array('0', '1'))
            ) {

                $this->form_validation->set_rules('product_sku', 'SKU', 'trim|xss_clean');
                $this->form_validation->set_rules('product_total_stock', 'Total Stock', 'trim|required|numeric|xss_clean');
                $this->form_validation->set_rules('simple_product_stock_status', 'Stock Status', 'trim|required|numeric|xss_clean');
            }
        } elseif (isset($_POST['product_type']) && $_POST['product_type'] == 'variable_product') { //If product type is variant	
            if (isset($_POST['variant_stock_status']) && $_POST['variant_stock_status'] == '0') {
                if (
                    $_POST['variant_stock_level_type'] == "product_level"
                ) {

                    $this->form_validation->set_rules('sku_pro_type', 'SKU', 'trim|xss_clean');
                    $this->form_validation->set_rules('total_stock_variant_type', 'Total Stock', 'trim|required|xss_clean');
                    $this->form_validation->set_rules('variant_stock_status', 'Stock Status', 'trim|required|xss_clean');
                    if (isset($_POST['variant_price']) && isset($_POST['variant_special_price'])) {
                        foreach ($_POST['variant_price'] as $key => $value) {
                            $this->form_validation->set_rules('variant_price[' . $key . ']', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price[' . $key . ']') . ']');
                            $this->form_validation->set_rules('variant_special_price[' . $key . ']', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price[' . $key . ']') . ']');
                        }
                    } else {
                        $this->form_validation->set_rules('variant_price', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price') . ']');
                        $this->form_validation->set_rules('variant_special_price', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price') . ']');
                    }
                } else {
                    if (isset($_POST['variant_price']) && isset($_POST['variant_special_price']) && isset($_POST['variant_sku']) && isset($_POST['variant_total_stock']) && isset($_POST['variant_stock_status'])) {
                        foreach ($_POST['variant_price'] as $key => $value) {
                            $this->form_validation->set_rules('variant_price[' . $key . ']', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price[' . $key . ']') . ']');
                            $this->form_validation->set_rules('variant_special_price[' . $key . ']', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price[' . $key . ']') . ']');
                            $this->form_validation->set_rules('variant_sku[' . $key . ']', 'SKU', 'trim|xss_clean');
                            $this->form_validation->set_rules('variant_total_stock[' . $key . ']', 'Total Stock', 'trim|required|numeric|xss_clean');
                            $this->form_validation->set_rules('variant_level_stock_status[' . $key . ']', 'Stock Status', 'trim|required|numeric|xss_clean');
                        }
                    } else {
                        $this->form_validation->set_rules('variant_price', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price') . ']');
                        $this->form_validation->set_rules('variant_special_price', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price') . ']');
                        $this->form_validation->set_rules('variant_sku', 'SKU', 'trim|xss_clean');
                        $this->form_validation->set_rules('variant_total_stock', 'Total Stock', 'trim|required|numeric|xss_clean');
                        $this->form_validation->set_rules('variant_level_stock_status', 'Stock Status', 'trim|required|numeric|xss_clean');
                    }
                }
            } else {
                if (isset($_POST['variant_price']) && isset($_POST['variant_special_price'])) {
                    foreach ($_POST['variant_price'] as $key => $value) {
                        $this->form_validation->set_rules('variant_price[' . $key . ']', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price[' . $key . ']') . ']');
                        $this->form_validation->set_rules('variant_special_price[' . $key . ']', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price[' . $key . ']') . ']');
                    }
                } else {
                    $this->form_validation->set_rules('variant_price', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price') . ']');
                    $this->form_validation->set_rules('variant_special_price', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price') . ']');
                }
            }
        } elseif (isset($_POST['product_type']) && $_POST['product_type'] == 'variable_product') { //If product type is variant	
            if (isset($_POST['variant_stock_status']) && $_POST['variant_stock_status'] == '0') {
                if ($_POST['variant_stock_level_type'] == "product_level") {

                    $this->form_validation->set_rules('sku_pro_type', 'SKU', 'trim|xss_clean');
                    $this->form_validation->set_rules('total_stock_variant_type', 'Variable Product Total Stock', 'trim|required|xss_clean');
                    $this->form_validation->set_rules('variant_stock_status', 'Stock Status', 'trim|required|xss_clean');
                    if (isset($_POST['variant_price']) && isset($_POST['variant_special_price'])) {
                        foreach ($_POST['variant_price'] as $key => $value) {
                            $this->form_validation->set_rules('variant_price[' . $key . ']', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price[' . $key . ']') . ']');
                            $this->form_validation->set_rules('variant_special_price[' . $key . ']', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price[' . $key . ']') . ']');
                        }
                    } else {
                        $this->form_validation->set_rules('variant_price', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price') . ']');
                        $this->form_validation->set_rules('variant_special_price', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price') . ']');
                    }
                } else {
                    if (isset($_POST['variant_price']) && isset($_POST['variant_special_price']) && isset($_POST['variant_sku']) && isset($_POST['variant_total_stock']) && isset($_POST['variant_stock_status'])) {
                        foreach ($_POST['variant_price'] as $key => $value) {
                            $this->form_validation->set_rules('variant_price[' . $key . ']', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price[' . $key . ']') . ']');
                            $this->form_validation->set_rules('variant_special_price[' . $key . ']', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price[' . $key . ']') . ']');
                            $this->form_validation->set_rules('variant_sku[' . $key . ']', 'SKU', 'trim|xss_clean');
                            $this->form_validation->set_rules('variant_total_stock[' . $key . ']', 'Variants Total Stock', 'trim|required|numeric|xss_clean');
                            $this->form_validation->set_rules('variant_level_stock_status[' . $key . ']', 'Stock Status', 'trim|required|numeric|xss_clean');
                        }
                    } else {
                        $this->form_validation->set_rules('variant_price', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price') . ']');
                        $this->form_validation->set_rules('variant_special_price', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price') . ']');
                        $this->form_validation->set_rules('variant_sku', 'SKU', 'trim|xss_clean');
                        $this->form_validation->set_rules('variant_total_stock', 'Total Stock', 'trim|required|numeric|xss_clean');
                        $this->form_validation->set_rules('variant_level_stock_status', 'Stock Status', 'trim|required|numeric|xss_clean');
                    }
                }
            } else {
                if (isset($_POST['variant_price']) && isset($_POST['variant_special_price'])) {
                    foreach ($_POST['variant_price'] as $key => $value) {
                        $this->form_validation->set_rules('variant_price[' . $key . ']', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price[' . $key . ']') . ']');
                        $this->form_validation->set_rules('variant_special_price[' . $key . ']', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price[' . $key . ']') . ']');
                    }
                } else {
                    $this->form_validation->set_rules('variant_price', 'Price', 'trim|required|numeric|xss_clean|greater_than_equal_to[' . $this->input->post('variant_special_price') . ']');
                    $this->form_validation->set_rules('variant_special_price', 'Special Price', 'trim|numeric|xss_clean|less_than_equal_to[' . $this->input->post('variant_price') . ']');
                }
            }
        }


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
            return false;
        } else {

            $zipcodes_string = $_POST['deliverable_zipcodes'][0]; // Get the string from the array
            $zipcodes_array = explode(',', $zipcodes_string); // Convert the string to an array using comma as delimiter

            // Remove duplicate values and re-index the array
            $unique_zipcodes = array_values(array_unique($zipcodes_array));

            if (!empty($_POST['deliverable_zipcodes'])) {
                $_POST['zipcodes'] =  !empty($unique_zipcodes) ? implode(",", $unique_zipcodes) : [];
            } else {
                $_POST['zipcodes'] = NULL;
            }

            if (isset($_POST['deliverable_cities']) && !empty($_POST['deliverable_cities'])) {
                $_POST['cities'] = implode(",", $_POST['deliverable_cities']);
            } else {
                $_POST['cities'] = NULL;
            }
            $this->product_model->add_product($_POST);
            $this->response['error'] = false;
            $message = (isset($_POST['edit_product_id'])) ? 'Product Updated Successfully' : 'Product Added Successfully';
            $this->response['message'] = $message;
            print_r(json_encode($this->response));
        }
    }


    public function upload_media()
    //upload media
    //documents[]:FILES
    {
           if (empty($_FILES['documents']['name'][0])) {
            $this->response['error'] = true;
            $this->response['message'] = "Upload at least one media file !";
            print_r(json_encode($this->response));
            return;
        }
     
            $year = date('Y');
            $target_path = FCPATH . MEDIA_PATH . $year . '/';
            $sub_directory = MEDIA_PATH . $year . '/';

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
                        $media_ids[] = $media_id = $this->media_model->set_media($temp_array); 
                        resize_image($temp_array,  $target_path, $media_id);
                        $other_images_new_name[$i] = $temp_array['file_name'];
                    }
                } else {

                    $_FILES['temp_image']['name'] = $files['documents']['name'][$i];
                    $_FILES['temp_image']['type'] = $files['documents']['type'][$i];
                    $_FILES['temp_image']['tmp_name'] = $files['documents']['tmp_name'][$i];
                    $_FILES['temp_image']['error'] = $files['documents']['error'][$i];
                    $_FILES['temp_image']['size'] = $files['documents']['size'][$i];
                    if (!$other_img->do_upload('temp_image')) {
                        $other_image_info_error = $other_img->display_errors();
                    }
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

            if (empty($_FILES) || $other_image_info_error != NULL) {
                $this->response['error'] = true;
                $this->response['message'] = (empty($_FILES)) ? "Files not Uploaded Successfully..!" :  $other_image_info_error;
                print_r(json_encode($this->response));
            } else {
                $this->response['error'] = false;
                $this->response['message'] = "Files Uploaded Successfully..!";
                print_r(json_encode($this->response));
            }
        
    }
    public function get_media()
    {
        /* 
        limit:25            // { default - 25 } optional
        offset:0            // { default - 0 } optional
        sort:               // { id } optional
        order:DESC/ASC      // { default - DESC } optional
        search:value        // {optional} 
        type:image          // {documents,spreadsheet,archive,video,audio,image}
        */
        if (!$this->verify_token()) {
            return false;
        }

        $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
        $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
        $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
        $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
        $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : '';
        $type = (isset($_POST['type']) && !empty(trim($_POST['type']))) ? $this->input->post('type', true) : '';
        $user_id = (isset($_POST['user_id']) && !empty(trim($_POST['user_id']))) ? $this->input->post('user_id', true) : '';

        $this->form_validation->set_rules('user_id', 'User id', 'trim|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $this->media_model->get_media($limit, $offset, $sort, $order, $search, $type, $user_id);
        }
    }

    public function get_zipcodes()
    {
        /*
              limit:10 {optional}
              offset:0 {optional}
              search:0 {optional}
          */
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('search', 'search', 'trim|xss_clean');


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {

            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $search = (isset($_POST['search']) &&  !empty(trim($_POST['search']))) ? $this->input->post('search', true) : '';
            $zipcodes = $this->Area_model->get_zipcodes($search, $limit, $offset);
            print_r(json_encode($zipcodes));
        }
    }

    public function get_attribute_set()
    {
        /*
            sort: ats.name              // { ats.name / ats.id } optional
            order:DESC/ASC      // { default - ASC } optional
            search:value        // {optional} 
            limit:10  {optional}
            offset:10  {optional}
       */
        $this->form_validation->set_rules('sort', 'sort', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        $this->form_validation->set_rules('search', 'search', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'Limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'Offset', 'trim|numeric|xss_clean');


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
            return false;
        } else {
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'ats.name';
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'ASC';
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = ($this->input->post('limit', true)) ? $this->input->post('limit', true) : NULL;
            $offset = ($this->input->post('offset', true)) ? $this->input->post('offset', true) : NULL;
            $result = $this->Attribute_model->get_attribute_set($sort, $order, $search, $limit, $offset);
            print_r(json_encode($result));
        }
    }


    public function get_attributes()
    {
        /*
            attribute_set_id:1  // {optional}
            sort: a.name              // { a.name / a.id } optional
            order:DESC/ASC      // { default - ASC } optional
            search:value        // {optional} 
            limit:10  {optional}
            offset:10  {optional}
       */
        $this->form_validation->set_rules('sort', 'sort', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        $this->form_validation->set_rules('search', 'search', 'trim|xss_clean');
        $this->form_validation->set_rules('attribute_set_id', 'attribute set id', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('limit', 'Limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'Offset', 'trim|numeric|xss_clean');


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
            return false;
        } else {
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'a.name';
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'ASC';
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = ($this->input->post('limit', true)) ? $this->input->post('limit', true) : NULL;
            $offset = ($this->input->post('offset', true)) ? $this->input->post('offset', true) : NULL;
            $attribute_set_id = (isset($_POST['attribute_set_id']) && !empty(trim($_POST['attribute_set_id']))) ? $this->input->post('attribute_set_id', true) : "";
            $result = $this->Attribute_model->get_attributes($sort, $order, $search, $attribute_set_id, $limit, $offset);
            print_r(json_encode($result));
        }
    }


    public function get_attribute_values()
    {
        /*
            attribute_id:1  // {optional}
            sort:a.name               // { a.name / a.id } optional
            order:DESC/ASC      // { default - ASC } optional
            search:value        // {optional} 
            limit:10  {optional}
            offset:10  {optional}
       */
        $this->form_validation->set_rules('sort', 'sort', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');
        $this->form_validation->set_rules('search', 'search', 'trim|xss_clean');
        $this->form_validation->set_rules('attribute_id', 'attribute id', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('limit', 'Limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'Offset', 'trim|numeric|xss_clean');


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
            return false;
        } else {
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'a.name';
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'ASC';
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = ($this->input->post('limit', true)) ? $this->input->post('limit', true) : NULL;
            $offset = ($this->input->post('offset', true)) ? $this->input->post('offset', true) : NULL;
            $attribute_id = (isset($_POST['attribute_id']) && !empty(trim($_POST['attribute_id']))) ? $this->input->post('attribute_id', true) : "";
            $result = $this->Attribute_model->get_attribute_value($sort, $order, $search, $attribute_id, $limit, $offset);
            print_r(json_encode($result));
        }
    }
    public function get_taxes()
    {


        $this->db->select('*');
        $types = $this->db->get('taxes')->result_array();
        if (!empty($types)) {
            for ($i = 0; $i < count($types); $i++) {
                $types[$i] = output_escaping($types[$i]);
            }
        }
        $this->response['error'] = false;
        $this->response['message'] = 'Taxes fetched successfully';
        $this->response['data'] = $types;
        print_r(json_encode($this->response));
    }
    public function delete_product()
    {
        /* Parameters to be passed
            product_id:28
        */
        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('product_id', 'Product Id', 'trim|required|numeric|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }
        $id = $this->input->post('product_id', true);
        if (delete_details(['product_id' => $id], 'product_variants')) {
            delete_details(['id' => $id], 'products');
            delete_details(['product_id' => $id], 'product_attributes');
            $response['error'] = false;
            $response['message'] = 'Deleted Successfully';
        } else {
            $response['error'] = true;
            $response['message'] = 'Something Went Wrong';
        }
        print_r(json_encode($response));
    }
    public function get_countries_data()
    {

        $this->form_validation->set_rules('search', 'search', 'trim|xss_clean');
        $this->form_validation->set_rules('offset', 'Offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('limit', 'Limit', 'trim|numeric|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $offset = ($this->input->post('offset', true)) ? $this->input->post('offset', true) : 0;
            $limit = ($this->input->post('limit', true)) ? $this->input->post('limit', true) : 25;
            $result = $this->product_model->get_country_list($search, $offset, $limit);
            print_r(json_encode($result));
        }
    }

    public function add_brand()
    {
        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('brand_input_name', 'name', 'trim|required|xss_clean');
        $this->form_validation->set_rules('brand_input_image', 'Image', 'trim|xss_clean');
        if (!$this->form_validation->run()) {

            $this->response['error'] = true;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            if (isset($_POST['edit_brand'])) {
                if (is_exist(['name' => $_POST['brand_input_name']], 'brands', $this->input->post('edit_brand', true))) {
                    $response["error"]   = true;
                    $response["message"] = "Name Already Exist ! Provide a unique name";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            } else {
                if (!$this->form_validation->is_unique($_POST['brand_input_name'], 'brands.name')) {
                    $response["error"]   = true;
                    $response["message"] = "Name Already Exist ! Provide a unique name";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            }

            $this->brand_model->add_brand($_POST);
            $this->response['error'] = false;
            $message = (isset($_POST['edit_brand'])) ? 'Brand Updated Successfully' : 'Brand Added Successfully';
            $this->response['message'] = $message;
            print_r(json_encode($this->response));
        }
    }

    public function get_brands_data()
    {

        $this->form_validation->set_rules('search', 'search', 'trim|xss_clean');
        $this->form_validation->set_rules('offset', 'Offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('limit', 'Limit', 'trim|numeric|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $offset = ($this->input->post('offset', true)) ? $this->input->post('offset', true) : 0;
            $limit = ($this->input->post('limit', true)) ? $this->input->post('limit', true) : 25;
            $result = $this->product_model->get_brand_list($search, $offset, $limit);
            print_r(json_encode($result));
        }
    }

    public function delete_brand()
    {


        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('id', 'Id', 'trim|required|numeric|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            $id = $this->input->post('id', true);
            if (delete_details(['id' => $id], 'brands')) {
                $response['error'] = false;
                $response['message'] = 'Deleted Successfully';
            } else {
                $response['error'] = true;
                $response['message'] = 'Something Went Wrong';
            }
            print_r(json_encode($response));
        }
    }

    public function send_digital_product_mail()
    {
        /*
             order_id : 1
             order_item_id : 101
             customer_email: abc123@gmail.com
             subject : this is test mail
             message : this is our first test mail for digital product
             username : Admin
             attachment : file url for attachment
      */
        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('order_id', 'order item id', 'trim|numeric|required|xss_clean');
        $this->form_validation->set_rules('order_item_id', 'order item id', 'trim|numeric|required|xss_clean');
        $this->form_validation->set_rules('customer_email', 'customer email', 'trim|valid_email|required|xss_clean');
        $this->form_validation->set_rules('subject', 'subject', 'trim|required|xss_clean');
        $this->form_validation->set_rules('message', 'message', 'trim|required|xss_clean');
        $this->form_validation->set_rules('username', 'username', 'trim|required|xss_clean');
        $this->form_validation->set_rules('attachment', 'attachment', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            echo json_encode($this->response);
            return false;
        } else {
            $mail_data = [
                'email' => $_POST['customer_email'],
                'subject' => $_POST['subject'],
                'message' => $_POST['message'],
                'username' => $_POST['username'],
                'pro_input_file' => $_POST['attachment'],
            ];
            $mail = $this->order_model->send_digital_product($mail_data);
            if ($mail['error'] == true) {
                $this->response['error'] = true;
                $this->response['message'] = "Cannot send mail. You can try to send mail manually.";
                $this->response['data'] = $mail['message'];
                echo json_encode($this->response);
                return false;
            } else {
                $this->response['error'] = false;
                $this->response['message'] = 'Mail sent successfully.';
                $this->response['data'] = array();
                echo json_encode($this->response);
                update_details(['active_status' => 'delivered'], ['id' => $this->input->post('order_item_id', true)], 'order_items');
                update_details(['is_sent' => 1], ['id' => $this->input->post('order_item_id', true)], 'order_items');
                $data = array(
                    'order_id' => $this->input->post('order_id', true),
                    'order_item_id' => $this->input->post('order_item_id', true),
                    'subject' => $this->input->post('subject', true),
                    'message' => $this->input->post('message', true),
                    'file_url' => $this->input->post('attachment', true),
                );
                insert_details($data, 'digital_orders_mails');
                return false;
            }
        }
    }

    public function get_digital_order_mails()
    {
        /*
                order_id:156
                order_item_id:5
                search : Search keyword // { optional }
                limit:25                // { default - 10 } optional
                offset:0                // { default - 0 } optional
                sort: id                // { default - id } optional
                order:DESC/ASC          // { default - DESC } optional
    
         */
        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('order_id', 'Order Id', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order_item_id', 'order item id', 'trim|numeric|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            $mail_data = $this->order_model->get_digital_order_mail_list(true);

            if (isset($mail_data['rows']) && !empty($mail_data['rows'])) {
                $this->response['error'] = false;
                $this->response['message'] = "Data retrived successfully.";
                $this->response['data'] = $mail_data;
                echo json_encode($this->response);
                return false;
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Data not retrived successfully.';
                $this->response['data'] = array();
                echo json_encode($this->response);
                return false;
            }
        }
    }
    public function manage_stock()
    {

        /*
            product_variant_id:156
            quantity:5
            type:add/subtract 

            if type is subtract then need to pass current_stock
            current_stock:102
        */

        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('product_variant_id', 'Product variant id', 'trim|numeric|required|xss_clean');
        $this->form_validation->set_rules('quantity', 'Quantity', 'trim|required|numeric|xss_clean');
        $this->form_validation->set_rules('type', 'Type', 'trim|required|xss_clean');



        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            if ((isset($_POST['type']) && $_POST['type'] == 'add')) {
                update_stock($this->input->post('product_variant_id', true), array($this->input->post('quantity', true)), 'plus');
                $this->response['error'] = false;
                $this->response['message'] = 'Stock Updated Successfully';
                print_r(json_encode($this->response));
                return false;
            } else if (isset($_POST['type']) && $_POST['type'] == 'subtract') {
                if ($this->input->post('quantity', true) > $this->input->post('current_stock', true)) {
                    $this->response['error'] = true;
                    $this->response['message'] = "Subtracted stock cannot be greater than current stock";
                    print_r(
                        json_encode($this->response)
                    );
                    return false;
                }
                update_stock($this->input->post('product_variant_id', true), array($this->input->post('quantity', true)));
                $this->response['error'] = false;
                $this->response['message'] = 'Stock Updated Successfully';
                print_r(json_encode($this->response));
                return false;
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Stock Not Updated';
                print_r(json_encode($this->response));
                return false;
            }
        }
    }

    /* add_product_faqs */
    public function add_product_faqs()
    {
        $this->form_validation->set_rules('product_id', 'Product Id', 'trim|numeric|xss_clean|required');
        $this->form_validation->set_rules('question', 'Question', 'trim|xss_clean|required');
        $this->form_validation->set_rules('answer', 'Answer', 'trim|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return;
        } else {
            $product_id = $this->input->post('product_id', true);
            $user_id = isset($this->user_details['id']) && $this->user_details['id'] !== null ? $this->user_details['id'] : '';
            $question = $this->input->post('question', true);
            $answer = $this->input->post('answer', true);
            $user = fetch_users($user_id);
            if (empty($user)) {
                $this->response['error'] = true;
                $this->response['message'] = "Seller not found!";
                $this->response['data'] = [];
                print_r(json_encode($this->response));
                return false;
            }
            $data = array(
                'product_id' => $product_id,
                'user_id' => $user_id,
                'question' => $question,
                'answer' => (isset($answer) && !empty($answer)) ? $answer : "",
                'answer_by' => (isset($answer) && !empty($answer)) ? $user_id : "",
            );

            $insert_id = $this->product_model->add_product_faqs($data);
            if (!empty($insert_id)) {
                $result = $this->product_model->get_product_faqs($insert_id, $product_id, $user_id);
                $this->response['error'] = false;
                $this->response['message'] =  'FAQs added Successfully';
                $this->response['data'] = $result['data'];
            } else {
                $this->response['error'] = true;
                $this->response['message'] =  'FAQs Not Added';
                $this->response['data'] = (!empty($this->response['data'])) ? $this->response['data'] : [];
            }
            print_r(json_encode($this->response));
        }
    }

    /*  get_product_faqs */
    public function get_product_faqs()
    {
        /*
            id:2    // {optional}
            product_id:25   // {optional}
            user_id:1       // {optional}
            search : Search keyword // { optional }
            limit:25                // { default - 10 } optional
            offset:0                // { default - 0 } optional
            sort: id                // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('id', 'FAQs ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('product_id', 'Product ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('user_id', 'User ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules(
            'search',
            'Search keyword',
            'trim|xss_clean'
        );
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {

            $id = (isset($_POST['id']) && is_numeric($_POST['id']) && !empty(trim($_POST['id']))) ? $this->input->post('id', true) : "";
            $product_id = (isset($_POST['product_id']) && is_numeric($_POST['product_id']) && !empty(trim($_POST['product_id']))) ? $this->input->post('product_id', true) : "";
            $user_id = (isset($_POST['user_id']) && is_numeric($_POST['user_id']) && !empty(trim($_POST['user_id']))) ? $this->input->post('user_id', true) : "";
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 10;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'DESC';
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
            
            $result = $this->product_model->get_product_faqs(
                $id,
                $product_id,
                '',
                $search,
                $offset,
                $limit,
                $sort,
                $order,
                true,
                $user_id
            );
            print_r(json_encode($result));
        }
    }

    public function delete_product_faq()
    {
        $this->form_validation->set_rules('id', 'FAQ id', 'trim|xss_clean|required');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $this->product_faqs_model->delete_faq($this->input->post('id', true));

            $this->response['error'] = false;
            $this->response['message'] = 'FAQ Deleted Successfully';

            print_r(json_encode($this->response));
        }
    }


    public function edit_product_faq()
    {

        $this->form_validation->set_rules('id', 'FAQ id', 'trim|xss_clean|required');
        $this->form_validation->set_rules('answer', 'Answer', 'trim|xss_clean|required');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            $user_id = isset($this->user_details['id']) && $this->user_details['id'] !== null ? $this->user_details['id'] : '';
            $edit_data = [
                'answer' => $this->input->post('answer',true),
                'answered_by' => $user_id,
            ];
            $this->product_faqs_model->edit_product_faqs($edit_data, $this->input->post('id', true));

            $this->response['error'] = false;
            $this->response['message'] = 'FAQ Update Successfully';

            print_r(json_encode($this->response));
        }
    }

    public function add_tax()
    {

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('title', 'Title', 'trim|required|xss_clean');
        $this->form_validation->set_rules('percentage', 'Percentage', 'trim|required|numeric|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
        } else {
            if (isset($_POST['edit_tax_id'])) {
                if (is_exist(['title' => $this->input->post('title', true)], 'taxes', $this->input->post('edit_tax_id', true))) {
                    $response["error"]   = true;
                    $response["message"] = "Name Already Exist ! Provide a unique name";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            } else {
                if (is_exist(['title' => $this->input->post('title', true)], 'taxes')) {
                    $response["error"]   = true;
                    $response["message"] = "Name Already Exist ! Provide a unique name";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            }

            $this->Tax_model->add_tax($_POST);
            $this->response['error'] = false;
            $this->response['message'] = isset($_POST['edit_tax_id']) ? 'Tax Details Updated Successfully' : 'Tax Details Added Successfully';
            print_r(json_encode($this->response));
        }
    }

    public function add_category()
    {

        if (!$this->verify_token()) {
            return false;
        }

        if (isset($_POST['edit_category'])) {
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
        $this->form_validation->set_rules('category_parent', 'Parent Id', 'trim|xss_clean');

        if (isset($_POST['edit_category'])) {
            $this->form_validation->set_rules('category_input_image', 'Image', 'trim|xss_clean');
        } else {
            $this->form_validation->set_rules('category_input_image', 'Image', 'trim|required|xss_clean', array('required' => 'Category image is required'));
        }

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            $this->category_model->add_category($_POST);
            $this->response['error'] = false;
            $message = (isset($_POST['edit_category'])) ? 'Category Updated Successfully' : 'Category Added Successfully';
            $this->response['message'] = $message;
            print_r(json_encode($this->response));
        }
    }

    public function delete_category()
    {
        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            $category_id = isset($_POST['id']) && !empty($_POST['id']) ? $this->input->post('id', true) : '';
            if (!is_exist(['id' => $category_id], 'categories')) {
                $this->response['error'] = true;
                $this->response['message'] = 'Category is not exist in your database';
                print_r(json_encode($this->response));
                return false;
            } else {
                if ($this->category_model->delete_category($category_id) == TRUE) {
                    $this->response['error'] = false;
                    $this->response['message'] = 'Deleted Successfully';
                    print_r(json_encode($this->response));
                }
            }
        }
    }

    public function add_slider()
    {
        if (!$this->verify_token()) {
            return false;
        }

        if (isset($_POST['edit_slider'])) {
            if (print_msg(!has_permissions('update', 'home_slider'), PERMISSION_ERROR_MSG, 'home_slider')) {
                return false;
            }
        } else {
            if (print_msg(!has_permissions('create', 'home_slider'), PERMISSION_ERROR_MSG, 'home_slider')) {
                return false;
            }
        }

        $this->form_validation->set_rules('slider_type', 'Slider Type', 'trim|xss_clean|required');
        $this->form_validation->set_rules('image', 'Slider Image', 'trim|required|xss_clean', array('required' => 'Slider image is required'));

        if (isset($_POST['slider_type']) && $_POST['slider_type'] == 'categories') {
            $this->form_validation->set_rules('category_id', 'Category id', 'trim|required|xss_clean');
        }
        if (isset($_POST['slider_type']) && $_POST['slider_type'] == 'products') {
            $this->form_validation->set_rules('product_id', 'Product', 'trim|required|xss_clean');
        }
        if (isset($_POST['slider_type']) && $_POST['slider_type'] == 'slider_url') {
            $this->form_validation->set_rules('link', 'Link', 'trim|required|xss_clean');
        }
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            $this->Slider_model->add_slider($_POST);
            $this->response['error'] = false;
            $message = (isset($_POST['edit_slider'])) ? 'Slider Updated Successfully' : 'Slider Added Successfully';
            $this->response['message'] = $message;
            print_r(json_encode($this->response));
        }
    }

    public function update_customer_wallet()
    {

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('user_id', 'User ID', 'trim|required|xss_clean');
        $this->form_validation->set_rules('type', 'Type', 'trim|required|xss_clean');
        $this->form_validation->set_rules('amount', 'Amount', 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('message', 'Message', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            if ($_POST['type'] == 'debit' || $_POST['type'] == 'credit') {
                $message = (isset($_POST['message']) && !empty($_POST['message'])) ? $this->input->post('message', true) : "Balance " . $_POST['type'] . "ed.";
                $response = update_wallet_balance($_POST['type'], $_POST['user_id'], $_POST['amount'], $message);
                print_r(json_encode($response));
            }
        }
    }

    public function add_pickup_location()
    {
        /* 
         pickup_location : Croma Digital
         name:admin // shipper's name
         email : admin123@gmail.com
         phone : 1234567890
         address : 201,time square,mirjapar hignway // note : must add specific address like plot_no/street_no/office_no etc.
         address2 : near prince lawns
         city : bhuj
         state : gujarat
         country : india
         pincode : 370001
         latitude : 23.5643445644
         longitude : 69.312531534
         status : 0/1 {default :0}
        */

        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('pickup_location', 'Pickup Location', 'trim|required|xss_clean');
        $this->form_validation->set_rules('name', "Shipper's Name", 'trim|required|xss_clean');
        $this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean|valid_email');
        $this->form_validation->set_rules('phone', 'Phone', 'trim|required|numeric|xss_clean');
        $this->form_validation->set_rules('address', 'Address', 'trim|required|xss_clean');
        $this->form_validation->set_rules('address2', 'Address 2', 'trim|required|xss_clean');
        $this->form_validation->set_rules('city', 'City', 'trim|required|xss_clean');
        $this->form_validation->set_rules('state', 'State', 'trim|required|xss_clean');
        $this->form_validation->set_rules('country', 'Country', 'trim|required|xss_clean');
        $this->form_validation->set_rules(
            'pincode',
            'Pincode',
            'trim|required|xss_clean'
        );
        $this->form_validation->set_rules('latitude', 'Latitude', 'trim|required|xss_clean');
        $this->form_validation->set_rules('longitude', 'Longitude', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            $this->Pickup_location_model->add_pickup_location($_POST);
            $this->response['error'] = false;
            $this->response['message'] = 'Pickup Location added successfully';
            print_r(json_encode($this->response));
        }
    }

    public function get_pickup_locations()
    {
        /*
            search : Search keyword // { optional }
            limit:25                // { default - 10 } optional
            offset:0                // { default - 0 } optional
            sort: id                // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('search', 'Search keyword', 'trim|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : "";
            $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 10;
            $offset = (isset($_POST['offset']) && is_numeric($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
            $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order',true) : 'DESC';
            $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort',true) : 'id';

            $res = $this->Pickup_location_model->get_list($table = 'pickup_locations', true);
            if (isset($res) && !empty($res)) {
                $this->response['error'] = false;
                $this->response['message'] = 'Data retrived successfully';
                $this->response['data'] = $res;
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Data not retrived';
                $this->response['data'] = array();
            }
            print_r(json_encode($this->response));
        }
    }

    public function get_slider_list()
    {

        $res = $this->Slider_model->get_slider();
        if (isset($res) && !empty($res)) {
            $this->response['error'] = false;
            $this->response['message'] = 'Data retrived successfully';
            $this->response['data'] = $res;
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Data not retrived';
            $this->response['data'] = array();
        }
        print_r(json_encode($this->response));
    }

    public  function delete_slider()
    {
        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('id', 'Id', 'trim|xss_clean|numeric|required');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {
            if (delete_details(['id' => $this->input->post('id', true)], 'sliders') == TRUE) {
                $this->response['error'] = false;
                $this->response['message'] = 'Slider Deleted Successfully';
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Something Went Wrong';
            }
            print_r(json_encode($this->response));
        }
    }

    public function add_faq()
    {
        if (!$this->verify_token()) {
            return false;
        }
        if (isset($_POST['edit_faq'])) {
            if (print_msg(!has_permissions('update', 'faq'), PERMISSION_ERROR_MSG, 'faq')) {
                return false;
            }
        } else {
            if (print_msg(!has_permissions('create', 'faq'), PERMISSION_ERROR_MSG, 'faq')) {
                return false;
            }
        }

        $this->form_validation->set_rules('question', 'Question', 'trim|required|xss_clean');
        $this->form_validation->set_rules('answer', 'Answer', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            if (isset($_POST['edit_faq'])) {
                if (is_exist(['question' => $_POST['question'], 'status' => '1'], 'faqs', $this->input->post('edit_faq', true))) {
                    $response["error"]   = true;
                    $response["message"] = "Question Already Exist !";
                    $response['csrfName'] = $this->security->get_csrf_token_name();
                    $response['csrfHash'] = $this->security->get_csrf_hash();
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            } else {
                if (is_exist(['question' => $_POST['question'], 'status' => '1'], 'faqs')) {
                    $response["error"]   = true;
                    $response["message"] = "Question Already Exist !";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            }
            $this->faq_model->add_faq($_POST);
            $this->response['error'] = false;
            $message = (isset($_POST['edit_faq'])) ? 'Faq Updated Successfully' : 'Faq Added Successfully';
            $this->response['message'] = $message;
            print_r(json_encode($this->response));
        }
    }

    public function delete_faq()
    {
        if (!$this->verify_token()) {
            return false;
        }
        if (print_msg(!has_permissions('delete', 'faq'), PERMISSION_ERROR_MSG, 'faq', false)) {
            return false;
        }
        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            if (update_details(['status' => '0'], ['id' => $this->input->post('id', true)], 'faqs') == TRUE) {
                $this->response['error'] = false;
                $this->response['message'] = 'Faq Deleted Successfully';
                print_r(json_encode($this->response));
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Something Went Wrong';
                print_r(json_encode($this->response));
            }
        }
    }

    public function get_faqs()
    {
        /*
    id:2    // {optional}
    search : Search keyword // { optional }
    limit:25                // { default - 10 } optional
    offset:0                // { default - 0 } optional
    sort: id                // { default - id } optional
    order:DESC/ASC          // { default - DESC } optional
    */

        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('id', 'FAQs ID', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('search', 'Search keyword', 'trim|xss_clean');
        $this->form_validation->set_rules('limit', 'limit', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('offset', 'offset', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('sort', 'sort', 'trim|xss_clean');
        $this->form_validation->set_rules('order', 'order', 'trim|xss_clean|in_list[DESC,ASC]');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $id = $this->input->post('id');
            $search = $this->input->post('search');
            $limit = $this->input->post('limit', true) ?? 10;
            $offset = $this->input->post('offset', true) ?? 0;
            $order = $this->input->post('order') ?? 'DESC';
            $sort = $this->input->post('sort') ?? 'id';

            $result = $this->faq_model->get_faq_list(
                $id,
                $search,
                $offset,
                $limit,
                $sort,
                $order
            );
        }

        print_r(json_encode($result));
    }

    public function delete_tax()
    {
        if (!$this->verify_token()) {
            return false;
        }

        if (!has_permissions('delete', 'tax')) {
            print_msg(PERMISSION_ERROR_MSG, 'tax', false);
            return false;
        }

        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $id = $this->input->post('id');
            if (!is_exist(['id' => $id], 'taxes')) {
                $this->response['error'] = true;
                $this->response['message'] = 'Tax is not exist in your database';
            } else {
                if (delete_details(['id' => $id], 'taxes')) {
                    $this->response['error'] = false;
                    $this->response['message'] = 'Tax Deleted Successfully';
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Something Went Wrong';
                }
            }
        }

        echo json_encode($this->response);
    }

    public function add_delivery_boy()
    {
        if (!$this->verify_token()) {
            return false;
        }
        if (isset($_POST['edit_delivery_boy'])) {
            if (print_msg(!has_permissions('update', 'delivery_boy'), PERMISSION_ERROR_MSG, 'delivery_boy')) {
                return true;
            }
        } else {
            if (print_msg(!has_permissions('create', 'delivery_boy'), PERMISSION_ERROR_MSG, 'delivery_boy')) {
                return true;
            }
        }

        $this->form_validation->set_rules('name', 'Name', 'trim|required|xss_clean');
        $this->form_validation->set_rules('email', 'Mail', 'trim|required|xss_clean');
        $this->form_validation->set_rules('mobile', 'Mobile', 'trim|required|xss_clean|min_length[5]');
        if (!isset($_POST['edit_delivery_boy'])) {
            $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean|min_length[6]');
            $this->form_validation->set_rules('confirm_password', 'Confirm password', 'trim|required|matches[password]|xss_clean');
        }
        $this->form_validation->set_rules('address', 'Address', 'trim|required|xss_clean');
        $this->form_validation->set_rules('bonus_type', 'Bonus Type', 'trim|required|xss_clean');
        $bonus = 0;

        if (isset($_POST['bonus_type']) && $_POST['bonus_type'] == 'fixed_amount_per_order') {
            $this->form_validation->set_rules('bonus_amount', 'Bonus Amount', 'trim|required|numeric|xss_clean');
            $bonus = $this->input->post('bonus_amount');
        } elseif (isset($_POST['bonus_type']) && $_POST['bonus_type'] == 'percentage_per_order') {
            $this->form_validation->set_rules('bonus_percentage', 'Bonus Percentage', 'trim|required|numeric|xss_clean');
            $bonus = $this->input->post('bonus_percentage');
        }

        if (!isset($_POST['edit_delivery_boy'])) {
            if (isset($_FILES) && !empty($_FILES) && count($_FILES['driving_license']['name']) < 2) {
                $this->form_validation->set_rules('driving_license', 'driving_license', 'trim|required|xss_clean', array('required' => 'Please add front and back image of Driving license'));
            }
            if (isset($_FILES) && !empty($_FILES) && count($_FILES['driving_license']['name']) > 2) {
                $this->form_validation->set_rules('driving_license', 'driving_license', 'trim|required|xss_clean', array('required' => 'You can only choose two images'));
            }
        }

        if (isset($_POST['edit_delivery_boy'])) {
            $delivery_boy_data = fetch_details('users', ['id' => $_POST['edit_delivery_boy']], 'driving_license');
            $driving_license = explode(',', $delivery_boy_data[0]['driving_license']);
        }

        if (isset($_POST['edit_delivery_boy'])) {
            if (isset($_FILES) && !empty($_FILES) && !empty($_FILES['driving_license']['name'][0]) && count($_FILES['driving_license']['name']) < 2) {
                $this->form_validation->set_rules('driving_license', 'driving_license', 'trim|required|xss_clean', array('required' => 'Please add front and back image of Driving license'));
            } elseif (isset($driving_license) && !empty($driving_license[0]) && count($driving_license) < 2) {
                $this->form_validation->set_rules('driving_license', 'driving_license', 'trim|required|xss_clean', array('required' => 'Please add front and back image of Driving license'));
            }
            if (isset($_FILES) && !empty($_FILES) && !empty($_FILES['driving_license']['name'][0]) && count($_FILES['driving_license']['name']) > 2) {
                $this->form_validation->set_rules('driving_license', 'driving_license', 'trim|required|xss_clean', array('required' => 'You can only choose two images'));
            } elseif (isset($driving_license) && !empty($driving_license[0]) && count($driving_license) > 2) {
                $this->form_validation->set_rules('driving_license', 'driving_license', 'trim|required|xss_clean', array('required' => 'You can only choose two images'));
            }
        }

        if (!$this->form_validation->run()) {

            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            if (!file_exists(FCPATH . DELIVERY_BOY_DOCUMENTS_PATH)) {
                mkdir(FCPATH . DELIVERY_BOY_DOCUMENTS_PATH, 0777);
            }

            $temp_array = array();
            $files = $_FILES;
            $images_new_name_arr = array();
            $images_info_error = "";
            $allowed_media_types = implode('|', allowed_media_types());
            $config = [
                'upload_path' =>  FCPATH . DELIVERY_BOY_DOCUMENTS_PATH,
                'allowed_types' => $allowed_media_types,
                'max_size' => 8000,
            ];


            if (isset($files['driving_license']) && !empty($files['driving_license']['name'][0]) && isset($files['driving_license']['name'][0])) {
                $other_image_cnt = count((array)$files['driving_license']['name']);
                $other_img = $this->upload;
                $other_img->initialize($config);


                if (isset($_POST['edit_delivery_boy']) && !empty($_POST['edit_delivery_boy']) && isset($delivery_boy_data[0]['driving_license']) && !empty($delivery_boy_data[0]['driving_license'])) {
                    $old_logo = explode('/', $delivery_boy_data[0]['driving_license']);
                    delete_images(DELIVERY_BOY_DOCUMENTS_PATH, $old_logo[2]);
                }
                for ($i = 0; $i < $other_image_cnt; $i++) {

                    if (!empty($_FILES['driving_license']['name'][$i])) {

                        $_FILES['temp_image']['name'] = $files['driving_license']['name'][$i];
                        $_FILES['temp_image']['type'] = $files['driving_license']['type'][$i];
                        $_FILES['temp_image']['tmp_name'] = $files['driving_license']['tmp_name'][$i];
                        $_FILES['temp_image']['error'] = $files['driving_license']['error'][$i];
                        $_FILES['temp_image']['size'] = $files['driving_license']['size'][$i];
                        if (!$other_img->do_upload('temp_image')) {
                            $images_info_error = 'driving_license :' . $images_info_error . ' ' . $other_img->display_errors();
                        } else {
                            $temp_array = $other_img->data();
                            resize_review_images($temp_array, FCPATH . DELIVERY_BOY_DOCUMENTS_PATH);
                            $images_new_name_arr[$i] = DELIVERY_BOY_DOCUMENTS_PATH . $temp_array['file_name'];
                        }
                    } else {
                        $_FILES['temp_image']['name'] = $files['driving_license']['name'][$i];
                        $_FILES['temp_image']['type'] = $files['driving_license']['type'][$i];
                        $_FILES['temp_image']['tmp_name'] = $files['driving_license']['tmp_name'][$i];
                        $_FILES['temp_image']['error'] = $files['driving_license']['error'][$i];
                        $_FILES['temp_image']['size'] = $files['driving_license']['size'][$i];
                        if (!$other_img->do_upload('temp_image')) {
                            $images_info_error = $other_img->display_errors();
                        }
                    }
                }
                //Deleting Uploaded attachments if any overall error occured
                if ($images_info_error != NULL || !$this->form_validation->run()) {
                    if (isset($images_new_name_arr) && !empty($images_new_name_arr || !$this->form_validation->run())) {
                        foreach ($images_new_name_arr as $key => $val) {
                            unlink(FCPATH . DELIVERY_BOY_DOCUMENTS_PATH . $images_new_name_arr[$key]);
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

            if (isset($_POST['edit_delivery_boy'])) {


                if (!edit_unique($this->input->post('email', true), 'users.email.' . $this->input->post('edit_delivery_boy', true) . '') || !edit_unique($this->input->post('mobile', true), 'users.mobile.' . $this->input->post('edit_delivery_boy', true) . '')) {
                    $response["error"]   = true;
                    $response["message"] = "Email or mobile already exists !";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
                $_POST['driving_license'] = isset($images_new_name_arr) && !empty($images_new_name_arr) ? implode(',', (array)$images_new_name_arr) : implode(',', (array)$delivery_boy_data[0]['driving_license']);

                $this->Delivery_boy_model->update_delivery_boy($_POST);
            } else {

                if (!$this->form_validation->is_unique($this->input->post('mobile', true), 'users.mobile') || !$this->form_validation->is_unique($this->input->post('email', true), 'users.email')) {
                    $response["error"]   = true;
                    $response["message"] = "Email or mobile already exists !";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }

                $identity_column = $this->config->item('identity', 'ion_auth');
                $email = strtolower($this->input->post('email'));
                $mobile = $this->input->post('mobile');
                $identity = ($identity_column == 'mobile') ? $mobile : $email;
                $password = $this->input->post('password');

                $additional_data = [
                    'username' => $this->input->post('name'),
                    'address' => $this->input->post('address'),
                    'bonus_type' => $this->input->post('bonus_type'),
                    'bonus' => $bonus,
                    'type' => 'phone',
                    'driving_license' => implode(',', $images_new_name_arr),
                ];
                $this->ion_auth->register($identity, $password, $email, $additional_data, ['3']);
                update_details(['active' => 1], [$identity_column => $identity], 'users');
            }

            $this->response['error'] = false;
            $message = (isset($_POST['edit_delivery_boy'])) ? 'Delivery Boy Update Successfully' : 'Delivery Boy Added Successfully';
            $this->response['message'] = $message;
            print_r(json_encode($this->response));
        }
    }

    public function delete_delivery_boy()
    {

        if (print_msg(!has_permissions('delete', 'delivery_boy'), PERMISSION_ERROR_MSG, 'delivery_boy', false)) {
            return true;
        }
        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $id = $this->input->post('id');

            if (!is_exist(['id' => $id], 'users')) {
                $this->response['error'] = true;
                $this->response['message'] = 'Delivery Boy is not exist in your database';
            } else {
                if (update_details(['group_id' => '2'], ['user_id' => $id, 'group_id' => 3], 'users_groups') == TRUE) {
                    $this->response['error'] = false;
                    $this->response['message'] = 'Delivery Boy Deleted Successfully';
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Something Went Wrong';
                }
            }
        }
        echo json_encode($this->response);
    }

    public function add_flash_sale()
    {

        if (!$this->verify_token()) {
            return false;
        }

        if (isset($_POST['edit_flash_sale'])) {
            if (print_msg(!has_permissions('update', 'flash_sale'), PERMISSION_ERROR_MSG, 'flash_sale')) {
                return false;
            }
        } else {
            if (print_msg(!has_permissions('create', 'flash_sale'), PERMISSION_ERROR_MSG, 'flash_sale')) {
                return false;
            }
        }

        $this->form_validation->set_rules('title', ' Title ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('short_description', ' Short Description ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('discount', ' Discount ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('product_ids[]', ' Product ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('start_date', 'Start date ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('end_date', 'End date ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('image', 'Image', 'trim|required|xss_clean', array('required' => 'Image is required'));

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            if ($_POST['start_date'] > $_POST['end_date']) {
                $this->response['error'] = true;
                $this->response['message'] = "End Date must be greater than Start Date";
                print_r(
                    json_encode($this->response)
                );
                return;
            }

            $this->Flash_sale_model->add_flash_sale($_POST);
            $this->response['error'] = false;
            $message = (isset($_POST['edit_flash_sale'])) ? 'Flash Sale Updated Successfully' : 'Flash Sale Added Successfully';
            $this->response['message'] = $message;
        }
        print_r(json_encode($this->response));
    }

    public function get_flash_sale()
    {

        $id = (isset($_POST['id']) && !empty(trim($_POST['id']))) ? $this->input->post('id', true) : NULL;
        $limit = (isset($_POST['limit'])  && !empty(trim($_POST['limit']))) ? $this->input->post('limit', true) : 25;
        $offset = (isset($_POST['offset']) && !empty(trim($_POST['offset']))) ? $this->input->post('offset', true) : 0;
        $sort = (isset($_POST['sort(array)']) && !empty(trim($_POST['sort']))) ? $this->input->post('sort', true) : 'id';
        $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $this->input->post('order', true) : 'ASC';
        $search = (isset($_POST['search']) && !empty(trim($_POST['search']))) ? $this->input->post('search', true) : '';
        $slug = (isset($_POST['slug']) && !empty(trim($_POST['slug']))) ? $this->input->post('slug', true) : '';
        $p_limit = (isset($_POST['p_limit']) && !empty(trim($_POST['p_limit']))) ? $this->input->post('p_limit', true) : 10;
        $p_offset = (isset($_POST['p_offset']) && !empty(trim($_POST['p_offset']))) ? $this->input->post('p_offset', true) : 0;
        $p_order = (isset($_POST['p_order']) && !empty(trim($_POST['p_order']))) ? $this->input->post('p_order',true) : 'DESC';
        $p_sort = (isset($_POST['p_sort']) && !empty(trim($_POST['p_sort']))) ? $this->input->post('p_sort',true) : 'p.id';

        $data =  $this->Flash_sale_model->get_flash_sale_list($id, $limit, $offset, $sort, $order, $slug, $search, $p_limit, $p_offset, $p_sort, $p_order);
        echo json_encode($data);
    }

    public function delete_flash_sale()
    {

        if (!$this->verify_token()) {
            return false;
        }
        if (print_msg(!has_permissions('delete', 'flash_sale'), PERMISSION_ERROR_MSG, 'flash_sale', false)) {
            return false;
        }

        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $sale_data = fetch_details('flash_sale', ['id' => $this->input->post('id', true)], 'product_ids');
            $is_on_sale_id = (explode(',', $sale_data[0]['product_ids']));
            foreach ($is_on_sale_id as $product_id) {
                update_details(['is_on_sale' => 0], ['id' => $product_id], 'products');
                update_details(['sale_discount' => 0], ['id' => $product_id], 'products');
            }
            if (delete_details(['id' => $this->input->post('id', true)], 'flash_sale') == TRUE) {
                $this->response['error'] = false;
                $this->response['message'] = 'Deleted Successfully';
                print_r(json_encode($this->response));
            } else {
                $this->response['error'] = false;
                $this->response['message'] = 'Something Went Wrong';
                print_r(json_encode($this->response));
            }
        }
        echo json_encode($this->response);
    }

    public function add_city()
    {
        if (!$this->verify_token()) {
            return false;
        }

        $is_editing = isset($_POST['edit_city']);

        $permission_action = $is_editing ? 'update' : 'create';
        $permission_entity = 'city';

        if (print_msg(!has_permissions($permission_action, $permission_entity), PERMISSION_ERROR_MSG, $permission_entity)) {
            return false;
        }

        $this->form_validation->set_rules('city_name', 'City Name', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $city_name = $this->input->post('city_name');

            $existing_city_check = is_exist(['name' => $city_name], 'cities', $is_editing ? $this->input->post('edit_city', true) : null);

            if ($existing_city_check) {
                $this->response['error'] = true;
                $this->response['message'] = 'City Name Already Exists! Please provide a unique name.';
            } else {
                $this->Area_model->add_city($_POST);
                $this->response['error'] = false;
                $message = $is_editing ? 'City Updated Successfully' : 'City Added Successfully';
                $this->response['message'] = $message;
            }
        }

        echo json_encode($this->response);
    }

    public function delete_city()
    {
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $id = $this->input->post('id');
            if (!is_exist(['id' => $id], 'cities')) {
                $this->response['error'] = true;
                $this->response['message'] = 'City is not exist in your database';
            } else {
                if (delete_details(['id' => $id], 'cities')) {
                    $this->response['error'] = false;
                    $this->response['message'] = 'City Deleted Successfully';
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Something Went Wrong';
                }
            }
        }
        echo json_encode($this->response);
    }
    public function add_zipcode()
    {
        if (!$this->verify_token()) {
            return false;
        }
        $this->form_validation->set_rules('city', ' City ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('zipcode', ' Zipcode ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('minimum_free_delivery_order_amount', ' Minimum Free Delivery Amount ', 'trim|required|numeric|xss_clean');
        $this->form_validation->set_rules('delivery_charges', ' Delivery Charges ', 'trim|required|numeric|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            if (isset($_POST['edit_zipcode'])) {
                if (is_exist(['city_id' => $this->input->post('city', true), 'zipcode' => $this->input->post('zipcode', true)], 'zipcodes',$this->input->post('edit_zipcode', true))) {
                    $response["error"]   = true;
                    $response["message"] = "Combination Already Exist ! Provide a unique Combination";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            } else {
                if (is_exist(['city_id' => $this->input->post('city', true), 'zipcode' => $this->input->post('zipcode', true)], 'zipcodes')) {
                    $response["error"]   = true;
                    $response["message"] = "Combination Already Exist ! Provide a unique Combination";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            }
            $this->Area_model->add_zipcode($_POST);
            $this->response['error'] = false;
            $message = (isset($_POST['edit_zipcode'])) ? 'Zipcode Updated Successfully' : 'Zipcode Added Successfully';
            $this->response['message'] = $message;
            print_r(json_encode($this->response));
        }
    }

    public function delete_zipcode()
    {
        if (print_msg(!has_permissions('delete', 'zipcodes'), PERMISSION_ERROR_MSG, 'zipcodes')) {
            return false;
        }
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $id = $this->input->post('id');
            if (!is_exist(['id' => $id], 'zipcodes')) {
                $this->response['error'] = true;
                $this->response['message'] = 'Zipcode is not exist in your database';
            } else {
                delete_details(['zipcode_id' => $id], 'areas');
                if (delete_details(['id' => $id], 'zipcodes')) {
                    $this->response['error'] = false;
                    $this->response['message'] = 'Zipcode Deleted Successfully';
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Something Went Wrong';
                }
            }
        }
        echo json_encode($this->response);
    }

    public function add_offer()
    {

        if (!$this->verify_token()) {
            return false;
        }

        if (isset($_POST['edit_offer'])) {
            if (print_msg(!has_permissions('update', 'offer'), PERMISSION_ERROR_MSG, 'offer')) {
                return false;
            }
        } else {
            if (print_msg(!has_permissions('create', 'offer'), PERMISSION_ERROR_MSG, 'offer')) {
                return false;
            }
        }

        $this->form_validation->set_rules('offer_type', 'Offer Type', 'trim|required|xss_clean');
        $this->form_validation->set_rules('image', 'Offer Image', 'trim|required|xss_clean', array('required' => 'Offer image is required'));

        if (isset($_POST['offer_type']) && $_POST['offer_type'] == 'offer_url') {
            $this->form_validation->set_rules('link', 'Link', 'trim|required|xss_clean');
        }
        if (isset($_POST['offer_type']) && $_POST['offer_type'] == 'categories' ?? '') {
            $this->form_validation->set_rules('min_discount', 'Min Discount', 'trim|required|numeric|xss_clean');
            $this->form_validation->set_rules('max_discount', 'Max Discount', 'trim|required|numeric|xss_clean');
            $this->form_validation->set_rules('category_id', 'Category Id', 'trim|required|xss_clean');
        } else if (isset($_POST['offer_type']) && $_POST['offer_type'] == 'all_products' ?? '') {
            $this->form_validation->set_rules('min_discount', 'Min Discount', 'trim|required|xss_clean');
            $this->form_validation->set_rules('max_discount', 'Max Discount', 'trim|required|xss_clean');
        } else if (isset($_POST['offer_type']) && $_POST['offer_type'] == 'products' ?? '') {
            $this->form_validation->set_rules('product_id', 'Product Id', 'trim|required|xss_clean');
            $this->form_validation->set_rules('min_discount', 'Min Discount', 'trim|required|xss_clean');
            $this->form_validation->set_rules('max_discount', 'Max Discount', 'trim|required|xss_clean');
        } else if (isset($_POST['offer_type']) && $_POST['offer_type'] == 'brand' ?? '') {
            $this->form_validation->set_rules('brand_id', 'Brand Id', 'trim|required|xss_clean');
            $this->form_validation->set_rules('min_discount', 'Min Discount', 'trim|required|xss_clean');
            $this->form_validation->set_rules('max_discount', 'Max Discount', 'trim|required|xss_clean');
        }
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {
            if ((isset($_POST['main_offer_type']) && $_POST['main_offer_type'] == 'popup_offer')) {
                $this->Offer_model->add_popup_offer($_POST);
            } else {
                $this->Offer_model->add_offer($_POST);
            }
            $this->response['error'] = false;
            $message = (isset($_POST['edit_offer'])) ? 'Offer Images Update Successfully' : 'Offer Images Added Successfully';
            $this->response['message'] = $message;
            print_r(json_encode($this->response));
        }
    }
    public function delete_offer()
    {
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $id = $this->input->post('id');
            if (!is_exist(['id' => $id], 'offers')) {
                $this->response['error'] = true;
                $this->response['message'] = 'Offer is not exist in your database';
            } else {
                if (delete_details(['id' => $id], 'offers')) {
                    $this->response['error'] = false;
                    $this->response['message'] = 'Offer Deleted Successfully';
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Something Went Wrong';
                }
            }
        }
        echo json_encode($this->response);
    }

    public function get_offer_images()
    {
        $sliders = $this->db->select('os.*')
            ->order_by("row_order")
            ->get('offer_sliders os')
            ->result_array();
        $i = 0;
        foreach ($sliders as $slider) {
            $offer_ids = $slider['offer_ids'];
            $offer_ids = explode(",", $offer_ids);
            $offer_data = [];
            if (!empty($offer_ids)) {
                $offer_data = $this->db->select('o.*')->where_in('o.id', $offer_ids)
                    ->order_by("FIELD(o.id," . $slider['offer_ids'] . ")")
                    ->get('offers o')
                    ->result_array();
            }
            $sliders[$i]['offer_images']  =  $offer_data;

            for (
                $j = 0;
                $j < count($sliders[$i]['offer_images']);
                $j++
            ) {
                $sliders[$i]['offer_images'][$j]['link'] = (isset($sliders[$i]['offer_images'][$j]['link']) && !empty($sliders[$i]['offer_images'][$j]['link'])) ? $sliders[$i]['offer_images'][$j]['link'] : "";
                $sliders[$i]['offer_images'][$j]['min_discount'] = (isset($sliders[$i]['offer_images'][$j]['min_discount']) && !empty($sliders[$i]['offer_images'][$j]['min_discount'])) ? $sliders[$i]['offer_images'][$j]['min_discount'] : "";
                $sliders[$i]['offer_images'][$j]['max_discount'] = (isset($sliders[$i]['offer_images'][$j]['max_discount']) && !empty($sliders[$i]['offer_images'][$j]['max_discount'])) ? $sliders[$i]['offer_images'][$j]['max_discount'] : "";
                $sliders[$i]['offer_images'][$j]['image'] = (isset($sliders[$i]['offer_images'][$j]['image']) && !empty($sliders[$i]['offer_images'][$j]['image'])) ? base_url($sliders[$i]['offer_images'][$j]['image']) : "";
                if (strtolower($sliders[$i]['offer_images'][$j]['type']) == 'categories') {
                    $id = (!empty($sliders[$i]['offer_images'][$j]['type_id']) && isset($sliders[$i]['offer_images'][$j]['type_id'])) ? $sliders[$i]['offer_images'][$j]['type_id'] : '';
                    $cat_res = $this->category_model->get_categories($id);
                    $sliders[$i]['offer_images'][$j]['data'][0]['id']  =  $cat_res[0]['id'];
                    $sliders[$i]['offer_images'][$j]['data'][0]['name']  = ($cat_res[0]['name']);
                    $sliders[$i]['offer_images'][$j]['data'][0]['image']  =  base_url($cat_res[0]['image']);
                    $sliders[$i]['offer_images'][$j]['data'][0]['banner']  =  base_url($cat_res[0]['banner']);
                    $sliders[$i]['offer_images'][$j]['data'][0]['children']  =  (isset($cat_res[0]['children']) && !empty($cat_res[0]['children'])) ? $cat_res[0]['children'] : [];
                    
                } else if (strtolower($sliders[$i]['offer_images'][$j]['type']) == 'products') {
                    $id = (!empty($sliders[$i]['offer_images'][$j]['type_id']) && isset($sliders[$i]['offer_images'][$j]['type_id'])) ? $sliders[$i]['offer_images'][$j]['type_id'] : '';
                    $pro_res = fetch_product(NULL, NULL, $id);
                    $sliders[$i]['offer_images'][$j]['data'][0]['id']  =  $pro_res['product'][0]['id'];
                    $sliders[$i]['offer_images'][$j]['data'][0]['image']  =  $pro_res['product'][0]['image'];
                } else if (strtolower($sliders[$i]['offer_images'][$j]['type']) == 'brand') {
                    $id = (!empty($sliders[$i]['offer_images'][$j]['type_id']) && isset($sliders[$i]['offer_images'][$j]['type_id'])) ? $sliders[$i]['offer_images'][$j]['type_id'] : '';
                    $brand_res = fetch_details('brands', ["id" => $id], '*');
                    $sliders[$i]['offer_images'][$j]['data'][0]['id']  =  $brand_res[0]['id'];
                    $sliders[$i]['offer_images'][$j]['data'][0]['name']  =  $brand_res[0]['name'];
                }
            }
            $i++;
        }
        $res = fetch_details('offers', '');
        $i = 0;
        foreach ($res as $row) {
            $res[$i]['image'] = base_url($res[$i]['image']);
            if ($res[$i]['link'] == null || empty($res[$i]['link'])) {
                $res[$i]['link'] = '';
            }
            $res[$i]['min_discount'] = (isset($res[$i]['min_discount']) && !empty($res[$i]['min_discount'])) ? $res[$i]['min_discount'] : "";
            $res[$i]['max_discount'] = (isset($res[$i]['max_discount']) && !empty($res[$i]['max_discount'])) ? $res[$i]['max_discount'] : "";
            if (strtolower($res[$i]['type']) == 'categories') {
                $id = (!empty($res[$i]['type_id']) && isset($res[$i]['type_id'])) ? $res[$i]['type_id'] : '';
                $cat_res = $this->category_model->get_categories($id);
                $res[$i]['data']  =  $cat_res;
            } else if (strtolower($res[$i]['type']) == 'products') {
                $id = (!empty($res[$i]['type_id']) && isset($res[$i]['type_id'])) ? $res[$i]['type_id'] : '';
                $pro_res = fetch_product(NULL, NULL, $id);
                $res[$i]['data']  =  $pro_res['product'];
            } else if (strtolower($res[$i]['type']) == 'brand') {
                $id = (!empty($res[$i]['type_id']) && isset($res[$i]['type_id'])) ? $res[$i]['type_id'] : '';
                $brand_res = fetch_details('brands', ["id" => $id], '*');
                $res[$i]['data']  =  $brand_res[0];
            } else {
                $res[$i]['data']  =  [];
            }
            $i++;
        }
        $this->response['error'] = false;
        $this->response['message'] = 'Offer Images Retrived Successfully';
        $this->response['slider_images'] = $sliders;
        $this->response['data'] = $res;
        print_r(json_encode($this->response));
    }

    public function add_offer_slider()
    {

        if (!$this->verify_token()) {
            return false;
        }
        if (isset($_POST['edit_offer_slider'])) {
            if (print_msg(!has_permissions('update', 'offer_slider'), PERMISSION_ERROR_MSG, 'offer_slider')) {
                return false;
            }
        } else {
            if (print_msg(!has_permissions(
                'create',
                'offer_slider'
            ), PERMISSION_ERROR_MSG, 'offer_slider')) {
                return false;
            }
        }

        $this->form_validation->set_rules('style', ' Style ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('offer_ids[]', ' Offer ', 'trim|xss_clean|required');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $this->offer_slider_model->add_offer_slider($_POST);
            $this->response['error'] = false;
            $message = (isset($_POST['edit_offer_slider'])) ? 'Offer Slider Updated Successfully' : 'Offer Slider Added Successfully';
            $this->response['message'] = $message;
        }
        print_r(json_encode($this->response));
    }

    public function delete_offer_slider()
    {
        if (!$this->verify_token()) {
            return false;
        }

        if (print_msg(!has_permissions('delete', 'offer_slider'), PERMISSION_ERROR_MSG, 'offer_slider', false)) {
            return false;
        }

        $this->form_validation->set_rules('id', 'Id', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
        } else {
            $id = $this->input->post('id');
            if (!is_exist(['id' => $id], 'offer_sliders')) {
                $this->response['error'] = true;
                $this->response['message'] = 'Offer Slider is not exist in your database';
            } else {
                if (delete_details(['id' => $id], 'offer_sliders')) {
                    $this->response['error'] = false;
                    $this->response['message'] = 'Offer Slider Deleted Successfully';
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Something Went Wrong';
                }
            }
        }

        echo json_encode($this->response);
    }


    public function add_promo_code()
    {

        if (!$this->verify_token()) {
            return false;
        }

        if (isset($_POST['is_specific_user']) && $_POST['is_specific_user'] == 'on') {
            $this->form_validation->set_rules('users_id[]', 'Users', 'trim|required|xss_clean');
        }
        $this->form_validation->set_rules('promo_code', 'Promo Code ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('message', 'Message ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('start_date', 'Start date ', 'trim|required|xss_clean');
        $this->form_validation->set_rules(
            'end_date',
            'End date ',
            'trim|required|xss_clean'
        );
        $this->form_validation->set_rules('no_of_users', 'No of Users ', 'trim|numeric|xss_clean');
        $this->form_validation->set_rules('image', 'Image ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('minimum_order_amount', 'Minimum Order Amount ', 'trim|numeric|required|xss_clean');
        $this->form_validation->set_rules('discount', 'Discount', 'trim|required|numeric|xss_clean|less_than_equal_to[' . $this->input->post('minimum_order_amount') . ']');
        $this->form_validation->set_rules('max_discount_amount', 'Maximum Discount Amount', 'trim|numeric|required|xss_clean|less_than_equal_to[' . $this->input->post('minimum_order_amount') . ']');
        $this->form_validation->set_rules('discount_type', 'Discount Type ', 'trim|required|xss_clean');
        $this->form_validation->set_rules('repeat_usage', 'Repeat Usage ', 'trim|required|xss_clean');
        $this->form_validation->set_rules(
            'is_cashback',
            'Is Cashback ',
            'trim|xss_clean'
        );
        $this->form_validation->set_rules('list_promocode', 'List Promocode ', 'trim|xss_clean');
        if ($_POST['repeat_usage'] == '1') {
            $this->form_validation->set_rules('no_of_repeat_usage', 'No. of Repeat Usage ', 'trim|required|numeric|xss_clean');
        }

        $this->form_validation->set_rules('status', 'Status ', 'trim|required|xss_clean');

        if (!$this->form_validation->run()) {

            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            print_r(json_encode($this->response));
        } else {

            if (isset($_POST['edit_promo_code'])) {

                if (is_exist(['promo_code' => $this->input->post('promo_code', true)], 'promo_codes', $this->input->post('edit_promo_code', true))) {
                    $response["error"]   = true;
                    $response["message"] = "Promo Code Already Exists !";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            } else {
                if (is_exist(['promo_code' => $this->input->post('promo_code', true)], 'promo_codes')) {
                    $response["error"]   = true;
                    $response["message"] = "Promo Code Already Exists !";
                    $response["data"] = array();
                    echo json_encode($response);
                    return false;
                }
            }

            $this->Promo_code_model->add_promo_code_details($_POST);
            $this->response['error'] = false;
            $message = (isset($_POST['edit_promo_code'])) ? 'Promo code Updated Successfully' : 'Promo code Added Successfully';
            $this->response['message'] = $message;
            print_r(json_encode($this->response));
        }
    }

    //verify_otp
    public function verify_otp()
    {
        /* 
        otp: 123456
        phone number: 9876543210
        */

        $this->form_validation->set_rules('mobile', 'Mobile', 'trim|required|xss_clean|max_length[16]|numeric');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $mobile = $this->input->post('mobile');
            $auth_settings = get_settings('authentication_settings', true);
            if ($auth_settings['authentication_method'] == "sms") {
                $otps = fetch_details('otps', ['mobile' => $mobile]);
                $time = $otps[0]['created_at'];
                $time_expire = checkOTPExpiration($time);
                if ($time_expire['error'] == 1) {
                    $response['error'] = true;
                    $response['message'] = $time_expire['message'];
                    echo json_encode($response);
                    return false;
                }
                if (($otps[0]['otp'] != $_POST['otp'])) {
                    $response['error'] = true;
                    $response['message'] = "OTP not valid , check again ";
                    echo json_encode($response);
                    return false;
                } else {
                    update_details(['varified' => 1], ['mobile' => $mobile], 'otps');
                }
            }
            $this->response['error'] = false;
            $this->response['message'] = 'Otp Verified Successfully';
            $this->response['data'] = array();
        }
        print_r(json_encode($this->response));
    }

    //resend_otp
    public function resend_otp()
    {
        /*
        mobile:9876543210
        */

        $this->form_validation->set_rules('mobile', 'Mobile', 'trim|required|xss_clean|max_length[16]|numeric');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $mobile = $this->input->post('mobile');
            $auth_settings = get_settings('authentication_settings', true);
            if ($auth_settings['authentication_method'] == "sms") {
                $otps = fetch_details('otps', ['mobile' => $mobile]);

                $query = $this->db->select(' * ')->where('id', $otps[0]['id'])->get('otps')->result_array();
                $otp = random_int(100000, 999999);
                $data = set_user_otp($mobile, $otp);
                $this->response['error'] = false;
                $this->response['message'] = 'Ready to sent OTP request from sms!';
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return;
            }
        }
    }

    // get_invoice_html    
    // order_id:214
    public function get_invoice_html()
    {
        if (!$this->verify_token()) {
            return false;
        }

        $this->form_validation->set_rules('order_id', 'Order id', 'trim|required|xss_clean');


        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        } else {

            $this->data['main_page'] = VIEW . 'api-order-invoice';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Invoice Management |' . $settings['app_name'];
            $this->data['meta_description'] = $settings['app_name'] . ' | Invoice Management';
            if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
                $res = $this->order_model->get_order_details(['o.id' => $_POST['order_id']]);
                if (!empty($res)) {
                    $items = [];
                    $promo_code = [];
                    if (!empty($res[0]['promo_code'])) {
                        $promo_code = fetch_details('promo_codes', ['promo_code' => trim($res[0]['promo_code'])]);
                    }
                    foreach ($res as $row) {
                        $row = output_escaping($row);
                        $temp['product_id'] = $row['product_id'];
                        $temp['seller_id'] = $row['seller_id'];
                        $temp['product_variant_id'] = $row['product_variant_id'];
                        $temp['pname'] = $row['pname'];
                        $temp['quantity'] = $row['quantity'];
                        $temp['discounted_price'] = $row['discounted_price'];
                        $temp['tax_percent'] = $row['tax_percent'];
                        $temp['tax_amount'] = $row['tax_amount'];
                        $temp['price'] = $row['price'];
                        $temp['product_special_price'] = $row['product_special_price'];
                        $temp['delivery_boy'] = $row['delivery_boy'];
                        $temp['mobile_number'] = $row['mobile_number'];
                        $temp['active_status'] = $row['oi_active_status'];
                        $temp['hsn_code'] = $row['hsn_code'];
                        array_push($items, $temp);
                    }
                    $this->data['order_detls'] = $res;
                    $this->data['items'] = $items;
                    $this->data['promo_code'] = $promo_code;
                    $this->data['settings'] = get_settings('system_settings', true);
                    $response['error'] = false;
                    $response['message'] = 'Invoice Generated Successfully';
                    $response['data'] = $this->load->view('admin/invoice-template', $this->data, TRUE);
                } else {
                    $response['error'] = false;
                    $response['message'] = 'No Order Details Found !';
                    $response['data'] = [];
                }
            } else {
                $response['error'] = false;
                $response['message'] = 'No Order Details Found !';
                $response['data'] = [];
            }
            print_r(json_encode($response));
            return false;
        }
    }
}
