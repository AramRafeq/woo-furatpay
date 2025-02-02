<?php
class FuratPay_API_Handler
{

    public static function get_payment_services($api_url, $token)
    {
        if (empty($api_url) || empty($token)) {
            throw new Exception(__('Payment gateway is not properly configured', 'woo_furatpay'));
        }

        $api_url = trailingslashit($api_url);

        $transient_key = 'furatpay_payment_services_' . md5($token);
        $services = get_transient($transient_key);

        if (false === $services) {
            $response = wp_remote_get(
                $api_url . '/payment_service/list?limit=10&offset=0',
                [
                    'headers' => [
                        'x-api-key' => $token,
                        'Content-Type' => 'application/json'
                    ],
                    'timeout' => 15
                ]
            );

            if (is_wp_error($response)) {
                throw new Exception(__('Failed to retrieve payment services', 'woo_furatpay'));
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (200 !== wp_remote_retrieve_response_code($response) || !isset($body['data'])) {
                $response_body = wp_remote_retrieve_body($response);
                error_log('FuratPay: Payment services response: ' . $response_body);

                throw new Exception(__('Invalid payment services response' . $response_body , 'woo_furatpay'));
            }

            $services = $body['data'];
            set_transient($transient_key, $services, 60 * 60);
        }

        return $services;
    }

    public static function create_invoice($api_url, $token, WC_Order $order)
    {
        if (empty($api_url) || empty($token)) {
            throw new Exception(__('Payment gateway is not properly configured', 'woo_furatpay'));
        }

        $invoice_data = [
            'order_number' => $order->get_order_number(),
            'customer_id' => self::get_customer_id($order),
            'currency_id' => self::get_currency_id($order->get_currency()),
            'total' => $order->get_total(),
            'items' => self::get_order_items($order),
        ];

        $response = wp_remote_post(
            $api_url . '/invoice',
            [
                'headers' => [
                    'x-api-key' => $token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($invoice_data),
                'timeout' => 15
            ]
        );

        if (is_wp_error($response)) {
            throw new Exception(__('Invoice creation failed', 'woo_furatpay'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['id'])) {
            throw new Exception(__('Invalid invoice response', 'woo_furatpay'));
        }

        $order->update_meta_data('_furatpay_invoice_id', $body['id']);
        $order->save();

        return $body['id'];
    }

    public static function create_payment($api_url, $token, $invoice_id, $service_id)
    {
        $response = wp_remote_post(
            $api_url . '/public/invoice/pay',
            [
                'headers' => [
                    'x-api-key' => $token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'payment_service_id' => $service_id,
                    'invoice_id' => $invoice_id
                ]),
                'timeout' => 15
            ]
        );

        if (is_wp_error($response)) {
            throw new Exception(__('Payment initiation failed', 'woo_furatpay'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['payment_url'])) {
            throw new Exception(__('Invalid payment response', 'woo_furatpay'));
        }

        return $body['payment_url'];
    }

    // Helper methods for data mapping...
}