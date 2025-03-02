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
        // Basic gateway setup
        $this->id = 'furatpay';
        $this->has_fields = true;
        $this->method_title = __('FuratPay', 'woo_furatpay');
        $this->method_description = __('Accept payments through FuratPay payment gateway', 'woo_furatpay');
        $this->supports = array('products');

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
        
        // Add payment form display hooks
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_review_order_before_payment', array($this, 'payment_fields_before'));
        add_action('woocommerce_review_order_after_payment', array($this, 'payment_fields_after'));
        
        add_action('init', function() {
            if (class_exists('FuratPay_IPN_Handler')) {
                new FuratPay_IPN_Handler($this->api_url, $this->api_key);
            }
        });

        // Enable blocks integration
        add_action('woocommerce_blocks_loaded', function() {
            require_once FURATPAY_PLUGIN_PATH . 'includes/blocks/class-furatpay-blocks.php';
        });

        // Add AJAX endpoints
        add_action('wp_ajax_furatpay_get_payment_services', array($this, 'ajax_get_payment_services'));
        add_action('wp_ajax_nopriv_furatpay_get_payment_services', array($this, 'ajax_get_payment_services'));
        add_action('wp_ajax_furatpay_initiate_payment', array($this, 'ajax_initiate_payment'));
        add_action('wp_ajax_nopriv_furatpay_initiate_payment', array($this, 'ajax_initiate_payment'));
        add_action('wp_ajax_furatpay_check_payment_status', array($this, 'check_payment_status'));
        add_action('wp_ajax_nopriv_furatpay_check_payment_status', array($this, 'check_payment_status'));

        // Debug hooks
        add_action('woocommerce_checkout_before_customer_details', array($this, 'debug_before_customer_details'));
        add_action('woocommerce_checkout_after_customer_details', array($this, 'debug_after_customer_details'));
        add_action('woocommerce_checkout_before_order_review', array($this, 'debug_before_order_review'));
        add_action('woocommerce_checkout_after_order_review', array($this, 'debug_after_order_review'));
        add_action('woocommerce_payment_methods_list', array($this, 'debug_payment_methods_list'), 10, 2);

        // Add API endpoint
        add_action('woocommerce_api_furatpay_pay', array($this, 'handle_payment_page'));
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
            ),
            'customer_id' => array(
                'title' => __('FuratPay Customer ID', 'woo_furatpay'),
                'type' => 'text',
                'description' => __('Your FuratPay customer ID', 'woo_furatpay'),
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

    public function debug_before_customer_details() {
        error_log('FuratPay: Before customer details section');
    }

    public function debug_after_customer_details() {
        error_log('FuratPay: After customer details section');
    }

    public function debug_before_order_review() {
        error_log('FuratPay: Before order review section');
    }

    public function debug_after_order_review() {
        error_log('FuratPay: After order review section');
    }

    public function debug_payment_methods_list($list, $order_id = null) {
        error_log('FuratPay: Payment methods list being generated');
        $gateway_ids = array_keys($list);
        error_log('Available gateway IDs: ' . implode(', ', $gateway_ids));
        return $list;
    }

    public function payment_fields_before() {
        echo '<div id="furatpay-payment-form-wrapper">';
    }

    public function payment_fields_after() {
        echo '</div>';
    }

    public function payment_fields()
    {
        try {
            echo '<div class="furatpay-payment-form">';
            
            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }

            if (empty($this->api_url) || empty($this->api_key)) {
                throw new Exception(__('Payment method is not properly configured.', 'woo_furatpay'));
            }

            $payment_services = FuratPay_API_Handler::get_payment_services($this->api_url, $this->api_key);
            
            if (empty($payment_services)) {
                throw new Exception(__('No payment methods available.', 'woo_furatpay'));
            }

            // Filter active services
            $active_services = $payment_services;

            if (empty($active_services)) {
                throw new Exception(__('No active payment methods available.', 'woo_furatpay'));
            }

            echo '<div class="furatpay-services-wrapper">';
            echo '<h4>' . __('Select a payment service:', 'woo_furatpay') . '</h4>';
            echo '<ul class="furatpay-method-list">';
            
            foreach ($active_services as $service) {
                echo '<li class="furatpay-method-item">';
                echo '<label>';
                echo '<input 
                    type="radio" 
                    name="furatpay_service" 
                    value="' . esc_attr($service['id']) . '"
                    ' . checked(isset($_POST['furatpay_service']) && $_POST['furatpay_service'] == $service['id'], true, false) . '
                    required="required"
                    class="furatpay-service-radio"
                />';
                
                if (!empty($service['logo'])) {
                    echo '<img 
                        src="' . esc_url($service['logo']) . '" 
                        alt="' . esc_attr($service['name']) . '"
                        class="furatpay-method-logo"
                    />';
                }
                
                echo '<span class="furatpay-method-name">' . esc_html($service['name']) . '</span>';
                echo '</label>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
            
            ?>
            <script type="text/javascript">
            jQuery(function($) {
                $('.furatpay-service-radio').on('change', function() {
                    $('body').trigger('update_checkout');
                });
            });
            </script>
            <?php
            
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="woocommerce-error">' . esc_html($e->getMessage()) . '</div>';
        }
    }

    public function enqueue_checkout_scripts()
    {
        error_log('FuratPay: Attempting to enqueue checkout scripts');
        
        if (!is_checkout()) {
            error_log('FuratPay: Not checkout page, skipping script enqueue');
            return;
        }

        error_log('FuratPay: Enqueuing checkout scripts for checkout page');

        // Force cache bust with current timestamp
        $version = time();

        // Enqueue jQuery first
        wp_enqueue_script('jquery');

        // Enqueue checkout script with explicit jQuery dependency
        $checkout_script_url = FURATPAY_PLUGIN_URL . 'assets/js/checkout.js';
        error_log('FuratPay: Loading checkout.js from: ' . $checkout_script_url);
        
        wp_enqueue_script(
            'furatpay-checkout',
            $checkout_script_url,
            array('jquery', 'wc-checkout'),
            $version,
            true
        );

        // Common data for both classic and block checkout
        $script_data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('furatpay-nonce'),
            'title' => $this->title,
            'description' => $this->description,
            'icon' => apply_filters('woocommerce_furatpay_icon', ''),
            'supports' => $this->supports,
            'debug' => true,
            'i18n' => array(
                'processing' => __('Processing payment, please wait...', 'woo_furatpay'),
                'redirect' => __('Redirecting to payment service...', 'woo_furatpay'),
                'waiting' => __('Waiting for Payment', 'woo_furatpay'),
                'complete_payment' => __('Payment window has been opened in a new tab. Please complete your payment there. This page will update automatically once payment is confirmed.', 'woo_furatpay'),
                'selectService' => __('Please select a payment service.', 'woo_furatpay'),
                'noServices' => __('No payment services available.', 'woo_furatpay'),
                'popupBlocked' => __('Popup was blocked. Please click the button below to open the payment window:', 'woo_furatpay'),
                'openPayment' => __('Open Payment Window', 'woo_furatpay')
            )
        );

        error_log('FuratPay: Localizing script data: ' . print_r($script_data, true));
        wp_localize_script('furatpay-checkout', 'furatpayData', $script_data);

        // Only load blocks script if using block checkout
        if (function_exists('wc_current_theme_is_fse_theme') && wc_current_theme_is_fse_theme()) {
            error_log('FuratPay: Loading blocks script for FSE theme');
            wp_enqueue_script(
                'furatpay-blocks',
                FURATPAY_PLUGIN_URL . 'build/blocks.js',
                array('wc-blocks-registry', 'wp-element', 'wp-i18n'),
                $version,
                true
            );
            wp_localize_script('furatpay-blocks', 'furatpayData', $script_data);
        }

        error_log('FuratPay: Loading checkout CSS');
        wp_enqueue_style(
            'furatpay-checkout',
            FURATPAY_PLUGIN_URL . 'assets/css/checkout.css',
            array(),
            $version
        );

        error_log('FuratPay: Script enqueuing completed');
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
        try {
            error_log('FuratPay: Processing payment for order ' . $order_id);
            
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Order not found', 'woo_furatpay'));
            }

            // Get selected payment service
            $payment_service_id = isset($_POST['furatpay_service']) ? intval($_POST['furatpay_service']) : 0;
            
            // For blocks checkout, check the payment data
            if (!$payment_service_id && isset($_POST['payment_method_data'])) {
                $payment_data = json_decode(stripslashes($_POST['payment_method_data']), true);
                if (isset($payment_data['furatpay_service'])) {
                    $payment_service_id = intval($payment_data['furatpay_service']);
                }
            }

            if (!$payment_service_id) {
                throw new Exception(__('Please select a payment service', 'woo_furatpay'));
            }

            // Get customer ID from settings
            $customer_id = $this->get_option('customer_id');
            if (empty($customer_id)) {
                throw new Exception(__('FuratPay customer ID is not configured', 'woo_furatpay'));
            }

            // Create invoice
            $invoice_id = FuratPay_API_Handler::create_invoice(
                $this->api_url,
                $this->api_key,
                $order,
                $customer_id
            );

            // Create payment URL
            $payment_url = FuratPay_API_Handler::create_payment(
                $this->api_url,
                $this->api_key,
                $invoice_id,
                $payment_service_id
            );

            // Store payment details in order meta
            $order->update_meta_data('_furatpay_payment_url', $payment_url);
            $order->update_meta_data('_furatpay_payment_service_id', $payment_service_id);
            $order->update_meta_data('_furatpay_invoice_id', $invoice_id);
            
            // Update order status to pending
            $order->update_status('pending', __('Awaiting FuratPay payment confirmation', 'woo_furatpay'));
            $order->save();

            // Empty cart
            WC()->cart->empty_cart();

            // Return success with payment URL and status page URL
            return array(
                'result' => 'success',
                'redirect' => add_query_arg(
                    array(
                        'order_id' => $order->get_id(),
                        'key' => $order->get_order_key(),
                    ),
                    WC()->api_request_url('furatpay_pay')
                ),
                'messages' => '<script type="text/javascript">
                    (function($) {
                        var paymentWindow = window.open("' . esc_js($payment_url) . '", "FuratPayment");
                        if (!paymentWindow || paymentWindow.closed) {
                            // If popup is blocked, we\'ll handle it on the status page
                            return;
                        }
                        paymentWindow.focus();
                    })(jQuery);
                </script>'
            );

        } catch (Exception $e) {
            error_log('FuratPay Error: ' . $e->getMessage());
            wc_add_notice($e->getMessage(), 'error');
            return array(
                'result' => 'failure',
                'messages' => $e->getMessage()
            );
        }
    }

    /**
     * Handle the payment page
     */
    public function handle_payment_page() {
        if (!isset($_GET['order_id']) || !isset($_GET['key'])) {
            wp_die(__('Invalid payment request', 'woo_furatpay'));
        }

        $order_id = wc_clean($_GET['order_id']);
        $order_key = wc_clean($_GET['key']);
        $order = wc_get_order($order_id);

        if (!$order || $order->get_order_key() !== $order_key) {
            wp_die(__('Invalid order', 'woo_furatpay'));
        }

        $payment_url = $order->get_meta('_furatpay_payment_url');
        if (!$payment_url) {
            wp_die(__('Payment URL not found', 'woo_furatpay'));
        }

        // Enqueue required scripts
        wp_enqueue_script('jquery');
        wp_enqueue_script('wc-checkout');
        
        // Add our script data
        $script_data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('furatpay-nonce'),
            'i18n' => array(
                'popupBlocked' => __('Popup was blocked. Please try again.', 'woo_furatpay'),
                'paymentFailed' => __('Payment failed. Please try again.', 'woo_furatpay')
            )
        );
        wp_localize_script('jquery', 'furatpayData', $script_data);

        // Display payment page template
        wc_get_template(
            'payment.php',
            array(
                'order' => $order,
                'payment_url' => $payment_url,
                'return_url' => $this->get_return_url($order),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('furatpay-nonce')
            ),
            '',
            FURATPAY_PLUGIN_PATH . 'templates/'
        );
        exit;
    }

    public function check_payment_status()
    {
        try {
            // Verify nonce
            check_ajax_referer('furatpay-nonce', 'nonce');

            // Get and validate order_id
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            if (!$order_id) {
                throw new Exception(__('Invalid order ID', 'woo_furatpay'));
            }

            // Get order
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Order not found', 'woo_furatpay'));
            }

            // Get invoice ID
            $invoice_id = $order->get_meta('_furatpay_invoice_id');
            if (!$invoice_id) {
                throw new Exception(__('Invoice ID not found', 'woo_furatpay'));
            }

            // Check payment status with FuratPay API
            $status = FuratPay_API_Handler::check_payment_status(
                $this->api_url,
                $this->api_key,
                $invoice_id
            );

            error_log('FuratPay: Payment status check for order ' . $order_id . ' returned: ' . $status);

            switch ($status) {
                case 'paid':
                    $order->payment_complete();
                    wp_send_json_success([
                        'status' => 'completed',
                        'redirect_url' => $order->get_checkout_order_received_url()
                    ]);
                    break;

                case 'failed':
                    $order->update_status('failed', __('Payment failed or was declined', 'woo_furatpay'));
                    wp_send_json_success([
                        'status' => 'failed',
                        'message' => __('Payment failed or was declined. Please try again.', 'woo_furatpay')
                    ]);
                    break;

                default:
                    wp_send_json_success([
                        'status' => 'pending'
                    ]);
                    break;
            }

        } catch (Exception $e) {
            error_log('FuratPay Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }

        // Ensure we always exit after sending JSON response
        wp_die();
    }

    public function validate_fields()
    {
        error_log('FuratPay: Validating fields');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('REQUEST data: ' . print_r($_REQUEST, true));
        
        // For block checkout, validation happens in process_payment
        if (function_exists('wc_current_theme_is_fse_theme') && wc_current_theme_is_fse_theme()) {
            error_log('FuratPay: Block checkout detected, skipping validation');
            return true;
        }
        
        if (!isset($_POST['furatpay_service']) || empty($_POST['furatpay_service'])) {
            error_log('FuratPay: No service selected');
            wc_add_notice(__('Please select a payment service.', 'woo_furatpay'), 'error');
            return false;
        }

        try {
            $payment_services = FuratPay_API_Handler::get_payment_services($this->api_url, $this->api_key);
            $service_ids = array_column($payment_services, 'id');
            
            if (!in_array($_POST['furatpay_service'], $service_ids)) {
                error_log('FuratPay: Invalid service selected');
                wc_add_notice(__('Invalid payment service selected.', 'woo_furatpay'), 'error');
                return false;
            }
        } catch (Exception $e) {
            error_log('FuratPay Validation Error: ' . $e->getMessage());
            wc_add_notice(__('Unable to validate payment service. Please try again.', 'woo_furatpay'), 'error');
            return false;
        }

        error_log('FuratPay: Fields validated successfully');
        return true;
    }

    /**
     * AJAX endpoint to get payment services
     */
    public function ajax_get_payment_services() {
        error_log('FuratPay: AJAX get payment services called');
        check_ajax_referer('furatpay-nonce', 'nonce');

        try {
            $services = FuratPay_API_Handler::get_payment_services($this->api_url, $this->api_key);
            wp_send_json_success($services);
        } catch (Exception $e) {
            error_log('FuratPay AJAX Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    public function ajax_initiate_payment() {
        check_ajax_referer('furatpay-nonce', 'nonce');

        try {
            // Get form data
            parse_str($_POST['form_data'], $form_data);
            
            // Create temporary order data
            $order_data = array(
                'total' => WC()->cart->get_total('edit'),
                'currency' => get_woocommerce_currency(),
            );
            
            // Create invoice
            $invoice_id = FuratPay_API_Handler::create_invoice(
                $this->api_url,
                $this->api_key,
                $order_data,
                $this->get_option('customer_id')
            );

            // Get payment URL
            $payment_url = FuratPay_API_Handler::create_payment(
                $this->api_url,
                $this->api_key,
                $invoice_id,
                intval($_POST['payment_service'])
            );

            // Store invoice ID in session for later use when webhook arrives
            WC()->session->set('furatpay_pending_invoice', $invoice_id);

            wp_send_json_success(array(
                'payment_url' => $payment_url
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
}