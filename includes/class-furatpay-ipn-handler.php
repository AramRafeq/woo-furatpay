<?php
class FuratPay_IPN_Handler {

    public function __construct($api_url, $token) {
        $this->api_url = $api_url;
        $this->token = $token;
        
        add_action('woocommerce_api_furatpay_ipn', [$this, 'handle_ipn']);
    }

    public function handle_ipn() {
        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($payload['invoice_id'])) {
                throw new Exception('Invalid IPN payload');
            }
            
            $order = $this->get_order_by_invoice_id($payload['invoice_id']);
            
            // Verify invoice status with API
            $invoice_status = $this->get_invoice_status($payload['invoice_id']);
            
            if ('paid' === $invoice_status) {
                $order->payment_complete();
                $order->add_order_note(__('Payment confirmed via FuratPay IPN', 'woo_furatpay'));
            }
            
            wp_send_json_success();
            
        } catch (Exception $e) {
            error_log('FuratPay IPN Error: ' . $e->getMessage());
            wp_send_json_error();
        }
    }

    private function get_invoice_status($invoice_id) {
        $response = wp_remote_get(
            $this->api_url . '/invoice/' . $invoice_id,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json'
                ]
            ]
        );
        
        if (is_wp_error($response)) {
            throw new Exception('Invoice status check failed');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $body['status'] ?? 'unknown';
    }

    private function get_order_by_invoice_id($invoice_id) {
        $orders = wc_get_orders([
            'meta_key' => '_furatpay_invoice_id',
            'meta_value' => $invoice_id,
            'limit' => 1
        ]);
        
        if (empty($orders)) {
            throw new Exception('Order not found');
        }
        
        return $orders[0];
    }
}