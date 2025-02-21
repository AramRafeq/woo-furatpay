<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class FuratPay_Blocks extends AbstractPaymentMethodType {
    private $gateway;
    
    protected $name = 'furatpay';

    public function initialize() {
        $this->gateway = new FuratPay_Gateway();
        error_log('FuratPay Blocks: Initialized');
    }

    public function is_active() {
        $is_active = $this->gateway->is_available();
        error_log('FuratPay Blocks: is_active check returned: ' . ($is_active ? 'true' : 'false'));
        return $is_active;
    }

    public function get_payment_method_script_handles() {
        $asset_path = FURATPAY_PLUGIN_PATH . 'build/blocks.asset.php';
        $version = file_exists($asset_path) ? include($asset_path) : ['version' => time()];
        
        wp_register_script(
            'furatpay-blocks',
            FURATPAY_PLUGIN_URL . 'assets/js/blocks.js',
            ['wc-blocks-registry', 'wp-element', 'wp-components', 'wp-html-entities', 'wp-i18n'],
            $version['version'],
            true
        );

        wp_localize_script('furatpay-blocks', 'furatpayData', [
            'apiUrl' => $this->gateway->get_option('api_url'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('furatpay-nonce')
        ]);

        error_log('FuratPay Blocks: Registered payment method scripts');
        return ['furatpay-blocks'];
    }

    public function get_payment_method_data() {
        $data = [
            'title' => $this->gateway->get_option('title'),
            'description' => $this->gateway->get_option('description'),
            'supports' => $this->gateway->supports,
            'showSavedCards' => false,
            'canMakePayment' => true,
            'icons' => [
                'id' => $this->gateway->id,
                'src' => FURATPAY_PLUGIN_URL . 'assets/images/icon.png',
                'alt' => 'FuratPay'
            ]
        ];

        error_log('FuratPay Blocks: Returning payment method data: ' . print_r($data, true));
        return $data;
    }
}

add_action('woocommerce_blocks_payment_method_type_registration', function($registry) {
    error_log('FuratPay Blocks: Registering payment method type');
    $registry->register(new FuratPay_Blocks());
});