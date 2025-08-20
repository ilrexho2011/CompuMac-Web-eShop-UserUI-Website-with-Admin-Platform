<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Payment_settings extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'language', 'timezone_helper']);
        $this->load->model('Setting_model');

        if (!has_permissions('read', 'payment_methods_settings')) {
            $this->session->set_flashdata('authorize_flag', PERMISSION_ERROR_MSG);
            redirect('admin/home', 'refresh');
        }
    }


    public function index()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            $this->data['main_page'] = FORMS . 'payment-settings';
            $settings = get_settings('system_settings', true);
            $this->data['title'] = 'Payment Methods Management | ' . $settings['app_name'];
            $this->data['meta_description'] = 'Payment Methods Management  | ' . $settings['app_name'];
            $this->data['settings'] = get_settings('payment_method', true);
            $this->load->view('admin/template', $this->data);
        } else {
            redirect('admin/login', 'refresh');
        }
    }

    public function update_payment_settings()
    {
        if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
            if (print_msg(!has_permissions('update', 'payment_methods_settings'), PERMISSION_ERROR_MSG, 'payment_methods_settings')) {
                return false;
            }
            $_POST['temp'] = '1';
            $this->form_validation->set_rules('temp', '', 'trim|required|xss_clean');

            $paypal_payment_method = $this->input->post('paypal_payment_method', true);
            if (isset($paypal_payment_method)) {
                $this->form_validation->set_rules('paypal_mode', 'Payyou Payment Mode', 'trim|required|xss_clean');
                $this->form_validation->set_rules('paypal_business_email', 'Paypal Business Email', 'trim|required|xss_clean|valid_email');
                $this->form_validation->set_rules('currency_code', 'Currency Code', 'trim|required|xss_clean');
            }

            $payumoney_payment_method = $this->input->post('payumoney_payment_method', true);
            if (isset($payumoney_payment_method)) {
                $this->form_validation->set_rules('payumoney_mode', 'Payumoney Mode', 'trim|required|xss_clean');
                $this->form_validation->set_rules('payumoney_merchant_key', 'Payumoney Merchant Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('payumoney_merchant_id', 'Payumoney Merchant Id', 'trim|required|xss_clean');
                $this->form_validation->set_rules('payumoney_salt', 'Payumoney Salt', 'trim|required|xss_clean');
            }

            $razorpay_payment_method = $this->input->post('razorpay_payment_method', true);
            if (isset($razorpay_payment_method)) {
                $this->form_validation->set_rules('razorpay_key_id', 'Razorpay Key Id', 'trim|required|xss_clean');
                $this->form_validation->set_rules('razorpay_secret_key', 'Razorpay Secret Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('razorpay_webhook_secret_key', 'Razorpay Webhook Secret Key', 'trim|required|xss_clean');
            }

            $midtrans_payment_method = $this->input->post('midtrans_payment_method', true);
            if (isset($midtrans_payment_method)) {
                $this->form_validation->set_rules('midtrans_payment_mode', 'Midtrans Payment Mode', 'trim|required|xss_clean');
                $this->form_validation->set_rules('midtrans_client_key', 'Midtrans Client  Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('midtrans_merchant_id', 'Midtrans Merchant ID', 'trim|required|xss_clean');
                $this->form_validation->set_rules('midtrans_server_key', 'Midtrans Server Key', 'trim|required|xss_clean');
            }

            $paystack_payment_method = $this->input->post('paystack_payment_method', true);
            if (isset($paystack_payment_method)) {
                $this->form_validation->set_rules('paystack_key_id', 'Paystack Key Id', 'trim|required|xss_clean');
                $this->form_validation->set_rules('paystack_secret_key', 'Paystack Secret Key', 'trim|required|xss_clean');
            }

            $flutterwave_payment_method = $this->input->post('flutterwave_payment_method', true);
            if (isset($flutterwave_payment_method)) {
                $this->form_validation->set_rules('flutterwave_public_key', 'Flutterwave Public Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('flutterwave_secret_key', 'Flutterwave Secret Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('flutterwave_encryption_key', 'Flutterwave Encryption Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('flutterwave_webhook_secret_key', 'Flutterwave Webhook Secret Key', 'trim|required|xss_clean');
            }

            $stripe_payment_method = $this->input->post('stripe_payment_method', true);
            if (isset($stripe_payment_method)) {
                $this->form_validation->set_rules('stripe_publishable_key', 'Stripe Publishable Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('stripe_secret_key', 'Stripe Secret Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('stripe_webhook_secret_key', 'Stripe Webhook Secret Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('stripe_currency_code', 'Stripe Currency Code', 'trim|required|xss_clean');
            }

            $paytm_payment_method = $this->input->post('paytm_payment_method', true);
            if (isset($paytm_payment_method)) {
                $this->form_validation->set_rules('paytm_payment_mode', 'Paytm Payment Mode', 'trim|required|xss_clean');
                $this->form_validation->set_rules('paytm_merchant_key', 'Paytm Merchant Key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('paytm_merchant_id', 'Paytm Merchant ID', 'trim|required|xss_clean');
                if ($this->input->post('paytm_payment_mode', true) == 'production') {
                    $this->form_validation->set_rules('paytm_website', 'Paytm website', 'trim|required|xss_clean');
                    $this->form_validation->set_rules('paytm_industry_type_id', 'Paytm Industry Type ID', 'trim|required|xss_clean');
                }
            }

            $google_pay_payment_method = $this->input->post('google_pay_payment_method', true);
            if (isset($google_pay_payment_method)) {
                $this->form_validation->set_rules('google_pay_mode', 'Google Pay Payment Mode', 'trim|required|xss_clean');
                $this->form_validation->set_rules('google_pay_merchant_name', 'Google Pay Merchant Name', 'trim|required|xss_clean');
                $this->form_validation->set_rules('google_pay_merchant_id', 'Google Pay Merchant ID', 'trim|required|xss_clean');
                $this->form_validation->set_rules('google_pay_currency_code', 'Google Pay Currency Code', 'trim|required|xss_clean');
                $this->form_validation->set_rules('google_pay_country_code', 'Google Pay Country Code', 'trim|required|xss_clean');
            }

            $payment_method = $this->input->post('myfaoorah_payment_method', true);
            if (isset($payment_method)) {
                $this->form_validation->set_rules('myfaoorah_payment_method', 'myFatoorah Payment  Mode', 'trim|required|xss_clean');
                $this->form_validation->set_rules('myfatoorah_token', 'Myfatoorah Token', 'trim|required|xss_clean');
                $this->form_validation->set_rules('myfatoorah_payment_mode', 'Myfatoorah Payment Mode ', 'trim|required|xss_clean');
                $this->form_validation->set_rules('myfatoorah_language', 'Myfatoorah Language', 'trim|required|xss_clean');
                $this->form_validation->set_rules('myfatoorah_country', 'Myfatoorah Country', 'trim|required|xss_clean');
                $this->form_validation->set_rules('myfatoorah__secret_key', 'myfatoorah Secret Key', 'trim|required|xss_clean');
            }


            $direct_bank_transfer = $this->input->post('direct_bank_transfer', true);
            if (isset($direct_bank_transfer)) {
                $this->form_validation->set_rules('account_name', 'Account Name', 'trim|required|xss_clean');
                $this->form_validation->set_rules('account_number', 'Account Number', 'trim|required|xss_clean');
                $this->form_validation->set_rules('bank_name', 'Bank Name', 'trim|required|xss_clean');
                $this->form_validation->set_rules('bank_code', 'Bank Code', 'trim|required|xss_clean');
            }

            $paymentMethod = $this->input->post('instamojo_payment_method', true);
            if (isset($paymentMethod)) {
                $this->form_validation->set_rules('instamojo_payment_mode', 'Instamojo Payment  Mode', 'trim|required|xss_clean');
                $this->form_validation->set_rules('instamojo_client_id', 'Instamojo client id', 'trim|required|xss_clean');
                $this->form_validation->set_rules('instamojo_client_secret', 'Instamojo client secret', 'trim|required|xss_clean');
            }

            $paymentMethod = $this->input->post('phonepe_payment_method', true);
            if (isset($paymentMethod)) {
                $this->form_validation->set_rules('phonepe_payment_mode', 'phonepe Payment Mode', 'trim|required|xss_clean');
                $this->form_validation->set_rules('phonepe_marchant_id', 'phonepe marchant id', 'trim|required|xss_clean');
                $this->form_validation->set_rules('phonepe_salt_key', 'phonepe salt key', 'trim|required|xss_clean');
                $this->form_validation->set_rules('phonepe_salt_index', 'phonepe salt index', 'trim|required|xss_clean');
            }
            if (!$this->form_validation->run()) {
                $this->response['error'] = true;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = validation_errors();
                print_r(json_encode($this->response));
            } else {

                $this->Setting_model->update_payment_method($_POST);
                $this->response['error'] = false;
                $this->response['csrfName'] = $this->security->get_csrf_token_name();
                $this->response['csrfHash'] = $this->security->get_csrf_hash();
                $this->response['message'] = 'System Setting Updated Successfully';
                print_r(json_encode($this->response));
            }
        } else {
            redirect('admin/login', 'refresh');
        }
    }
}
