<?php
/**
 * Plugin Name: FuratPay for WooCommerce
 * Plugin URI: https://furatpay.com/plugins/woocommerce
 * Description: Integrates all popular Iraqi payment gateways with WooCommerce. Currently supports ZainCash, FIB, FastPay, and more.
 * Version: 1.0.0
 * Author: FuratPay
 * Author URI: https://furatpay.com/
 * Text Domain: woo_furatpay
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit; // Prevent direct access

// Define constants
define('FURATPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FURATPAY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'furatpay_activate');
register_deactivation_hook(__FILE__, 'furatpay_deactivate');
register_activation_hook(__FILE__, 'furatpay_activation_check');

// add_filter('woocommerce_is_checkout_block_editor_enabled', '__return_false');
// add_filter('woocommerce_is_checkout_block_enabled', '__return_false');

function furatpay_activate() {
    error_log('FuratPay: Plugin activated');
}

function furatpay_deactivate() {
    error_log('FuratPay: Plugin deactivated');
}

function furatpay_activation_check() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('FuratPay requires WooCommerce to be installed and active.', 'woo_furatpay'),
            __('Plugin dependency error', 'woo_furatpay'),
            ['back_link' => true]
        );
    }
}

// Initialize the gateway
add_action('plugins_loaded', 'furatpay_init', 0);
function furatpay_init() {
    error_log('FuratPay: Init started');
    
    // Check if WooCommerce is active
    if (!class_exists('WC_Payment_Gateway')) {
        error_log('FuratPay: WooCommerce not active');
        return;
    }

    error_log('FuratPay: Loading gateway files');
    
    // Load required files
    require_once FURATPAY_PLUGIN_PATH . 'includes/class-furatpay-api-handler.php';
    require_once FURATPAY_PLUGIN_PATH . 'includes/class-furatpay-ipn-handler.php';
    require_once FURATPAY_PLUGIN_PATH . 'includes/class-furatpay-gateway.php';

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'furatpay_add_gateway');
    
    // Load plugin textdomain
    load_plugin_textdomain('woo_furatpay', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    error_log('FuratPay: Init completed');
}

function furatpay_add_gateway($gateways) {
    error_log('FuratPay: Adding gateway - Before: ' . print_r($gateways, true));
    $gateways[] = 'FuratPay_Gateway';
    error_log('FuratPay: Adding gateway - After: ' . print_r($gateways, true));
    return $gateways;
}

// Add admin notice checks
add_action('admin_notices', 'furatpay_admin_notices');
function furatpay_admin_notices() {
    $settings = get_option('woocommerce_furatpay_settings', []);
    error_log('FuratPay: Checking admin notices. Gateway enabled: ' . ($settings['enabled'] ?? 'no'));
    
    if (empty($settings['enabled']) || 'yes' !== $settings['enabled']) {
        return;
    }
    
    $errors = [];
    
    if (empty($settings['api_url'])) {
        $errors[] = __('FuratPay API URL is required.', 'woo_furatpay');
    }
    
    if (empty($settings['api_key'])) {
        $errors[] = __('FuratPay JWT Token is required.', 'woo_furatpay');
    }
    
    if (!empty($errors)) {
        error_log('FuratPay: Configuration errors found: ' . implode(', ', $errors));
        echo '<div class="error notice">';
        echo '<p><strong>' . __('FuratPay is misconfigured:', 'woo_furatpay') . '</strong></p>';
        echo '<ul style="list-style: inside; padding-left: 15px;">';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul>';
        echo '<p>' . sprintf(
            __('Please configure in %sWooCommerce Settings%s.', 'woo_furatpay'),
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=furatpay') . '">',
            '</a>'
        ) . '</p>';
        echo '</div>';
    }
}

// Add debug action for checkout page
add_action('woocommerce_before_checkout_form', 'furatpay_debug_checkout');
function furatpay_debug_checkout() {
    error_log('################ FuratPay: Checkout page loaded');
    $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
    error_log('################# Available payment gateways at checkout: ' . print_r($available_gateways, true));
}

add_action('init', function() {
    if (isset($_GET['test_furatpay'])) {
        try {
            $api = new FuratPay_API_Handler();
            $services = $api::get_payment_services('https://pepu-furat-api-test.ylxkwt.easypanel.host', 'b4482e8d-8710-4c22-87a1-51f8a5e2aed1');
            echo '<pre>'; print_r($services); echo '</pre>';
            die();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            die();
        }
    }
});

add_action('template_redirect', function() {
    if (is_checkout() && !is_wc_endpoint_url()) {
        error_log('Checkout page loaded');
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
    error_log('################# Available payment gateways at checkout: ' . print_r($available_gateways, true));
        //error_log('Available gateways: ' . print_r(WC()->payment_gateways->get_available_payment_gateways(), true));
    }
});

add_action('woocommerce_review_order_before_payment', function() {
    error_log('##### Before payment methods rendering #####');
});

add_action('woocommerce_review_order_after_payment', function() {
    error_log('##### After payment methods rendering #####');
});

// Add this to see if payment method HTML is being generated
add_action('woocommerce_payment_methods_list_item', function($item, $gateway) {
    error_log('Payment method being rendered: ' . $gateway->id);
    return $item;
}, 10, 2);