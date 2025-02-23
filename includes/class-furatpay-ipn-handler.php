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
            $headers = $request->get_headers();
            $timestamp = isset($headers['x_timestamp']) ? $headers['x_timestamp'][0]:null;
            $payloadSignature =  isset( $headers['x_signature']) ? $headers['x_signature'][0] :null;
            $payloadStr = json_encode($payload);
            $signaturePayload = $payloadStr.$timestamp.$this->api_key;
            // $calculatedSignature =  'sha256='.hash('sha256', $signaturePayload);
            $calculatedSignature =  hash('sha256', $signaturePayload);

            if (!$payload) {
                throw new Exception('Invalid JSON received');
            }

            error_log('Timestamp: ' . $timestamp);
            error_log('Payload Signature   : ' . $payloadSignature);
            error_log('Calculated Signature: ' . $calculatedSignature);
            error_log('RawPayload: ' . $signaturePayload);
            // error_log('Headers: ' .  print_r($headers, true));
            // error_log('Payload: ' . print_r($payload, true));

            // if ($payload['type'] != 'INVOICE_PAID' ) {
            //     return new WP_REST_Response(['success' => false, 'message'=>'webhook type is ignored'], 400);
            // }

            if (!isset($payload['invoice_id'])) {
                throw new Exception('Missing invoice_id');
            }

            

            $order = $this->get_order_by_invoice_id($payload['invoice_id']);
            $invoice_status = $this->get_invoice_status($payload['invoice_id']);

            error_log("Invoice Status: $invoice_status");

            if ('paid' === $invoice_status) {
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
