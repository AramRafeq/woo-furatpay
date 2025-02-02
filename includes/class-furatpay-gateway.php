<?php
class FuratPay_Gateway extends WC_Payment_Gateway
{
    /**
     * @var string
     */
    protected $api_url;

    /**
     * @var string
     */
    protected $api_key;

    public function __construct()
    {
        error_log('#### FuratPay: Gateway constructor called ####');

        // Basic gateway setup
        $this->id = 'furatpay';
        $this->has_fields = true;
        $this->method_title = __('FuratPay', 'woo_furatpay');
        $this->method_description = __('Accept payments through FuratPay payment gateway', 'woo_furatpay');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings values
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_url = $this->get_option('api_url');
        $this->api_key = $this->get_option('api_key');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));

        add_action('woocommerce_init', function () {
            new FuratPay_IPN_Handler($this->api_url, $this->api_key);
        });

        // add_action('woocommerce_blocks_loaded', function() {
        //     require_once FURATPAY_PLUGIN_PATH . 'includes/blocks/class-furatpay-blocks.php';
        // });
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woo_furatpay'),
                'type' => 'checkbox',
                'label' => __('Enable FuratPay', 'woo_furatpay'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woo_furatpay'),
                'type' => 'text',
                'description' => __('Payment method title', 'woo_furatpay'),
                'default' => __('FuratPay', 'woo_furatpay'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'woo_furatpay'),
                'type' => 'textarea',
                'description' => __('Payment method description', 'woo_furatpay'),
                'default' => __('Pay via FuratPay', 'woo_furatpay'),
                'desc_tip' => true
            ),
            'api_url' => array(
                'title' => __('API URL', 'woo_furatpay'),
                'type' => 'text',
                'description' => __('FuratPay API endpoint URL', 'woo_furatpay'),
                'default' => '',
                'placeholder' => 'https://api.furatpay.com/v1'
            ),
            'api_key' => array(
                'title' => __('FuratPay API Key', 'woo_furatpay'),
                'type' => 'password',
                'description' => __('Your FuratPay API key', 'woo_furatpay'),
                'default' => ''
            )
        );
    }

    public function process_admin_options()
    {
        $result = parent::process_admin_options();

        // Get settings after save
        $this->init_settings();
        $saved_settings = $this->settings;

        // Make sure we have the latest values
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_url = $this->get_option('api_url');
        $this->api_key = $this->get_option('api_key');

        return $result;
    }

    public function payment_fields()
    {
        error_log('################ FuratPay payment_fields called ################');
        echo '<div class="furatpay-debug">Payment fields test</div>';
        return;// TEMPORARY
        
        if ($description = $this->get_description()) {
            echo wpautop(wptexturize($description));
        }

        if (empty($this->api_url) || empty($this->api_key)) {
            echo '<div class="woocommerce-error">' . 
                 __('Payment method is not properly configured.', 'woo_furatpay') . 
                 '</div>';
            return;
        }

        try {
            $payment_services = FuratPay_API_Handler::get_payment_services($this->api_url, $this->api_key);
            include FURATPAY_PLUGIN_PATH . 'templates/payment-methods.php';
        } catch (Exception $e) {
            echo '<div class="woocommerce-error">' . 
                 __('Payment method unavailable. Please try again later.', 'woo_furatpay') . 
                 '</div>';
        }
    }

    public function enqueue_checkout_scripts()
    {
        if (is_checkout()) {
            wp_enqueue_style(
                'furatpay-checkout',
                FURATPAY_PLUGIN_URL . 'assets/css/checkout.css'
            );

            wp_enqueue_script(
                'furatpay-debug',
                FURATPAY_PLUGIN_URL . 'assets/js/debug.js',
                ['jquery'],
                time()
            );
        }
    }

    /**
     * Check if this gateway is available for use
     *
     * @return bool
     */
    public function is_available()
    {
        $parent_available = parent::is_available();
        error_log('FuratPay parent is_available: ' . ($parent_available ? 'true' : 'false'));
        
        $has_api_url = !empty($this->api_url);
        error_log('FuratPay has API URL: ' . ($has_api_url ? 'true' : 'false'));
        
        $has_api_key = !empty($this->api_key);
        error_log('FuratPay has API key: ' . ($has_api_key ? 'true' : 'false'));
        
        $is_available = $parent_available && $has_api_url && $has_api_key;
        error_log('FuratPay final is_available: ' . ($is_available ? 'true' : 'false'));
        
        return $is_available;
    }

    /**
     * Process the payment
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        try {
            // Get selected payment service
            $selected_service = isset($_POST['furatpay_service']) ? sanitize_text_field($_POST['furatpay_service']) : '';

            if (empty($selected_service)) {
                throw new Exception(__('Please select a payment method', 'woo_furatpay'));
            }

            // Create invoice
            $invoice_id = FuratPay_API_Handler::create_invoice($this->api_url, $this->api_key, $order);

            // Create payment and get redirect URL
            $payment_url = FuratPay_API_Handler::create_payment(
                $this->api_url,
                $this->api_key,
                $invoice_id,
                $selected_service
            );

            // Update order status
            $order->update_status('pending', __('Awaiting FuratPay payment', 'woo_furatpay'));

            // Return success with redirect
            return array(
                'result' => 'success',
                'redirect' => $payment_url
            );

        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return array(
                'result' => 'failure',
                'messages' => $e->getMessage()
            );
        }
    }

    public function validate_fields()
    {
        if (empty($_POST['furatpay_service'])) {
            wc_add_notice(__('Please select a payment method', 'woo_furatpay'), 'error');
            return false;
        }
        return true;
    }
}