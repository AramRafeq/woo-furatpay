<?php


class FuratPay_IPN_Handler {

    public function __construct($api_url, $token) {
        $this->api_url = $api_url;
        $this->api_key = $token;

        // Register REST API route
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('furatpay/v1', '/ipn', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_ipn'],
            'permission_callback' => '__return_true', // Allow public access
        ]);
    }

   
   
    public function handle_ipn(WP_REST_Request $request) {

        error_log('FuratPay IPN received');

        try {
            $payload = $request->get_json_params();
           
           
            if (!$payload) {
                throw new Exception('Invalid JSON received');
            }

            $headers = $request->get_headers();
            $timestamp = isset($headers['x_timestamp']) ? $headers['x_timestamp'][0]:null;
            $payloadSignature =  isset( $headers['x_signature']) ? $headers['x_signature'][0] :null;
            $signaturePayload = $payload['data']['id'].$payload['data']['code'].$payload['data']['order_number'].$timestamp;
            $calculatedSignature = hash_hmac('sha256', trim($signaturePayload), $this->api_key,false);

            if($calculatedSignature!==$payloadSignature){
                throw new Exception('Invalid payload signature');
            }

           
            if ($payload['type'] != 'INVOICE_PAID' && $payload['type'] != 'INVOICE_UPDATED' ) {
                return new WP_REST_Response(['success' => false, 'message'=>'webhook type is ignored'], 400);
            }

            if (!isset($payload['data']['order_number'])) {
                throw new Exception('Missing invoice code');
            }

            $order = $this->get_order_by_id($payload['data']['order_number']);
            error_log('ORDER: ' . $order);
            $invoice_status = $this->get_invoice_status($payload['data']['id']);

            error_log("Invoice Status: $invoice_status");

            if ('paid' === $invoice_status ) {
                $order->payment_complete();
                $order->add_order_note(__('Payment confirmed via FuratPay IPN', 'woo_furatpay'));
            }

            return new WP_REST_Response(['success' => true], 200);
        } catch (Exception $e) {
            error_log('FuratPay IPN Error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }


    private function get_invoice_status($invoice_id) {
        $response = wp_remote_get(
            $this->api_url . '/invoice/' . $invoice_id,
            [
                'headers' => [
                    'X-API-key' => $this->api_key,
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

    private function get_order_by_id($order_id) {
        $order= wc_get_order($order_id);
        
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        return $order;
    }
}
