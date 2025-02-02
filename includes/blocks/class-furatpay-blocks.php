<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class FuratPay_Blocks extends AbstractPaymentMethodType {
    private $gateway;
    
    protected $name = 'furatpay';

    public function initialize() {
        $this->gateway = new FuratPay_Gateway();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'furatpay-blocks',
            FURATPAY_PLUGIN_URL . 'assets/js/blocks.js',
            array('wc-blocks-registry'),
            true
        );
        return ['furatpay-blocks'];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
        ];
    }
}

add_action('woocommerce_blocks_payment_method_type_registration', function($registry) {
    $registry->register(new FuratPay_Blocks());
});