<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Cart extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library(['cart', 'razorpay', 'stripe', 'paystack', 'flutterwave', 'midtrans', 'my_fatoorah', 'instamojo', 'phonepe']);
        $this->paystack->__construct('test');
        $this->load->model(['cart_model', 'address_model', 'order_model', 'Order_model', 'transaction_model']);
        $this->data['is_logged_in'] = ($this->ion_auth->logged_in()) ? 1 : 0;
        $this->data['user'] = ($this->ion_auth->logged_in()) ? $this->ion_auth->user()->row() : array();
        $this->response['csrfName'] = $this->security->get_csrf_token_name();
        $this->response['csrfHash'] = $this->security->get_csrf_hash();
        $this->data['settings'] = get_settings('system_settings', true);
        $this->data['web_settings'] = get_settings('web_settings', true);
    }

    public function index()
    {
        if ((isset($this->data['settings']['is_web_under_maintenance']) && $this->data['settings']['is_web_under_maintenance'] == 1)) {
            redirect(base_url("maintenance"));
        }
        if ($this->data['is_logged_in']) {
            $user_id = $this->session->userdata("user_id");
            $this->data['related_products'] = fetch_product($user_id, NULL, NULL, NULL, 12);
            $this->data['main_page'] = 'cart';
            $this->data['title'] = 'Product Cart | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Product Cart, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Product Cart | ' . $this->data['web_settings']['meta_description'];
            $this->data['cart'] = get_cart_total($this->data['user']->id);
            $this->data['save_for_later'] = get_cart_total($this->data['user']->id, false, '1');
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url());
        }
    }

    public function manage()
    {
        if ($this->data['is_logged_in']) {
            $this->form_validation->set_rules('product_variant_id', 'Product Variant', 'trim|required|xss_clean');
            $this->form_validation->set_rules('is_saved_for_later', 'Saved For Later', 'trim|xss_clean');
            $_POST['qty'] = (isset($_POST['qty']) && $_POST['qty'] != '') ? $_POST['qty'] : 1;
            $_POST['is_buy_now'] = (isset($_POST['is_buy_now']) && $_POST['is_buy_now'] != '') ? $_POST['is_buy_now'] : 0;
            $this->form_validation->set_rules('qty', 'Quantity', 'trim|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                $this->response['data'] = array();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            }

            if ((isset($_POST['update_cart']) && $_POST['update_cart'] == 1)) {
                $check_is_exist = $this->db->select('id')->where(['product_variant_id' => $_POST['product_variant_id']])->get('cart')->result_array();
                if ($check_is_exist == null) {
                    $this->response['error'] = true;
                    $this->response['message'] = "Already Exist";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    print_r(json_encode($this->response));
                    return false;
                }
            }

            $data = array(
                'product_variant_id' => $this->input->post('product_variant_id', true),
                'qty' => $this->input->post('qty', true),
                'is_saved_for_later' => $this->input->post('is_saved_for_later', true),
                'is_buy_now' => $this->input->post('is_buy_now', true),
                'user_id' => $this->data['user']->id,
            );
            $product_variant_id = explode(',', $_POST['product_variant_id']);
            $_POST['user_id'] = $this->data['user']->id;
            $settings = get_settings('system_settings', true);
            //check for digital or phisical product in cart
            if (!is_single_product_type($product_variant_id[0], $_POST['user_id'])) {

                $this->response['error'] = true;
                $this->response['message'] = 'you can only add either digital product or physical product to cart';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }
            $_POST['user_id'] = $this->data['user']->id;
            $settings = get_settings('system_settings', true);
            $cart_count = get_cart_count($_POST['user_id']);
            $is_variant_available_in_cart = is_variant_available_in_cart($_POST['product_variant_id'], $_POST['user_id']);
            if (!$is_variant_available_in_cart) {
                if ($cart_count[0]['total'] >= $settings['max_items_cart']) {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Maximum ' . $settings['max_items_cart'] . ' Item(s) Can Be Added Only!';
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return;
                }
            }
            $saved_for_later = (isset($_POST['is_saved_for_later']) && $_POST['is_saved_for_later'] != "") ? $this->input->post('is_saved_for_later', true) : 0;
            $check_status = ($saved_for_later == 1) ? false : true;

            $res = $this->cart_model->add_to_cart($data, $check_status);
            if (isset($res['error']) && $res['error'] == true) {
                $this->response['error'] = true;
                $this->response['message'] = $res['message'];
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            } else {
                if ($_POST['qty'] == 0) {
                    $res = get_cart_total($this->data['user']->id, false);
                } else {
                    $res = get_cart_total($this->data['user']->id, $_POST['product_variant_id']);
                }
                $this->response['error'] = false;
                $this->response['message'] = 'Item added to Cart.';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = [
                    'total_quantity' => ($_POST['qty'] == 0) ? '0' : strval($_POST['qty']),
                    'sub_total' => strval($res['sub_total']),
                    'total_items' => (isset($res[0]['total_items'])) ? strval($res[0]['total_items']) : "0",
                    'tax_percentage' => (isset($res['tax_percentage'])) ? strval($res['tax_percentage']) : "0",
                    'tax_amount' => (isset($res['tax_amount'])) ? strval($res['tax_amount']) : "0",
                    'cart_count' => (isset($res[0]['cart_count'])) ? strval($res[0]['cart_count']) : "0",
                    'max_items_cart' => $this->data['settings']['max_items_cart'],
                    'overall_amount' => $res['overall_amount'],
                    'items' => $this->cart_model->get_user_cart($this->data['user']->id),
                ];

                print_r(json_encode($this->response));
                return false;
            }
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Please Login first to use Cart.';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = $this->data;
            echo json_encode($this->response);
            return false;
        }
    }
    public function cart_sync()
    {

        if (!isset($_POST['data']) || empty($_POST['data'])) {
            $this->response['error'] = true;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return false;
        }
        $post_data = json_decode($_POST['data'], true);

        if (isset($post_data) && !empty($post_data)) {
            $pv_ids = [];
            foreach ($post_data as $data) {
                if (!isset($data['product_variant_id']) || empty($data['product_variant_id']) || !is_numeric($data['product_variant_id'])) {
                    $this->response['error'] = true;
                    $this->response['message'] = "The variant ID field is required";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                }
                array_push($pv_ids, $data['product_variant_id']);
                if (!isset($data['qty']) || empty($data['qty']) || !is_numeric($data['qty'])) {
                    $this->response['error'] = true;
                    $this->response['message'] = "Please enter valid quantity for " . $data['title'];
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                }
            }

            if (!is_single_product_type(implode(",", $pv_ids), $this->data['user']->id)) {
                $this->response['error'] = true;
                $this->response['message'] = 'you can only add either digital product or physical product to cart';
                $this->response['data'] = array();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            }
        } else {
            $this->response['error'] = true;
            $this->response['data'] = array();
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($this->response));
            return false;
        }
        $user_id = $this->data['user']->id;
        $product_variant_ids = array_column($post_data, "product_variant_id");
        $quantity = array_column($post_data, "qty");
        $place_order_data = array();
        $place_order_data['product_variant_id'] = implode(",", $product_variant_ids);
        $place_order_data['qty'] = implode(",", $quantity);
        $place_order_data['user_id'] =  $user_id;

        $settings = get_settings('system_settings', true);
        $cart_count = get_cart_count($user_id);
        foreach ($product_variant_ids as $variant_id) {
            $is_variant_available_in_cart = is_variant_available_in_cart($variant_id, $user_id);
            if (!$is_variant_available_in_cart) {
                if ($cart_count[0]['total'] >= $settings['max_items_cart']) {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Maximum ' . $settings['max_items_cart'] . ' Item(s) Can Be Added Only!';
                    $this->response['data'] = array();
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    print_r(json_encode($this->response));
                    return;
                }
            }
        }
        $saved_for_later = (isset($_POST['is_saved_for_later']) && $_POST['is_saved_for_later'] != "") ? $this->input->post('is_saved_for_later', true) : 0;
        $check_status = ($saved_for_later == 1) ? false : true;
        $res = $this->cart_model->add_to_cart($place_order_data, $check_status);
        if (isset($res['error']) && $res['error'] == true) {
            $this->response['error'] = true;
            $this->response['message'] = $res['message'];
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($this->response));
            return false;
        } else {
            if ($_POST['qty'] == 0) {
                $res = get_cart_total($this->data['user']->id, false);
            } else {
                $res = get_cart_total($this->data['user']->id, $_POST['product_variant_id']);
            }
            $this->response['error'] = false;
            $this->response['message'] = 'Item added to Cart.';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = [
                'total_quantity' => ($_POST['qty'] == 0) ? '0' : strval($_POST['qty']),
                'sub_total' => strval($res['sub_total']),
                'total_items' => (isset($res[0]['total_items'])) ? strval($res[0]['total_items']) : "0",
                'tax_percentage' => (isset($res['tax_percentage'])) ? strval($res['tax_percentage']) : "0",
                'tax_amount' => (isset($res['tax_amount'])) ? strval($res['tax_amount']) : "0",
                'cart_count' => (isset($res[0]['cart_count'])) ? strval($res[0]['cart_count']) : "0",
                'max_items_cart' => $this->data['settings']['max_items_cart'],
                'overall_amount' => $res['overall_amount'],
                'items' => $this->cart_model->get_user_cart($this->data['user']->id),
            ];
            print_r(json_encode($this->response));
            return false;
        }
    }


    // remove_from_cart
    public function remove()
    {
        $this->form_validation->set_rules('product_variant_id', 'Product Variant', 'trim|numeric|xss_clean|required');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = validation_errors();
            $this->response['data'] = array();
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            print_r(json_encode($this->response));
            return false;
        } else {
            //Fetching cart items to check wheather cart is empty or not
            $cart_total_response = get_cart_total($this->data['user']->id);
            if (isset($_POST['is_save_for_later']) && empty($_POST['is_save_for_later'])) {
                if (!isset($cart_total_response[0]['total_items'])) {
                    $this->response['error'] = true;
                    $this->response['message'] = 'Cart Is Already Empty !';
                    $this->response['data'] = array();
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    print_r(json_encode($this->response));
                    return false;
                }
            }
            $data = array(
                'user_id' => $this->data['user']->id,
                'product_variant_id' => $this->input->post('product_variant_id', true),
            );
            if ($this->cart_model->remove_from_cart($data)) {
                $this->response['error'] = false;
                $this->response['message'] = 'Removed From Cart !';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return false;
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Cannot remove this Item from cart.';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                echo json_encode($this->response);
                return false;
            }
        }
    }
    public function clear()
    {
        if ($this->data['is_logged_in']) {
            $cart_total_response = get_cart_total($this->data['user']->id);
            if (!isset($cart_total_response[0]['total_items'])) {
                $this->response['error'] = true;
                $this->response['message'] = 'Cart Is Already Empty !';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return;
            }
            $data = array(
                'user_id' => $this->data['user']->id,
            );
            if ($this->cart_model->remove_from_cart($data)) {
                $cart_total_response = get_cart_total($data['user_id']);
                $this->response['error'] = false;
                $this->response['message'] = 'Product Clear From Cart !';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                if (!empty($cart_total_response) && isset($cart_total_response)) {
                    $this->response['data'] = [
                        'total_quantity' => strval($cart_total_response['quantity']),
                        'sub_total' => strval($cart_total_response['sub_total']),
                        'total_items' => (isset($cart_total_response[0]['total_items'])) ? strval($cart_total_response[0]['total_items']) : "0",
                        'max_items_cart' => $this->data['settings']['max_items_cart']
                    ];
                } else {
                    $this->response['data'] = [];
                }
                print_r(json_encode($this->response));
                return false;
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Cannot remove this Item from cart.';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                echo json_encode($this->response);
                return false;
            }
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Please Login first to use Cart.';
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            echo json_encode($this->response);
            return false;
        }
    }

    public function get_user_cart()
    {
        if ($this->data['is_logged_in']) {
            $cart_user_data = $this->cart_model->get_user_cart($this->data['user']->id);
            $cart_total_response = get_cart_total($this->data['user']->id);
            $tmp_cart_user_data = $cart_user_data;

            if (!empty($tmp_cart_user_data)) {
                for ($i = 0; $i < count($tmp_cart_user_data); $i++) {

                    $product_data = fetch_details('product_variants', ['id' => $tmp_cart_user_data[$i]['product_variant_id']], 'product_id,availability');
                    $pro_details = fetch_product($this->data['user']->id, null, $product_data[0]['product_id']);
                    if (!empty($pro_details['product'])) {

                        if (trim($pro_details['product'][0]['availability']) == 0 && $pro_details['product'][0]['availability'] != null) {
                            unset($cart_user_data[$i]);
                            continue;
                        }
                        if (!empty($pro_details['product'])) {
                            $cart_user_data[$i]['product_details'] = $pro_details['product'];
                        } else {
                            unset($cart_user_data[$i]);
                            continue;
                        }
                    } else {
                        unset($cart_user_data[$i]);
                        continue;
                    }
                }
            }
            if (empty($cart_user_data)) {
                $this->response['error'] = true;
                $this->response['message'] = 'Cart Is Empty !';
                $this->response['data'] = array();
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return;
            }
            $this->response['error'] = false;
            $this->response['message'] = 'Product Retrived From Cart...!';
            $this->response['total_quantity'] = $cart_total_response['quantity'];
            $this->response['sub_total'] = $cart_total_response['sub_total'];
            $this->response['delivery_charge'] = $this->data['settings']['delivery_charge'];
            $this->response['tax_percentage'] = (isset($cart_total_response['tax_percentage'])) ? $cart_total_response['tax_percentage'] : "0";
            $this->response['tax_amount'] = (isset($cart_total_response['tax_amount'])) ? $cart_total_response['tax_amount'] : "0";
            $this->response['total_arr'] =  $cart_total_response['total_arr'];
            $this->response['variant_id'] =  $cart_total_response['variant_id'];
            $this->response['data'] = array_values($cart_user_data);
            print_r($this->response);
            return;
        } else {
            $this->response['error'] = true;
            $this->response['message'] = 'Please Login first to use Cart.';
            $this->response['data'] = $this->data;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            echo json_encode($this->response);
            return false;
        }
    }
    public function checkout()
    {
        if ($this->data['is_logged_in']) {
            $cart = $this->cart_model->get_user_cart($this->data['user']->id);
            if (empty($cart)) {
                redirect(base_url());
            }
            $this->data['time_slot_config'] = get_settings('time_slot_config', true);
            $payment_methods = get_settings('payment_method', true);
            $this->data['main_page'] = 'checkout';
            $this->data['title'] = 'Checkout | ' . $this->data['web_settings']['site_title'];
            $this->data['keywords'] = 'Checkout, ' . $this->data['web_settings']['meta_keywords'];
            $this->data['description'] = 'Checkout | ' . $this->data['web_settings']['meta_description'];
            $cart_total_data = get_cart_total($this->data['user']->id);
            $this->data['cart'] = $cart_total_data;
            $this->data['payment_methods'] = get_settings('payment_method', true);
            $this->data['time_slots'] = fetch_details('time_slots', 'status=1',  '*');
            $this->data['wallet_balance'] = fetch_details('users', 'id=' . $this->data['user']->id, 'balance,mobile');

            $this->data['default_address'] = $this->address_model->get_address($this->data['user']->id, NULL, NULL, TRUE);
            $this->data['payment_methods'] = $payment_methods;
            $settings = get_settings('system_settings', true);
            $this->data['support_email'] = (isset($settings['support_email']) && !empty($settings['support_email'])) ? $settings['support_email'] : 'abc@gmail.com';
            $currency = (isset($settings['currency']) && !empty($settings['currency'])) ? $settings['currency'] : '';
            $total = $this->data['cart']['total_arr'];
            if ($total < $settings['minimum_cart_amt']) {
                if (isset($settings['minimum_cart_amt']) && !empty($settings['minimum_cart_amt'])) {
                    $this->session->set_flashdata('message', 'Minimum total should be ' . $currency . ' ' . $settings['minimum_cart_amt']);
                    $this->session->set_flashdata('message_type', 'error');
                    redirect(base_url('cart'), 'refresh');
                }
            }

            foreach ($cart_total_data as $row) {
                if (isset($row['availability'])  && empty($row['availability']) && $row['availability'] != "") {
                    $this->session->set_flashdata('message', 'Some of the product(s) are OUt of Stock. Please remove it from cart or save to later.');
                    $this->session->set_flashdata('message_type', 'error');
                    redirect(base_url('cart'), 'refresh');
                }
            }
            $this->data['currency'] = $currency;
            $this->load->view('front-end/' . THEME . '/template', $this->data);
        } else {
            redirect(base_url());
        }
    }

    public function place_order()
    {

        if ($this->data['is_logged_in']) {
            /*
            mobile:696919362
            product_variant_id: 1,2,3
            quantity: 3,3,1
            latitude:40.1451
            longitude:-45.4545
            promo_code:NEW20 {optional}
            payment_method: Paypal / Payumoney / COD / PAYTM
            address_id:17
            delivery_date:10/12/2012
            delivery_time:Today - Evening (4:00pm to 7:00pm)
            is_wallet_used:1 {By default 0}
            wallet_balance_used:1
            active_status:awaiting {optional}
      
        */
            $settings = get_settings('system_settings', true);
            $shipping_settings = get_settings('shipping_method', true);
            $pickup  = (isset($settings['local_pickup'])) ? $settings['local_pickup'] : 0;
            if ($pickup == 0 && $_POST['local_pickup'] == 0 && $_POST['product_type'] != 'digital_product') {
                if (!isset($_POST['address_id']) || empty($_POST['address_id'])) {
                    $this->response['error'] = true;
                    $this->response['message'] = "Please choose address.";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return false;
                }
            }
            $this->form_validation->set_rules('mobile', 'Mobile', 'trim|required|numeric|xss_clean');
            $this->form_validation->set_rules('product_variant_id', 'Product Variant Id', 'trim|required|xss_clean');
            $this->form_validation->set_rules('quantity', 'Quantities', 'trim|required|xss_clean');
            $this->form_validation->set_rules('promo_code', 'Promo Code', 'trim|xss_clean');
            $this->form_validation->set_rules('order_note', 'Special Note', 'trim|xss_clean');
            $this->form_validation->set_rules('local_pickup', 'Local Pickup', 'trim|required|xss_clean');


            /*
        ------------------------------
        If Wallet Balance Is Used
        ------------------------------
        */

            $this->form_validation->set_rules('latitude', 'Latitude', 'trim|numeric|xss_clean');
            $this->form_validation->set_rules('longitude', 'Longitude', 'trim|numeric|xss_clean');
            $time_slot_config = get_settings('time_slot_config', true);
            if (isset($time_slot_config['is_time_slots_enabled']) && ($time_slot_config['is_time_slots_enabled'] == 1 || $time_slot_config['is_time_slots_enabled'] == '1') && $_POST['product_type'] != 'digital_product' && $shipping_settings['local_shipping_method'] == 1) {
                $this->form_validation->set_rules('delivery_date', 'Delivery Date', 'trim|required|xss_clean');
                $this->form_validation->set_rules('delivery_time', 'Delivery time', 'trim|required|xss_clean');
            }
        }

        if (isset($_POST['product_type']) && $_POST['product_type'] == 'digital_product' && $_POST['download_allowed'] == 0) {
            $this->form_validation->set_rules('email', 'Email ID', 'trim|required|valid_email|xss_clean', array('required' => 'Please Enter Email ID'));
        }

        if ($_POST['payment_method'] == "Razorpay") {
            $this->form_validation->set_rules('razorpay_order_id', 'Razorpay Order ID', 'trim|required|xss_clean');
            $this->form_validation->set_rules('razorpay_payment_id', 'Razorpay Payment ID', 'trim|required|xss_clean');
            $this->form_validation->set_rules('razorpay_signature', 'Razorpay Signature', 'trim|required|xss_clean');
        } else if ($_POST['payment_method'] == "Paystack") {
            $this->form_validation->set_rules('paystack_reference', 'Paystack Reference', 'trim|required|xss_clean');
        } else if ($_POST['payment_method'] == "Flutterwave") {
            $this->form_validation->set_rules('flutterwave_transaction_id', 'Flutterwave Transaction ID', 'trim|required|xss_clean');
            $this->form_validation->set_rules('flutterwave_transaction_ref', 'Flutterwave Transaction Refrence', 'trim|required|xss_clean');
        } else if ($_POST['payment_method'] == "Paytm") {
            $this->form_validation->set_rules('paytm_transaction_token', 'Paytm transaction token', 'trim|required|xss_clean');
            $this->form_validation->set_rules('paytm_order_id', 'Paytm order ID', 'trim|required|xss_clean');
        } else if ($_POST['payment_method'] == "instamojo") {
            $this->form_validation->set_rules('instamojo_payment_id', 'Instamojo Payment ID', 'trim|required|xss_clean');
        }


        $_POST['user_id'] = $this->data['user']->id;
        $_POST['customer_email'] = $this->data['user']->email;
        $_POST['is_wallet_used'] = 0;
        $data = array();
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = array();
            print_r(json_encode($this->response));
            return;
        } else {


            $_POST['order_note'] = (isset($_POST['order_note']) && !empty($_POST['order_note'])) ? $this->input->post("order_note", true) : NULL;
            //upload attachments
            $settings = get_settings('system_settings', true);
            $upload_attachments = isset($settings['allow_order_attachments']) ? $settings['allow_order_attachments'] : '';
            $upload_limit = isset($settings['upload_limit']) ? $settings['upload_limit'] : '';
            $limit = (isset($_FILES['documents']['name'])) ? count($_FILES['documents']['name']) : 0;

            $images_new_name_arr = $attachments = array();

            /* checking if any of the product requires the media file or not */
            $product_variant_ids = $this->input->post('product_variant_id', true);
            $product_ids = fetch_details('product_variants', '',  'product_id', '', '', '', '', 'id', $product_variant_ids);
            $product_ids = (!empty($product_ids)) ? array_column($product_ids, 'product_id') : [];
            $product_ids = (!empty($product_ids)) ? implode(",", $product_ids) : "";
            $product_attachments = fetch_details('products', '',  'is_attachment_required', '', '', '', '', 'id', $product_ids);

            $is_attachment_required = false;
            if (!empty($product_attachments)) {
                foreach ($product_attachments as $attachment) {
                    if ($attachment['is_attachment_required'] == 1) {
                        $is_attachment_required = true;
                        break;
                    }
                }
            }
            /* ends checking if any of the product requires the media file or not */

            if (empty($_FILES['documents']['name'][0]) && $is_attachment_required) {
                $this->response['error'] = true;
                $this->response['message'] = "Some of your products in cart require at least one media file to be uploaded!";
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return;
            }
            if ($limit >= 0) {
                if (!file_exists(FCPATH . ORDER_ATTACHMENTS)) {
                    mkdir(FCPATH . ORDER_ATTACHMENTS, 0777);
                }
                $temp_array = array();
                $files = $_FILES;
                $images_info_error = "";
                $allowed_media_types = 'jpg|png|jpeg';
                $config = [
                    'upload_path' =>  FCPATH . ORDER_ATTACHMENTS,
                    'allowed_types' => $allowed_media_types,
                    'max_size' => 8000,
                ];
                if (!empty($_FILES['documents']['name'][0]) && isset($_FILES['documents']['name'])) {
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
                                $images_info_error = 'documents :' . $images_info_error . ' ' . $other_img->display_errors();
                            } else {
                                $temp_array = $other_img->data();
                                resize_review_images($temp_array, FCPATH . ORDER_ATTACHMENTS);
                                $images_new_name_arr[$i] = ORDER_ATTACHMENTS . $temp_array['file_name'];
                            }
                        } else {
                            $_FILES['temp_image']['name'] = $files['documents']['name'][$i];
                            $_FILES['temp_image']['type'] = $files['documents']['type'][$i];
                            $_FILES['temp_image']['tmp_name'] = $files['documents']['tmp_name'][$i];
                            $_FILES['temp_image']['error'] = $files['documents']['error'][$i];
                            $_FILES['temp_image']['size'] = $files['documents']['size'][$i];
                            if (!$other_img->do_upload('temp_image')) {
                                $images_info_error = $other_img->display_errors();
                            }
                        }
                    }
                    //Deleting Uploaded attachments if any overall error occured
                    if ($images_info_error != NULL || !$this->form_validation->run()) {
                        if (isset($images_new_name_arr) && !empty($images_new_name_arr || !$this->form_validation->run())) {
                            foreach ($images_new_name_arr as $key => $val) {
                                unlink(FCPATH . ORDER_ATTACHMENTS . $images_new_name_arr[$key]);
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
            } else {
                $this->response['error'] = true;
                $this->response['message'] = "You Can Not Upload More Then one Images !";
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                print_r(json_encode($this->response));
                return;
            }

            $attachments = $images_new_name_arr;
            $mapped_attachments = [];

            // if ($upload_attachments == 0 && isset($_FILES['documents']['name'][0])) {
            //     $this->response['error'] = true;
            //     $this->response['message'] = "Please Turn On Upload Media Setting";
            //     $this->response['csrfName'] = $this->security->get_csrf_token_name();
            //     $this->response['csrfHash'] = $this->security->get_csrf_hash();
            //     print_r(json_encode($this->response));
            //     return;
            // }

            // if (isset($upload_attachments) && $upload_attachments == 1) {
            //     /* checking if any of the product requires the media file or not */

            //     $product_variant_ids = $this->input->post('product_variant_id', true);
            //     $product_ids = fetch_details('product_variants', '',  'product_id', '', '', '', '', 'id', $product_variant_ids);
            //     $product_ids = (!empty($product_ids)) ? array_column($product_ids, 'product_id') : [];
            //     $product_ids = (!empty($product_ids)) ? implode(",", $product_ids) : "";
            //     $product_attachments = fetch_details('products', '',  'is_attachment_required', '', '', '', '', 'id', $product_ids);

            //     $is_attachment_required = false;
            //     if (!empty($product_attachments))
            //         foreach ($product_attachments as $attachment) {
            //             if ($attachment['is_attachment_required'] == 1) {
            //                 $is_attachment_required = true;
            //                 break;
            //             }
            //         }
            //     /* ends checking if any of the product requires the media file or not */

            //     if (empty($_FILES['documents']['name'][0]) && $is_attachment_required) {
            //         $this->response['error'] = true;
            //         $this->response['message'] = "Some of your products in cart require at least one media file to be uploaded!";
            //         $this->response['csrfName'] = $this->security->get_csrf_token_name();
            //         $this->response['csrfHash'] = $this->security->get_csrf_hash();
            //         print_r(json_encode($this->response));
            //         return;
            //     }
            //     if ($limit <= $upload_limit) {
            //         if (!file_exists(FCPATH . ORDER_ATTACHMENTS)) {
            //             mkdir(FCPATH . ORDER_ATTACHMENTS, 0777);
            //         }
            //         $temp_array = array();
            //         $files = $_FILES;
            //         $images_info_error = "";
            //         $allowed_media_types = 'jpg|png|jpeg|pdf|doc|docs|txt';
            //         $config = [
            //             'upload_path' =>  FCPATH . ORDER_ATTACHMENTS,
            //             'allowed_types' => $allowed_media_types,
            //             'max_size' => 8000,
            //         ];
            //         if (!empty($_FILES['documents']['name'][0]) && isset($_FILES['documents']['name'])) {
            //             $other_image_cnt = count($_FILES['documents']['name']);
            //             $other_img = $this->upload;
            //             $other_img->initialize($config);

            //             for ($i = 0; $i < $other_image_cnt; $i++) {

            //                 if (!empty($_FILES['documents']['name'][$i])) {

            //                     $_FILES['temp_image']['name'] = $files['documents']['name'][$i];
            //                     $_FILES['temp_image']['type'] = $files['documents']['type'][$i];
            //                     $_FILES['temp_image']['tmp_name'] = $files['documents']['tmp_name'][$i];
            //                     $_FILES['temp_image']['error'] = $files['documents']['error'][$i];
            //                     $_FILES['temp_image']['size'] = $files['documents']['size'][$i];
            //                     if (!$other_img->do_upload('temp_image')) {
            //                         $images_info_error = 'documents :' . $images_info_error . ' ' . $other_img->display_errors();
            //                     } else {
            //                         $temp_array = $other_img->data();
            //                         resize_review_images($temp_array, FCPATH . ORDER_ATTACHMENTS);
            //                         $images_new_name_arr[$i] = ORDER_ATTACHMENTS . $temp_array['file_name'];
            //                     }
            //                 } else {
            //                     $_FILES['temp_image']['name'] = $files['documents']['name'][$i];
            //                     $_FILES['temp_image']['type'] = $files['documents']['type'][$i];
            //                     $_FILES['temp_image']['tmp_name'] = $files['documents']['tmp_name'][$i];
            //                     $_FILES['temp_image']['error'] = $files['documents']['error'][$i];
            //                     $_FILES['temp_image']['size'] = $files['documents']['size'][$i];
            //                     if (!$other_img->do_upload('temp_image')) {
            //                         $images_info_error = $other_img->display_errors();
            //                     }
            //                 }
            //             }
            //             //Deleting Uploaded attachments if any overall error occured
            //             if ($images_info_error != NULL || !$this->form_validation->run()) {
            //                 if (isset($images_new_name_arr) && !empty($images_new_name_arr || !$this->form_validation->run())) {
            //                     foreach ($images_new_name_arr as $key => $val) {
            //                         unlink(FCPATH . ORDER_ATTACHMENTS . $images_new_name_arr[$key]);
            //                     }
            //                 }
            //             }
            //         }
            //         if ($images_info_error != NULL) {
            //             $this->response['error'] = true;
            //             $this->response['message'] =  $images_info_error;
            //             $this->response['csrfName'] = $this->security->get_csrf_token_name();
            //             $this->response['csrfHash'] = $this->security->get_csrf_hash();
            //             print_r(json_encode($this->response));
            //             return false;
            //         }
            //     } else {
            //         $this->response['error'] = true;
            //         $this->response['message'] = "You Can Not Upload More Then $upload_limit Images !";
            //         $this->response['csrfName'] = $this->security->get_csrf_token_name();
            //         $this->response['csrfHash'] = $this->security->get_csrf_hash();
            //         print_r(json_encode($this->response));
            //         return;
            //     }

            //     $attachments = $images_new_name_arr;
            // }


            if (isset($_POST['product_type']) && $_POST['product_type'] != 'digital_product'  && $_POST['local_pickup'] == 0) {

                $area_id = fetch_details('addresses', ['id' => $_POST['address_id']], ['area_id', 'area', 'pincode', 'city']);

                $zipcode = $area_id[0]['pincode'];
                $zipcode_id = fetch_details('zipcodes', ['zipcode' => $zipcode], 'id')[0];

                $city = $area_id[0]['city'];
                $city_id = fetch_details('cities', ['name' => $city], 'id');
                $city_id = $city_id[0]['id'];

                $system_settings = get_settings('system_settings', true);
                if ((isset($system_settings['pincode_wise_deliverability']) && $system_settings['pincode_wise_deliverability'] == 1) || (isset($shipping_settings['local_shipping_method']) && isset($shipping_settings['shiprocket_shipping_method']) && $shipping_settings['local_shipping_method'] == 1 && $shipping_settings['shiprocket_shipping_method'] == 1)) {
                    $product_delivarable = check_cart_products_delivarable($_POST['user_id'], $area_id[0]['area_id'], $zipcode, $zipcode_id['id']);
                }
                if (isset($system_settings['city_wise_deliverability']) && $system_settings['city_wise_deliverability'] == 1 && $shipping_settings['shiprocket_shipping_method'] != 1) {
                    $product_delivarable = check_cart_products_delivarable($_POST['user_id'], $area_id[0]['area_id'], '', '', $city, $city_id);
                }
                if (!empty($product_delivarable)) {
                    $product_not_delivarable = array_filter($product_delivarable, function ($var) {
                        return ($var['is_deliverable'] == false && $var['product_id'] != null);
                    });
                    $product_not_delivarable = array_values($product_not_delivarable);
                    $product_delivarable = array_filter($product_delivarable, function ($var) {
                        return ($var['product_id'] != null);
                    });
                    if (!empty($product_not_delivarable)) {
                        $this->response['error'] = true;
                        $this->response['message'] = "Some of the item(s) are not delivarable on selected address. Try changing address or modify your cart items.";
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['data'] = array();
                        print_r(json_encode($this->response));
                        return;
                    }
                }
            }

            $product_variant_id = explode(',', $_POST['product_variant_id'] ?? '');

            foreach ($product_variant_id as $index => $id) {
                $mapped_attachments[$id] = isset($attachments[$index]) ? $attachments[$index] : null;
            }
            $_POST['attachments'] = $mapped_attachments;



            if ($_POST['payment_method'] == "COD") {
                for ($i = 0; $i < count($product_variant_id); $i++) {
                    $product_id = fetch_details("product_variants", ['id' => $product_variant_id[$i]], 'product_id');
                    $is_allowed = fetch_details("products", ['id' => $product_id[0]['product_id']], 'cod_allowed,name');
                    if ($is_allowed[0]['cod_allowed'] == 0) {
                        $this->response['error'] = true;
                        $this->response['message'] = "Cash On Delivery is not allow on the product " . $is_allowed[0]['name'];
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['data'] = array();
                        print_r(json_encode($this->response));
                        return false;
                    }
                }
            }
            $quantity = explode(',', $_POST['quantity'] ?? '');
            if (isset($_POST['product_type']) && $_POST['product_type'] != 'digital_product') {

                $check_current_stock_status = validate_stock($product_variant_id, $quantity);

                if ($check_current_stock_status['error'] == true) {
                    $this->response['error'] = true;
                    $this->response['message'] = $check_current_stock_status['message'];
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return false;
                }
            }

            $cart = get_cart_total($_POST['user_id'], false, '0', $_POST['address_id']);

            if (empty($cart)) {
                $this->response['error'] = true;
                $this->response['message'] = "Your Cart is empty.";
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            }

            if (isset($_POST['product_type']) && $_POST['product_type'] != 'digital_product') {
                $_POST['delivery_charge'] = get_delivery_charge($_POST['address_id'], $cart['total_arr']);
                if ($_POST['payment_method'] == 'COD' || $_POST['payment_method'] == 'cod') {
                    $_POST['delivery_charge'] = $_POST['delivery_charge_with_cod'];
                } else {
                    $_POST['delivery_charge'] = $_POST['delivery_charge_without_cod'];
                }

                $_POST['delivery_charge'] = str_replace(',', '', $_POST['delivery_charge']);
                $_POST['is_delivery_charge_returnable'] = intval($_POST['delivery_charge']) != 0 ? 1 : 0;
            }
            $wallet_balance = fetch_details('users', 'id=' . $_POST['user_id'], 'balance');

            $final_total = $cart['overall_amount'];
            $wallet_balance = $wallet_balance[0]['balance'];
            $_POST['wallet_balance_used'] = 0;
            if (isset($_POST['wallet_used']) && $_POST['wallet_used'] == 1) {
                if ($wallet_balance != 0) {
                    $_POST['is_wallet_used'] = 1;
                    if ($wallet_balance >= $final_total) {
                        $_POST['wallet_balance_used'] = $final_total;
                        $_POST['payment_method'] = 'wallet';
                    } else {
                        $_POST['wallet_balance_used'] = $wallet_balance;
                    }
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = "Insufficient balance";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return false;
                }
            }
            $promo_discount = 0;
            if (isset($_POST['promo_code']) && !empty($_POST['promo_code'])) {

                $validate = validate_promo_code($_POST['promo_code'], $this->data['user']->id, $cart['total_arr']);
                if ($validate['error']) {
                    $this->response['error'] = true;
                    $this->response['message'] = $validate['message'];
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    print_r(json_encode($this->response));
                    return false;
                } else {
                    $promo_discount = $validate['data'][0]['final_discount'];
                }
            }
            $_POST['final_total'] = $cart['overall_amount'] - $_POST['wallet_balance_used'] - $promo_discount;
            if ($_POST['payment_method'] == "Razorpay") {
                if (!verify_payment_transaction($_POST['razorpay_payment_id'], 'razorpay')) {
                    $this->response['error'] = true;
                    $this->response['message'] = "Invalid Razorpay Payment Transaction.";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return false;
                }
                $data['status'] = "success";
                $data['txn_id'] = $_POST['razorpay_payment_id'];
                $data['message'] = "Order Placed Successfully";
            } elseif ($_POST['payment_method'] == "instamojo") {
                if (!verify_payment_transaction($_POST['instamojo_order_id'], 'instamojo')) {
                    $this->response['error'] = true;
                    $this->response['message'] = "Invalid Instamojo Payment Transaction.";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return false;
                }

                $data['status'] = "success";
                $data['txn_id'] = $_POST['instamojo_payment_id'];
                $data['message'] = "Order Placed Successfully";
            } elseif ($_POST['payment_method'] == "phonepe") {
                $data['status'] = "awaiting";
                $_POST['active_status'] = "draft";
                $data['txn_id'] = $_POST['phonepe_transaction_id'];
                $data['message'] = "Payment is Not Done Yet";
            } elseif ($_POST['payment_method'] == "Flutterwave") {
                if (!verify_payment_transaction($_POST['flutterwave_transaction_id'], 'flutterwave')) {
                    $this->response['error'] = true;
                    $this->response['message'] = "Invalid Flutterwave Payment Transaction.";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return false;
                }
                $data['status'] = "success";
                $data['txn_id'] = $_POST['flutterwave_transaction_id'];
                $data['message'] = "Order Placed Successfully";
            } elseif ($_POST['payment_method'] == "Paytm") {
                $paytm_response = verify_payment_transaction($_POST['paytm_order_id'], 'paytm');
                if ($paytm_response['error'] == true) {
                    $this->response['error'] = true;
                    $this->response['message'] = "Invalid Paytm Transaction.";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = array();
                    print_r(json_encode($this->response));
                    return false;
                }
                $status = $paytm_response['data']['body']['resultInfo']['resultStatus'];
                $_POST['active_status'] = $status == "TXN_SUCCESS" ? 'received' : 'awaiting';
                $data['status'] = $status == "TXN_SUCCESS" ? 'success' : 'pending';
                $data['txn_id'] = $_POST['paytm_order_id'];
                $data['message'] = "Order Placed Successfully";
            } elseif ($_POST['payment_method'] == "Paystack") {
                $transfer = verify_payment_transaction($_POST['paystack_reference'], 'paystack');
                if (isset($transfer['data']['status']) && $transfer['data']['status']) {
                    if (isset($transfer['data']['data']['status']) && $transfer['data']['data']['status'] != "success") {
                        $this->response['error'] = true;
                        $this->response['message'] = "Invalid Paystack Transaction.";
                        $this->response['csrfName'] = $this->security->get_csrf_token_name();
                        $this->response['csrfHash'] = $this->security->get_csrf_hash();
                        $this->response['data'] = array();
                        print_r(json_encode($this->response));
                        return false;
                    }
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = "Error While Fetching the Order Details.Contact Admin ASAP.";
                    $this->response['csrfName'] = $this->security->get_csrf_token_name();
                    $this->response['csrfHash'] = $this->security->get_csrf_hash();
                    $this->response['data'] = $transfer;
                    print_r(json_encode($this->response));
                    return false;
                }
                $data['txn_id'] = $_POST['paystack_reference'];
                $data['message'] = "Order Placed Successfully";
                $data['status'] = "success";
            } elseif ($_POST['payment_method'] == "Stripe") {
                $_POST['active_status'] = "awaiting";
                $data['status'] = "success";
                $data['txn_id'] = $_POST['stripe_payment_id'];
                $data['message'] = "Order Placed Successfully";
            } elseif ($_POST['payment_method'] == "Paypal") {
                $_POST['active_status'] = "awaiting";
                $data['status'] = "success";
                $data['txn_id'] = null;
                $data['message'] = null;
            } elseif ($_POST['payment_method'] == "COD") {
                $_POST['active_status'] = "received";
            } elseif ($_POST['payment_method'] == "wallet") {
                $data['status'] = "success";
                $data['txn_id'] = null;
                $data['message'] = 'Order Placed Successfully';
            } elseif ($_POST['payment_method'] == BANK_TRANSFER) {
                $_POST['payment_method'] = "bank_transfer";
                $_POST['active_status'] = "awaiting";
                $data['status'] = "awaiting";
                $data['txn_id'] = null;
                $data['message'] = null;
            } elseif ($_POST['payment_method'] == "my_fatoorah") {
                $_POST['active_status'] = "awaiting";
                $data['status'] = "success";
                $data['txn_id'] = null;
                $data['message'] = null;
            } elseif ($_POST['payment_method'] == "instamojo") {
                $_POST['active_status'] = "awaiting";
                $data['status'] = "success";
                $data['txn_id'] = null;
                $data['message'] = null;
            }

            if (isset($_POST['product_type']) && $_POST['product_type'] != 'digital_product') {
                $_POST['address_id'] = $_POST['address_id'];
            } else {
                $_POST['address_id'] = '';
            }


            $order_data = $_POST;

            // if (isset($_FILES['documents']) && !empty($_FILES['documents'])) {
            //     $order_data['documents'] = $_FILES['documents'];
            // }


            // Pass the combined data to the model
            $res = $this->order_model->place_order($order_data);

            $order_item_id = fetch_details('order_items', ['order_id' => $res['order_id']], 'id,sub_total');
            for ($i = 0; $i < count($order_item_id); $i++) {
                $data['status'] = $data['status'];
                $data['txn_id'] = $data['txn_id'];
                $data['message'] = $data['message'];
                $data['order_id'] = $res['order_id'];
                $data['user_id'] = $_POST['user_id'];
                $data['type'] = $_POST['payment_method'];
                $data['amount'] = $order_item_id[$i]['sub_total'];
                $data['order_item_id'] = $order_item_id[$i]['id'];
                if (($_POST['payment_method'] != "COD" && $_POST['payment_method'] != "Paypal") ||  $_POST['payment_method'] == "bank_transfer") {
                    $this->transaction_model->add_transaction($data);
                }
            }
            // Retrieve user data directly from the $_POST or session
            $userId = $_POST['user_id']; // Or use session if user ID is stored in session
            $user = fetch_details('users', ['id' => $userId], 'web_fcm');


            $this->response['error'] = false;
            $this->response['message'] = $res['message'];
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
            $this->response['data'] = $res;
            print_r(json_encode($this->response));
            return false;
        }
    }

    public function validate_promo_code()
    {
        if ($this->data['is_logged_in']) {
            /*
            promo_code:'NEWOFF10'
            user_id:28
            final_total:'300'

        */
            $this->form_validation->set_rules('promo_code', 'Promo Code', 'trim|required|xss_clean');
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['message'] = validation_errors();
                $this->response['data'] = array();
                print_r(json_encode($this->response));
                return false;
            } else {
                $cart = get_cart_total($this->data['user']->id, false, '0', $_POST['address_id']);
                $validate = validate_promo_code($_POST['promo_code'], $this->data['user']->id, $cart['sub_total']);
                $this->response['error'] = $validate['error'];
                $this->response['message'] = $validate['message'];
                $this->response['data'] = $validate['data'];
                print_r(json_encode($this->response));
                return false;
            }
        } else {
            return false;
        }
    }

    public function pre_payment_setup()
    {
        $payment_settings = get_settings('payment_method', true);
        $country_code = $payment_settings['myfatoorah_country'];
        if ($this->data['is_logged_in']) {

            $_POST['user_id'] = $this->data['user']->id;
            $cart = get_cart_total($this->data['user']->id, false, '0', $_POST['address_id']);

            $wallet_balance = fetch_details('users', 'id=' . $this->data['user']->id, 'balance');
            $wallet_balance = $wallet_balance[0]['balance'];
            $overall_amount = $cart['overall_amount'];

            $row = $cart;
            if ($_POST['wallet_used'] == 1 && $wallet_balance > 0) {
                $overall_amount = $overall_amount - $wallet_balance;
            }
            if (!empty($_POST['promo_code']) && $_POST['payment_method'] != "my_fatoorah") {
                $validate = validate_promo_code($_POST['promo_code'], $this->data['user']->id, $cart['sub_total']);
                if ($validate['error']) {
                    $this->response['error'] = true;
                    $this->response['message'] = $validate['message'];
                    print_r(json_encode($this->response));
                    return false;
                } else {
                    $overall_amount = $overall_amount - $validate['data'][0]['final_discount'];
                }
            }

            // Handle delivery type and subtract delivery charges for pickup_from_store
            if (isset($_POST['delivery_type']) && $_POST['delivery_type'] == 'pickup_from_store') {
                // Subtract delivery charges from overall amount if pickup from store is selected
                $delivery_charge = isset($cart['delivery_charge']) ? $cart['delivery_charge'] : 0;
                $overall_amount = $overall_amount - $delivery_charge;
                
                // Ensure overall amount doesn't go below 0
                if ($overall_amount < 0) {
                    $overall_amount = 0;
                }
            }

            if ($_POST['payment_method'] == "Razorpay") {

                $order = $this->razorpay->create_order(intval($overall_amount * 100));
                if (!isset($order['error'])) {
                    $this->response['order_id'] = $order['id'];
                    $this->response['error'] = false;
                    $this->response['message'] = "Client Secret Get Successfully.";
                    print_r(json_encode($this->response));
                    return false;
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = $order['error']['description'];
                    $this->response['details'] = $order;
                    print_r(json_encode($this->response));
                    return false;
                }
            } elseif ($_POST['payment_method'] == "Stripe") {

                $user_details = fetch_details('users', ['id' => $_POST['user_id']], 'username,email');
                $address = fetch_details('addresses', ['user_id' => $_POST['user_id'], 'is_default' => 1], 'address,pincode,city,state,country');

                if (!empty($address)) {
                    $customer_address = $address[0];
                } else {
                    $address = fetch_details('addresses', ['user_id' => $_POST['user_id']], 'address,pincode,city,state,country');
                    $customer_address = $address[0];
                }

                $customer_data = [];
                $customer_data['name'] = $user_details[0]['username'];
                $customer_data['email'] = $user_details[0]['email'];
                $customer_data['line1'] = $customer_address['address'];
                $customer_data['postal_code'] = $customer_address['pincode'];
                $customer_data['city'] = $customer_address['city'];
                $customer_data['state'] = $customer_address['state'];
                $customer_data['country'] = $customer_address['country'];

                $customer = $this->stripe->create_customer($customer_data);

                $order = $this->stripe->create_payment_intent(array('amount' => intval($overall_amount * 100)), $customer['id']);

                $this->response['client_secret'] = $order['client_secret'];
                $this->response['customer_id'] = $customer['id'];
                $this->response['id'] = $order['id'];
            } elseif ($_POST['payment_method'] == "Midtrans") {
                $order_id = "mdtrns-" . $this->data['user']->id . "-" . time() . "-" . rand("100", "999");

                $order = $this->midtrans->create_transaction($order_id, $overall_amount);
                $order['body'] = (isset($order['body']) && !empty($order['body'])) ? json_decode($order['body'], 1) : "";

                if (!empty($order['body'])) {
                    $this->response['error'] = false;
                    $this->response['order_id'] = $order_id;
                    $this->response['token'] = $order['body']['token'];
                    $this->response['redirect_url'] = $order['body']['redirect_url'];
                    $this->response['message'] = "Transaction Token generated successfully.";
                    $this->response['overall_amount'] = $overall_amount;
                    print_r(json_encode($this->response));
                    return false;
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = "Oops! Token couldn't be generated! check your configurations!";
                    $this->response['details'] = $order;
                    $this->response['overall_amount'] = $overall_amount;
                    print_r(json_encode($this->response));
                    return false;
                }
            } elseif ($_POST['payment_method'] == "instamojo") {

                $data = [
                    'purpose' => 'transaction',
                    'amount' => $overall_amount,
                    'buyer_name' => $user[0]['username'],
                    'email' => isset($user[0]['email']) && !empty($user[0]['email']) ? $user[0]['email'] : 'foo@example.com',
                    'phone' => isset($user[0]['mobile']) && !empty($user[0]['mobile']) ? $user[0]['mobile'] : '9999999999',
                    'redirect_url' => base_url('admin/webhook/instamojo_success_url'),
                ];
                $order = $this->instamojo->payment_requests($data);

                if (!empty($order) && ($order['http_code'] == 200 || $order['http_code'] == '200' || $order['http_code'] == 201 || $order['http_code'] == '201')) {
                    $this->response['error'] = false;
                    $this->response['order_id'] = $order['id'];
                    $this->response['redirect_url'] = $order['longurl'];
                    $this->response['message'] = "Transaction Token generated successfully.";
                    print_r(json_encode($this->response));
                    return false;
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = "Oops! Token couldn't be generated! check your configurations!";
                    $this->response['details'] = $order;
                    print_r(json_encode($this->response));
                    return false;
                }
            } elseif ($_POST['payment_method'] == "my_fatoorah") {
                $order_id = $_POST['my_fatoorah_order_id'];
                $amount = fetch_details('orders', ['id' => $order_id], 'total_payable');
                $total_payable = $amount[0]['total_payable'];

                $order = $this->my_fatoorah->ExecutePayment($total_payable, 2, ["UserDefinedField" => $_POST['my_fatoorah_order_id']]);
                if (!empty($order->Data)) {
                    $this->response['error'] = false;
                    $this->response['PaymentURL'] = $order->Data->PaymentURL;
                    $this->response['message'] = "success";
                    print_r(json_encode($this->response));
                    return false;
                }
            } elseif ($_POST['payment_method'] == "Flutterwave" || $_POST['payment_method'] == "Paystack" || $_POST['payment_method'] == "Paytm") {
                
                $this->response['error'] = false;
                $this->response['final_amount'] = $overall_amount;
            }
            $this->response['error'] = false;
            $this->response['message'] = "Client Secret Get Successfully.";
            print_r(json_encode($this->response));
            return false;
        } else {
            $this->response['error'] = true;
            $this->response['message'] = "Unauthorised access is not allowed.";
            print_r(json_encode($this->response));
            return false;
        }
    }
    public function get_delivery_charge()
    {
        $settings = get_settings('shipping_method', true);
        $system_settings = get_settings('system_settings', true);

        $cart = $this->cart_model->get_user_cart($this->data['user']->id);
        if ($cart[0]['type'] == 'digital_product') {
            $this->response['delivery_charge_with_cod'] = '0';
            $this->response['delivery_charge_without_cod'] = '0';
            return false;
        }
        $standard_shipping_cart = $local_shipping_cart = [];
        $this->response['delivery_charge_with_cod'] = $this->response['delivery_charge_without_cod'] = 0;
        $this->response['estimate_date'] = "";
        $address_id = $this->input->post('address_id', true);
        if (isset($address_id) && !empty($address_id)) {

            $area_id = fetch_details('addresses', ['id' => $address_id], ['area_id', 'area', 'pincode', 'city']);
            $zipcode = $area_id[0]['pincode'];
            $zipcode_id = fetch_details('zipcodes', ['zipcode' => $zipcode], 'id')[0];

            $city = $area_id[0]['city'];
            $city_id = fetch_details('cities', ['name' => $city], 'id');
            $city_id = $city_id[0]['id'];

            if ((isset($system_settings['pincode_wise_deliverability']) && $system_settings['pincode_wise_deliverability'] == 1) || (isset($settings['local_shipping_method']) && isset($settings['shiprocket_shipping_method']) && $settings['local_shipping_method'] == 1 && $settings['shiprocket_shipping_method'] == 1)) {
                $product_availability = check_cart_products_delivarable($this->data['user']->id, $area_id[0]['area_id'], $zipcode, $zipcode_id['id']);
            }
            if (isset($system_settings['city_wise_deliverability']) && $system_settings['city_wise_deliverability'] == 1 && $settings['shiprocket_shipping_method'] != 1) {
                $product_availability = check_cart_products_delivarable($this->data['user']->id, $area_id[0]['area_id'], '', '', $city, $city_id);
            }
            $product_not_delivarable = array_filter($product_availability, function ($product) {
                return ($product['is_deliverable'] == false);
            });

            $cart = $this->cart_model->get_user_cart($this->data['user']->id);
            for ($i = 0; $i < count($cart); $i++) {
                $cart[$i]['delivery_by'] = $product_availability[$i]['delivery_by'];
                $cart[$i]['is_deliverable'] = $product_availability[$i]['is_deliverable'];
                if ($cart[$i]['delivery_by'] == "standard_shipping") {
                    $standard_shipping_cart[] = $cart[$i];
                } else {
                    $local_shipping_cart[] = $cart[$i];
                }
            }

            // if one item is deliverable by local and one is deliverable by standard shipping, then merge it to standard shipping and order is standard shipping order
            if (!empty($standard_shipping_cart)) {
                array_merge($standard_shipping_cart, $local_shipping_cart);
                unset($local_shipping_cart);
            }

            $this->response['error'] = (empty($product_not_delivarable)) ? false : true;
            $this->response['message'] = (empty($product_not_delivarable)) ? "All the products are deliverable on the selected address" : "Some of the item(s) are not delivarable on selected address. Try changing address or modify your cart items.";

            if (!empty($standard_shipping_cart)) {
                $delivery_pincode = fetch_details('addresses', ['id' => $_POST['address_id']], 'pincode');
                $parcels = make_shipping_parcels($cart);
                $parcels_details = check_parcels_deliveriblity($parcels, $delivery_pincode[0]['pincode']);

                $this->response['delivery_charge_with_cod'] = $parcels_details['delivery_charge_with_cod'];
                $this->response['delivery_charge_without_cod'] = $parcels_details['delivery_charge_without_cod'];
                $this->response['estimate_date'] = $parcels_details['estimate_date'];
                $this->response['shipping_method'] = $settings['shiprocket_shipping_method'];
            } elseif (!empty($local_shipping_cart)) {
                // print_r("adddress id : " . $address_id . '\n');
                // print_r("total : " . $_POST['total'] . '\n');
                $delivery_charge = get_delivery_charge($address_id, $_POST['total']);
                // print_r($delivery_charge);

                $this->response['delivery_charge_with_cod'] = $delivery_charge;
                $this->response['delivery_charge_without_cod'] = $delivery_charge;
            }

            $this->response['data'] = $cart;
            $this->response['availability_data'] = $product_availability;
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
        } else {
            $this->response['error'] = true;
            $this->response['message'] = "Please select address.";
            $this->response['csrfName'] = $this->security->get_csrf_token_name();
            $this->response['csrfHash'] = $this->security->get_csrf_hash();
        }

        print_r(json_encode($this->response));
    }

    public function send_bank_receipt()
    {
        $this->form_validation->set_rules('order_id', 'Order Id', 'trim|required|numeric|xss_clean');

        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = strip_tags(validation_errors());
            $this->response['data'] = array();
        } else {
            $order_id = $this->input->post('order_id', true);

            $order = fetch_details('orders', ['id' => $order_id], 'id');
            if (empty($order)) {
                $this->response['error'] = true;
                $this->response['message'] = "Order not found!";
                $this->response['data'] = [];
                print_r(json_encode($this->response));
                return false;
            }
            if (!file_exists(FCPATH . DIRECT_BANK_TRANSFER_IMG_PATH)) {
                mkdir(FCPATH . DIRECT_BANK_TRANSFER_IMG_PATH, 0777);
            }

            $temp_array = array();
            $files = $_FILES;
            $images_new_name_arr = array();
            $images_info_error = "";
            $allowed_media_types = implode('|', allowed_media_types());
            $config = [
                'upload_path' =>  FCPATH . DIRECT_BANK_TRANSFER_IMG_PATH,
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
                            resize_review_images($temp_array, FCPATH . DIRECT_BANK_TRANSFER_IMG_PATH);
                            $images_new_name_arr[$i] = DIRECT_BANK_TRANSFER_IMG_PATH . $temp_array['file_name'];
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
                            unlink(FCPATH . DIRECT_BANK_TRANSFER_IMG_PATH . $images_new_name_arr[$key]);
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
                'order_id' => $order_id,
                'attachments' => $images_new_name_arr,
            );
            if ($this->Order_model->add_bank_transfer_proof($data)) {
                /* Send Custom notification messages */
                $settings = get_settings('system_settings', true);
                $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                $user_roles = fetch_details("user_permissions", "", '*', '',  '', '', '');
                foreach ($user_roles as $user) {
                    $user_res = fetch_details('users', ['id' => $user['user_id']], 'fcm_id');
                    $fcm_ids[0][] = $user_res[0]['fcm_id'];
                }

                if (!empty($fcm_ids)) {
                    $custom_notification = fetch_details('custom_notifications', ['type' => "bank_transfer_proof"], '');

                    $hashtag_order_id = '< order_id >';
                    $hashtag_application_name = '< application_name >';

                    $string = json_encode($custom_notification[0]['message'], JSON_UNESCAPED_UNICODE);
                    $hashtag = html_entity_decode($string);

                    $data = str_replace(array($hashtag_order_id, $hashtag_application_name), array($order_id, $app_name), $hashtag);
                    $message = output_escaping(trim($data, '"'));

                    $customer_msg = (!empty($custom_notification)) ? $message : 'Hello Dear Admin you have new order bank transfer proof. Order ID #' . $order_id . ' please take note of it! Thank you. Regards ' . $app_name . '';

                    $fcmMsg = array(
                        'title' => (!empty($custom_notification)) ? $custom_notification[0]['title'] : "You have new order proof",
                        'body' => $customer_msg,
                        'type' => "bank_transfer_proof",
                    );

                    send_notification($fcmMsg, $fcm_ids, $fcmMsg);
                }


                $this->response['error'] = false;
                $this->response['message'] =  'Bank Payment Receipt Added Successfully!';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = (!empty($data)) ? $data : [];
                print_r(json_encode($this->response));
                redirect(base_url('my-account/order-details/' . $order_id), 'refresh');
            } else {
                $this->response['error'] = true;
                $this->response['message'] =  'Bank Payment Receipt Was Not Added';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = (!empty($this->response['data'])) ? $this->response['data'] : [];
                print_r(json_encode($this->response));
            }
        }
    }

    public function check_product_availability()
    {
        $this->form_validation->set_rules('address_id', 'Address Id', 'trim|numeric|xss_clean|required');
        if (!$this->form_validation->run()) {
            $this->response['error'] = true;
            $this->response['message'] = validation_errors();
            $this->response['data'] = array();
            echo json_encode($this->response);
        } else {
            $settings = get_settings('shipping_method', true);
            $system_settings = get_settings('system_settings', true);
            $product_delivarable = array();
            $address_id = $this->input->post('address_id', true);

            $area_id = fetch_details('addresses', ['id' => $address_id], ['area_id', 'area', 'pincode', 'city']);
            $zipcode = $area_id[0]['pincode'];
            $zipcode_id = fetch_details('zipcodes', ['zipcode' => $zipcode], 'id')[0];

            $city = $area_id[0]['city'];
            $city_id = fetch_details('cities', ['name' => $city], 'id');
            $city_id = $city_id[0]['id'];

            if ((isset($system_settings['pincode_wise_deliverability']) && $system_settings['pincode_wise_deliverability'] == 1) || (isset($settings['local_shipping_method']) && isset($settings['shiprocket_shipping_method']) && $settings['local_shipping_method'] == 1 && $settings['shiprocket_shipping_method'] == 1)) {
                $product_delivarable = check_cart_products_delivarable($this->data['user']->id, $area_id[0]['area_id'], $zipcode, $zipcode_id['id']);
            }
            if (isset($system_settings['city_wise_deliverability']) && $system_settings['city_wise_deliverability'] == 1 && $settings['shiprocket_shipping_method'] != 1) {
                $product_delivarable = check_cart_products_delivarable($this->data['user']->id, $area_id[0]['area_id'], '', '', $city, $city_id);
            }

            if (!empty($product_delivarable)) {
                $product_not_delivarable = array_filter($product_delivarable, function ($var) {
                    return ($var['is_deliverable'] == false);
                });

                $this->response['error'] = (empty($product_not_delivarable)) ? false : true;
                $this->response['message'] = (empty($product_not_delivarable)) ? "All the products are deliverable on the selected address" : "Some of the item(s) are not delivarable on selected address. Try changing address or modify your cart items.";
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['data'] = $product_delivarable;
                $this->response['zipcode'] = $zipcode;
                echo json_encode($this->response);
                return false;
            } else {
                $this->response['error'] = true;
                $this->response['message'] = 'Cannot be delivered to "' . $zipcode . '" in selected address.';
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                echo json_encode($this->response);
                return false;
            }
        }
    }

    public function wallet_refill()
    {

        $payment_settings = get_settings('payment_method', true);
        $country_code = $payment_settings['myfatoorah_country'];
        if ($this->data['is_logged_in']) {
            $_POST['user_id'] = $this->data['user']->id;
            $overall_amount = $_POST['amount'];

            if ($_POST['payment_method'] == "Flutterwave" || $_POST['payment_method'] == "Paystack" || $_POST['payment_method'] == "Paytm") {
                $this->response['error'] = false;
                $this->response['final_amount'] = $_POST['amount'];
                $this->response['error'] = false;
                $this->response['message'] = "Client Secret Get Successfully.";
                print_r(json_encode($this->response));
                return false;
            }

            if ($_POST['payment_method'] == "phonepe") {
                $user_id = $this->data['user']->user_id;
                $this->response['phonepe_transaction_id'] = $_POST['order_id'];
                $this->response['error'] = false;
                $this->response['message'] = "Client Secret Get Successfully.";

                $data['transaction_type'] = "wallet";
                $data['user_id'] = $user_id;
                $data['type'] = "credit";
                $data['txn_id'] = $_POST['order_id'];
                $data['amount'] = $_POST['amount'];
                $data['status'] = "awaiting";
                $data['message'] = "waiting for payment";

                $this->transaction_model->add_transaction($data);

                print_r(json_encode($this->response));
                return false;
            }
            if ($_POST['payment_method'] == "Razorpay") {
                $order = $this->razorpay->create_order(intval($overall_amount * 100));
                if (!isset($order['error'])) {
                    $this->response['order_id'] = $order['id'];
                    $this->response['error'] = false;
                    $this->response['message'] = "Client Secret Get Successfully.";
                    print_r(json_encode($this->response));
                    return false;
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = $order['error']['description'];
                    $this->response['details'] = $order;
                    print_r(json_encode($this->response));
                    return false;
                }
            }

            if ($_POST['payment_method'] == "Midtrans") {
                $order = $this->midtrans->create_transaction($_POST['order_id'], $_POST['amount']);
                $order['body'] = (isset($order['body']) && !empty($order['body'])) ? json_decode($order['body'], 1) : "";

                if (!empty($order['body'])) {
                    $this->response['error'] = false;
                    $this->response['order_id'] = $_POST['order_id'];
                    $this->response['token'] = $order['body']['token'];
                    $this->response['redirect_url'] = $order['body']['redirect_url'];
                    $this->response['message'] = "Transaction Token generated successfully.";
                    print_r(json_encode($this->response));
                    return false;
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = "Oops! Token couldn't be generated! check your configurations!";
                    $this->response['details'] = $order;
                    print_r(json_encode($this->response));
                    return false;
                }
            }

            if ($_POST['payment_method'] == "my_fatoorah") {
                $order_id = $_POST['order_id'];
                $total_payable = $_POST['amount'];

                $order = $this->my_fatoorah->ExecutePayment($total_payable, 2, ["UserDefinedField" => $order_id]);
                if (!empty($order->Data)) {
                    $this->response['error'] = false;
                    $this->response['PaymentURL'] = $order->Data->PaymentURL;
                    $this->response['message'] = "success";
                    print_r(json_encode($this->response));
                    return false;
                }
            }

            if ($_POST['payment_method'] == "instamojo") {
                $data = [
                    'purpose' => $_POST['order_id'],
                    'amount' => $_POST['amount'],
                    'buyer_name' => $user[0]['username'],
                    'email' => isset($user[0]['email']) && !empty($user[0]['email']) ? $user[0]['email'] : 'foo@example.com',
                    'phone' => isset($user[0]['mobile']) && !empty($user[0]['mobile']) ? $user[0]['mobile'] : '9999999999',
                ];
                $order = $this->instamojo->payment_requests($data);
                if (!empty($order)) {
                    $this->response['error'] = false;
                    $this->response['order_id'] = $order['id'];
                    $this->response['redirect_url'] = $order['longurl'];
                    $this->response['message'] = "Transaction Token generated successfully.";
                    print_r(json_encode($this->response));
                    return false;
                } else {
                    $this->response['error'] = true;
                    $this->response['message'] = "Oops! Token couldn't be generated! check your configurations!";
                    $this->response['details'] = $order;
                    print_r(json_encode($this->response));
                    return false;
                }
            }

            if ($_POST['payment_method'] == "Stripe") {
                log_message('error', 'payment_method --> ' . var_export($_POST['payment_method'], true));
                $user_details = fetch_details('users', ['id' => $_POST['user_id']], 'username,email');
                $address = fetch_details('addresses', ['user_id' => $_POST['user_id'], 'is_default' => 1], 'address,pincode,city,state,country');
                if (!empty($address)) {
                    $customer_address = $address[0];
                } else {
                    $address = fetch_details('addresses', ['user_id' => $_POST['user_id']], 'address,pincode,city,state,country');
                    $customer_address = $address[0];
                }

                $customer_data = [];
                $customer_data['name'] = $user_details[0]['username'];
                $customer_data['email'] = $user_details[0]['email'];
                $customer_data['line1'] = $customer_address['address'];
                $customer_data['postal_code'] = $customer_address['pincode'];
                $customer_data['city'] = $customer_address['city'];
                $customer_data['state'] = $customer_address['state'];
                $customer_data['country'] = $customer_address['country'];
                $cus = $this->stripe->create_customer($customer_data);
                $order = $this->stripe->create_payment_intent(array('amount' => ($_POST['amount'] * 100), "metadata[order_id]" => ($_POST['order_id'])), $cus['id']);
                log_message('error', 'order --> ' . var_export($order, true));
                $this->response['client_secret'] = $order['client_secret'];
                $this->response['id'] = $order['id'];
                print_r(json_encode($this->response));
                return false;
            }
        } else {
            $this->response['error'] = true;
            $this->response['message'] = "Unauthorised access is not allowed.";
            print_r(json_encode($this->response));
            return false;
        }
    }
    public function check_stock()
    {
        $product_id = $this->input->post('product_id');
        $variant_id = $this->input->post('variant_id');
        $quantity = $this->input->post('quantity');

        // For simplicity, assuming quantity is an array
        $quantity_array = array($quantity);

        // Call validate_stock function from helper
        $this->response['csrfName'] = $this->security->get_csrf_token_name();
        $this->response['csrfHash'] = $this->security->get_csrf_hash();
        $this->load->helper('function_helper');
        $result = validate_stock(array($variant_id), $quantity_array);

        // Send response
        echo json_encode($result);
    }
}
