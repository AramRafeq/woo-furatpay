<?php
class FuratPay_API_Handler
{

    public static function get_payment_services($api_url, $token)
    {
        error_log('FuratPay API: Starting payment services request');
        error_log('FuratPay API: Using URL: ' . $api_url);
        error_log('FuratPay API: Token present: ' . (!empty($token) ? 'yes' : 'no'));

        if (empty($api_url) || empty($token)) {
            error_log('FuratPay API: Configuration error - missing URL or token');
            throw new Exception(__('Payment gateway is not properly configured', 'woo_furatpay'));
        }

        $api_url = rtrim($api_url, '/');
        $endpoint = $api_url . '/payment_service/linked/list?limit=10&offset=0&status=production,testing';
        error_log('FuratPay API: Full endpoint URL: ' . $endpoint);

        $response = wp_remote_get(
            $endpoint,
            [
                'headers' => [
                    'x-api-key' => $token,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 15,
                'sslverify' => false
            ]
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('FuratPay API Error: ' . $error_message);
            error_log('FuratPay API Error Details: ' . print_r($response, true));
            throw new Exception(__('Failed to retrieve payment services: ' . $error_message, 'woo_furatpay'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('FuratPay API: Response code: ' . $response_code);
        error_log('FuratPay API: Raw response body: ' . $body);

        $body_array = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('FuratPay API Error: JSON decode error: ' . json_last_error_msg());
            throw new Exception(__('Invalid API response format', 'woo_furatpay'));
        }

        if (!isset($body_array['data']) || !is_array($body_array['data'])) {
            error_log('FuratPay API Error: Invalid response structure');
            throw new Exception(__('Invalid payment services response', 'woo_furatpay'));
        }

        $services = $body_array['data'];
        error_log('FuratPay API: Successfully retrieved ' . count($services) . ' payment services');
        error_log('FuratPay API: Services: ' . print_r($services, true));

        return $services;
    }

    public static function create_invoice($api_url, $token, WC_Order $order, $customer_id)
    {
        if (empty($api_url) || empty($token)) {
            throw new Exception(__('Payment gateway is not properly configured', 'woo_furatpay'));
        }

        $order_total = $order->get_total();
        $current_time = current_time('mysql', true);
        
        $invoice_data = [
            'code' => $order->get_id().'',
            // 'code' => $order->get_order_number(),
            'number' => '1',
            'order_number' => $order->get_id().'',
            // 'order_number' => $order->get_order_number(),
            'customer_id' => $customer_id,
            'currency_id' => 3,
            'base_currency_id' => 3,
            'rate_id' => -1,
            'rate' => 1,
            'at' => $current_time,
            'due_at' => $current_time,
            'terms' => 'due_on_recept',
            'subject' => 'Web Payment',
            'customer_notes' => '   ',
            'terms_and_conditions' => '   ',
            'notes' => '   ',
            'attachments' => [],
            'discount' => 0,
            'discount_type' => 'percentage',
            'subtotal' => $order_total,
            'total' => $order_total,
            'status' => 'pending',
            'remarks' => '   ',
            'items' => array_map(function($item) {
                return [
                    'item' => $item->get_name(),
                    'description' => ' ',
                    'rate' => $item->get_total() / $item->get_quantity(),
                    'quantity' => $item->get_quantity(),
                    'discount' => 0
                ];
            }, array_values($order->get_items())),
            'meta_data' => (object)[]
        ];

        error_log('FuratPay API: Creating invoice with data: ' . print_r($invoice_data, true));

        $response = wp_remote_post(
            $api_url . '/invoice',
            [
                'headers' => [
                    'x-api-key' => $token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($invoice_data),
                'timeout' => 15,
                'sslverify' => false
            ]
        );

        if (is_wp_error($response)) {
            error_log('FuratPay API Error: Failed to create invoice - ' . $response->get_error_message());
            throw new Exception(__('Invoice creation failed', 'woo_furatpay'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        error_log('FuratPay API: Invoice creation response code: ' . $response_code);
        error_log('FuratPay API: Invoice creation response body: ' . $body);

        if ($response_code !== 200 && $response_code !== 201) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';
            error_log('FuratPay API Error: ' . $error_message);
            throw new Exception(__('Invoice creation failed: ', 'woo_furatpay') . $error_message);
        }

        $body_array = json_decode($body, true);
        if (!isset($body_array['id'])) {
            error_log('FuratPay API Error: Invalid invoice response - ' . print_r($body_array, true));
            throw new Exception(__('Invalid invoice response', 'woo_furatpay'));
        }

        $order->update_meta_data('_furatpay_invoice_id', $body_array['id']);
        $order->add_order_note(__('FuratPay Invoice Id: '. $body_array['id'], 'woo_furatpay'));
        $order->save();

        return $body_array['id'];
    }

    public static function create_payment($api_url, $token, $invoice_id, $service_id)
    {
        error_log('FuratPay API: Creating payment for invoice ' . $invoice_id . ' with service ' . $service_id);
        
        $api_url = rtrim($api_url, '/');
        $endpoint = $api_url . '/public/invoice/pay';
        
        $payment_data = [
            'payment_service_id' => $service_id,
            'invoice_id' => $invoice_id
        ];
        
        error_log('FuratPay API: Payment request data: ' . print_r($payment_data, true));
        error_log('FuratPay API: Payment endpoint: ' . $endpoint);

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => [
                    'x-api-key' => $token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($payment_data),
                'timeout' => 15,
                'sslverify' => false
            ]
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('FuratPay API Error: Failed to create payment - ' . $error_message);
            throw new Exception(__('Payment initiation failed', 'woo_furatpay'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        error_log('FuratPay API: Payment creation response code: ' . $response_code);
        error_log('FuratPay API: Payment creation response body: ' . $body);
        error_log('FuratPay API: Payment service ID used: ' . $service_id);
        error_log('FuratPay API: Response structure: ' . print_r(json_decode($body, true), true));

        if ($response_code !== 200 && $response_code !== 201) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';
            error_log('FuratPay API Error: Payment creation failed - ' . $error_message);
            throw new Exception(__('Payment creation failed: ', 'woo_furatpay') . $error_message);
        }

        $body_array = json_decode($body, true);
        
        // Log the response structure details
        error_log('FuratPay API: Response data structure check:');
        error_log('- Has data.redirect_uri: ' . (isset($body_array['data']['redirect_uri']) ? 'yes' : 'no'));
        error_log('- Has url: ' . (isset($body_array['url']) ? 'yes' : 'no'));
        error_log('- Has data.url: ' . (isset($body_array['data']['url']) ? 'yes' : 'no'));
        error_log('- Has personalAppLink: ' . (isset($body_array['personalAppLink']) ? 'yes' : 'no'));
        error_log('- Has businessAppLink: ' . (isset($body_array['businessAppLink']) ? 'yes' : 'no'));
        error_log('- Has corporateAppLink: ' . (isset($body_array['corporateAppLink']) ? 'yes' : 'no'));
        
        // Check for the URL in the response structure
        $payment_url = null;
        
        // Handle PayTabs response (service_id 5)
        if ($service_id == 5) {
            if (isset($body_array['redirect_url'])) {
                $payment_url = $body_array['redirect_url'];
                error_log('FuratPay API: Found PayTabs URL in redirect_url');
            } elseif (isset($body_array['data']['redirect_uri'])) {
                $payment_url = $body_array['data']['redirect_uri'];
                error_log('FuratPay API: Found PayTabs URL in data.redirect_uri');
            } elseif (isset($body_array['data']['url'])) {
                $payment_url = $body_array['data']['url'];
                error_log('FuratPay API: Found PayTabs URL in data.url');
            }
            
            // Store PayTabs transaction data in a transient
            if (isset($body_array['tran_ref'])) {
                $paytabs_data = [
                    'tran_ref' => $body_array['tran_ref'],
                    'cart_id' => $body_array['cart_id'],
                    'cart_amount' => $body_array['cart_amount'],
                    'cart_currency' => $body_array['cart_currency']
                ];
                set_transient('furatpay_paytabs_data_' . $invoice_id, $paytabs_data, 24 * HOUR_IN_SECONDS);
                error_log('FuratPay API: Stored PayTabs data in transient for invoice ' . $invoice_id);
            }
        }
        // Handle FIB response
        elseif (isset($body_array['personalAppLink'])) {
            // For FIB, use the personal app link as the payment URL
            $payment_url = $body_array['personalAppLink'];
            error_log('FuratPay API: Using FIB personal app link as payment URL');
            
            // Store additional FIB data in a separate meta field
            if (isset($body_array['qrCode'])) {
                $fib_data = [
                    'qrCode' => $body_array['qrCode'],
                    'readableCode' => $body_array['readableCode'],
                    'personalAppLink' => $body_array['personalAppLink'],
                    'businessAppLink' => $body_array['businessAppLink'],
                    'corporateAppLink' => $body_array['corporateAppLink'],
                    'validUntil' => $body_array['validUntil']
                ];
                
                // Store FIB data in a transient with the invoice ID as key
                set_transient('furatpay_fib_data_' . $invoice_id, $fib_data, 24 * HOUR_IN_SECONDS);
                error_log('FuratPay API: Stored FIB data in transient for invoice ' . $invoice_id);
            }
        }
        // Handle generic response
        else {
            if (isset($body_array['data']['redirect_uri'])) {
                $payment_url = $body_array['data']['redirect_uri'];
                error_log('FuratPay API: Found URL in data.redirect_uri');
            } elseif (isset($body_array['url'])) {
                $payment_url = $body_array['url'];
                error_log('FuratPay API: Found URL in url');
            } elseif (isset($body_array['data']['url'])) {
                $payment_url = $body_array['data']['url'];
                error_log('FuratPay API: Found URL in data.url');
            }
        }

        if (!$payment_url) {
            error_log('FuratPay API Error: No payment URL found in response - ' . print_r($body_array, true));
            throw new Exception(__('Invalid payment response - No payment URL found', 'woo_furatpay'));
        }

        error_log('FuratPay API: Payment URL generated: ' . $payment_url);
        return $payment_url;
    }

    public static function check_payment_status($api_url, $token, $invoice_id)
    {
        error_log('FuratPay API: Checking payment status for invoice ' . $invoice_id);
        
        $api_url = rtrim($api_url, '/');
        $endpoint = $api_url . '/invoice/' . $invoice_id;
        
        error_log('FuratPay API: Status check endpoint: ' . $endpoint);

        $response = wp_remote_get(
            $endpoint,
            [
                'headers' => [
                    'x-api-key' => $token,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 15,
                'sslverify' => false
            ]
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('FuratPay API Error: Failed to check payment status - ' . $error_message);
            throw new Exception(__('Failed to check payment status', 'woo_furatpay'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        error_log('FuratPay API: Status check response code: ' . $response_code);
        error_log('FuratPay API: Status check response body: ' . $body);

        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';
            error_log('FuratPay API Error: Status check failed - ' . $error_message);
            throw new Exception(__('Failed to check payment status: ', 'woo_furatpay') . $error_message);
        }

        $body_array = json_decode($body, true);
        if (!isset($body_array['status'])) {
            error_log('FuratPay API Error: Invalid status response - ' . print_r($body_array, true));
            throw new Exception(__('Invalid status response', 'woo_furatpay'));
        }

        // Map FuratPay status to our status
        $status = strtolower($body_array['status']);
        error_log('FuratPay API: Payment status: ' . $status);

        if ($status === 'paid' || $status === 'completed') {
            return 'paid';
        } else if ($status === 'failed' || $status === 'canceled' || $status === 'expired') {
            return 'failed';
        } else {
            return 'pending';
        }
    }

    protected static function get_customer_id(WC_Order $order)
    {
        // Helper function to generate UUID v4
        $generate_uuid_v4 = function() {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // Set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // Set bits 6-7 to 10

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        };

        $customer_id = $order->get_customer_id();
        $email = $order->get_billing_email();
        $order_id = $order->get_id();

        // Generate a deterministic UUID based on customer data
        $unique_string = $customer_id ? "wc_{$customer_id}" : "guest_{$email}_{$order_id}";
        $hash = md5($unique_string);
        
        // Format the hash as a UUID v4
        return sprintf('%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            '4' . substr($hash, 13, 3),  // Version 4
            dechex(hexdec(substr($hash, 16, 4)) & 0x3fff | 0x8000), // RFC 4122 variant
            substr($hash, 20, 12)
        );
    }

    protected static function get_currency_id($currency_code)
    {
        // Map WooCommerce currency codes to FuratPay currency IDs
        $currency_map = [
            'IQD' => 3, // Iraqi Dinar
            'USD' => 1, // US Dollar
            // Add more currency mappings as needed
        ];

        return $currency_map[$currency_code] ?? 3; // Default to IQD (3) if not found
    }

    protected static function get_order_items(WC_Order $order)
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            $quantity = $item->get_quantity();
            $unit_price = $item->get_total() / $quantity;
            
            $items[] = [
                'item' => $item->get_name(),
                'description' => $item->get_meta_data() ? wp_json_encode($item->get_meta_data()) : ' ',
                'quantity' => number_format($quantity, 4, '.', ''),
                'rate' => number_format($unit_price, 4, '.', ''),
                'discount' => '0.0000',
                'meta_data' => []
            ];
        }
        return $items;
    }
}