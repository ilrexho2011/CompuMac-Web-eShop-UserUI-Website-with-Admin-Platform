<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Cron_job extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library(['ion_auth', 'form_validation', 'upload']);
        $this->load->helper(['url', 'language', 'file']);
        $this->load->model(['Promo_code_model', 'order_model', 'Cart_model']);
    }

    public function settle_cashback_discount()
    {
        return $this->Promo_code_model->settle_cashback_discount();
    }
    public function fetch_active_flash_sale()
    {
        return fetch_active_flash_sale();
    }

    public function draft_order_settel()
    {
        return $this->order_model->delete_draft_orders();
    }
    public function remaining_cart()
    {
        return $this->Cart_model->cart_item_remainder();
    }
}
