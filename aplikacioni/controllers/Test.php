<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Test extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('function_helper');
        $this->load->model('chat_model');
    }
    public function send_test_notification()
    {
        // Example FCM token
        $fcm_token[][] = 'co7JUsINRvKhZ8OEU-ZG7d:APA91bE6wn12_RMwcHBvT2ybr4XjvIty49echIc6WDIWnMnxcBks6fO6XcA_aAkqrMZPCZADc2DH2IGpy-8b7sGZdKACRrCLVRwplEsSc67Yct9IbIyb1hZU2h8slTtyI7bYajAaTkdZ';

        // Construct the notification data
        $title = "Test Notification";
        $body = "This is a test notification to check the functionality.";
        $customBodyFields = [
            'title' => $title,
            'body' => $body,
            'type' => 'Test Type'
        ];

        // Send the notification
        print_r(send_notification($customBodyFields, $fcm_token, $customBodyFields));
    }

    public function test_access_token()
    {
        $accessToken = getAccessToken();
        echo "Access Token: " . $accessToken . "<br>";
    }
    
    public function  index()
    {
        $product_id = '35,15';
        $data =  is_exist_in_current_flash_sale($product_id);

        if ($data) {
            echo "123";
            return;
        } else {
            echo "13";
            return;
        }
    }
}
