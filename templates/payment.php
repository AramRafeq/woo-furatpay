<?php
/**
 * FuratPay payment page template
 */

defined('ABSPATH') || exit;

// Get header
get_header('shop');

?>
<div class="furatpay-payment-page">
    <div class="furatpay-payment-container">
        <h2><?php esc_html_e('Complete Your Payment', 'woo_furatpay'); ?></h2>
        
        <div class="furatpay-payment-details">
            <p><?php esc_html_e('Your order has been created and is waiting for payment.', 'woo_furatpay'); ?></p>
            
            <?php
            // Get FIB data if available
            $invoice_id = $order->get_meta('_furatpay_invoice_id');
            $fib_data = get_transient('furatpay_fib_data_' . $invoice_id);
            $payment_service = $order->get_meta('_furatpay_service');
            
            if ($fib_data): ?>
                <div class="furatpay-fib-container">
                    <div class="furatpay-qr-section">
                        <h3><?php esc_html_e('Scan QR Code', 'woo_furatpay'); ?></h3>
                        <img src="<?php echo esc_attr($fib_data['qrCode']); ?>" alt="QR Code" class="furatpay-qr-code">
                        <p class="furatpay-readable-code"><?php echo esc_html($fib_data['readableCode']); ?></p>
                    </div>
                    
                    <div class="furatpay-app-links">
                        <h3><?php esc_html_e('Open in FIB App', 'woo_furatpay'); ?></h3>
                        <div class="furatpay-app-buttons">
                            <a href="<?php echo esc_url($fib_data['personalAppLink']); ?>" class="button alt furatpay-app-button" target="_blank">
                                <?php esc_html_e('Personal Banking', 'woo_furatpay'); ?>
                            </a>
                            <a href="<?php echo esc_url($fib_data['businessAppLink']); ?>" class="button alt furatpay-app-button" target="_blank">
                                <?php esc_html_e('Business Banking', 'woo_furatpay'); ?>
                            </a>
                            <a href="<?php echo esc_url($fib_data['corporateAppLink']); ?>" class="button alt furatpay-app-button" target="_blank">
                                <?php esc_html_e('Corporate Banking', 'woo_furatpay'); ?>
                            </a>
                        </div>
                        <p class="furatpay-valid-until">
                            <?php 
                            $valid_until = new DateTime($fib_data['validUntil']);
                            $valid_until->setTimezone(new DateTimeZone(wp_timezone_string()));
                            echo sprintf(
                                esc_html__('Valid until: %s', 'woo_furatpay'),
                                $valid_until->format('Y-m-d H:i:s T')
                            );
                            ?>
                        </p>
                    </div>
                </div>
            <?php elseif ($payment_service == 5): // PayTabs ?>
                <div class="furatpay-paytabs-container">
                    <?php
                    $paytabs_data = get_transient('furatpay_paytabs_data_' . $invoice_id);
                    if ($paytabs_data): ?>
                        <div class="furatpay-paytabs-details">
                            <p class="paytabs-amount">
                                <?php echo sprintf(
                                    esc_html__('Amount: %s %s', 'woo_furatpay'),
                                    $paytabs_data['cart_amount'],
                                    $paytabs_data['cart_currency']
                                ); ?>
                            </p>
                            <p class="paytabs-ref">
                                <?php echo sprintf(
                                    esc_html__('Transaction Reference: %s', 'woo_furatpay'),
                                    $paytabs_data['tran_ref']
                                ); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <p><?php esc_html_e('You will be redirected to PayTabs secure payment page.', 'woo_furatpay'); ?></p>
                    <button id="furatpay-open-payment" class="button alt">
                        <?php esc_html_e('Proceed to PayTabs', 'woo_furatpay'); ?>
                    </button>
                </div>
            <?php else: ?>
                <!-- Payment Status Container -->
                <div id="furatpay-payment-status" class="furatpay-payment-section">
                    <p><?php esc_html_e('Payment window has been opened in a new tab. Please complete your payment there.', 'woo_furatpay'); ?></p>
                    <div class="furatpay-spinner"></div>
                    <p style="font-size: 17px;font-weight:500;"><?php esc_html_e('This page will update automatically once payment is confirmed.', 'woo_furatpay'); ?></p>
                </div>

                <!-- Popup Blocked Message -->
                <div id="furatpay-popup-blocked" class="furatpay-payment-section" style="display: none;">
                    <p class="furatpay-warning"><?php esc_html_e('To proceed with your payment, please allow popups for this website.', 'woo_furatpay'); ?></p>
                    <button id="furatpay-retry-payment" class="button alt">
                        <?php esc_html_e('Try Again', 'woo_furatpay'); ?>
                    </button>
                </div>

                <!-- Payment Retry Container -->
                <div id="furatpay-payment-retry" class="furatpay-payment-section" style="display: none;">
                    <p><?php esc_html_e('Payment window was closed. Click below to reopen the payment window:', 'woo_furatpay'); ?></p>
                    <button id="furatpay-reopen-payment" class="button alt">
                        <?php esc_html_e('Reopen Payment Window', 'woo_furatpay'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(function($) {
    var fibData = <?php echo json_encode(get_transient('furatpay_fib_data_' . $invoice_id)); ?>;
    var paymentUrl = <?php echo json_encode($payment_url); ?>;
    var returnUrl = <?php echo json_encode($return_url); ?>;
    var orderId = <?php echo json_encode($order->get_id()); ?>;
    var checkInterval;
    var paymentWindow = null;

    function openPaymentWindow() {
      
        // First try to open a test window
        var testWindow = window.open('about:blank', 'test');
        if (!testWindow || testWindow.closed) {
            showSection('popup-blocked');
            return false;
        }
        testWindow.close();

        // for FIB skip window open
        if(!fibData){
            // Try to open actual payment window
            paymentWindow = window.open(paymentUrl, 'FuratPayment');
            if (!paymentWindow || paymentWindow.closed) {
                showSection('popup-blocked');
                return false;
            }
            paymentWindow.focus();
            showSection('payment-status');
        }

        return true;
    }

    function showSection(section) {
        // Hide all sections first
        $('.furatpay-payment-section').hide();
        
        // Show the requested section
        switch(section) {
            case 'payment-status':
                $('#furatpay-payment-status').show();
                break;
            case 'popup-blocked':
                $('#furatpay-popup-blocked').show();
                break;
            case 'payment-retry':
                $('#furatpay-payment-retry').show();
                break;
        }
    }

    function startPaymentCheck() {
        if (checkInterval) {
            clearInterval(checkInterval);
        }
        checkInterval = setInterval(checkPaymentStatus, 5000);
    }

    function checkPaymentStatus() {
        // Check if payment window is closed and update UI
        if (paymentWindow && paymentWindow.closed) {
            showSection('payment-retry');
            // Don't return - continue checking payment status
        }

        $.ajax({
            url: <?php echo json_encode($ajax_url); ?>,
            type: 'POST',
            data: {
                action: 'furatpay_check_payment_status',
                order_id: orderId,
                nonce: <?php echo json_encode($nonce); ?>
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.status === 'completed') {
                        clearInterval(checkInterval);
                        window.location.href = returnUrl;
                    } else if (response.data.status === 'failed') {
                        clearInterval(checkInterval);
                        showSection('payment-retry');
                    }
                    // If still pending, keep checking
                }
            }
        });
    }

    // Initial payment window open and start checking
    openPaymentWindow();
    startPaymentCheck(); // Start checking immediately

    // Event Handlers
    $('#furatpay-retry-payment, #furatpay-reopen-payment').on('click', function(e) {
        e.preventDefault();
        if (openPaymentWindow()) {
            // Don't need to start checking again as it's already running
            paymentWindow.focus();
        }
    });
});
</script>

<style>
.furatpay-payment-page {
    max-width: 800px;
    margin: 2em auto;
    padding: 20px;
}

.furatpay-payment-container {
    background: #fff;
    padding: 2em;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.furatpay-spinner {
    display: inline-block;
    width: 50px;
    height: 50px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #49db34;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.furatpay-warning {
    color: #d63638;
    font-weight: 500;
    margin-bottom: 1em;
}

#furatpay-retry-payment,
#furatpay-reopen-payment {
    display: inline-block;
    padding: 12px 24px;
    background-color: #52c41a;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s ease;
    margin: 10px 0;
    width: auto;
    min-width: 100%;
    height: 60px;
}

#furatpay-retry-payment:hover,
#furatpay-reopen-payment:hover {
    background-color: #389e0d;
    color: #ffffff;
    text-decoration: none;
}

#furatpay-retry-payment:active,
#furatpay-reopen-payment:active {
    transform: translateY(1px);
}

#furatpay-retry-payment:disabled,
#furatpay-reopen-payment:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

.furatpay-payment-details {
    text-align: center;
}

/* FIB specific styles */
.furatpay-fib-container {
    max-width: 600px;
    margin: 2em auto;
    padding: 1em;
}

.furatpay-qr-section {
    margin-bottom: 2em;
}

.furatpay-qr-code {
    max-width: 300px;
    height: auto;
    margin: 1em auto;
    display: block;
}

.furatpay-readable-code {
    font-size: 1.2em;
    font-family: monospace;
    background: #f5f5f5;
    padding: 0.5em;
    border-radius: 4px;
    display: inline-block;
    margin: 1em 0;
}

.furatpay-app-links {
    margin-top: 2em;
}

.furatpay-app-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 1em;
    justify-content: center;
    margin: 1em 0;
}

.furatpay-app-button {
    flex: 1;
    min-width: 200px;
    text-align: center;
    margin: 0.5em;
}

.furatpay-valid-until {
    color: #666;
    font-size: 0.9em;
    margin-top: 1em;
}

/* PayTabs specific styles */
.furatpay-paytabs-container {
    text-align: center;
    margin: 2em 0;
}

.furatpay-paytabs-details {
    background: #f8f8f8;
    padding: 1em;
    border-radius: 4px;
    margin-bottom: 1em;
}

.furatpay-paytabs-details p {
    margin: 0.5em 0;
    font-size: 1.1em;
}

.paytabs-amount {
    font-weight: bold;
    color: #333;
}

.paytabs-ref {
    color: #666;
    font-family: monospace;
}

.furatpay-paytabs-container .button {
    font-size: 1.2em;
    padding: 1em 2em;
    margin: 1em 0;
    background-color: #00a1e0;
    color: #fff;
}

.furatpay-paytabs-container .button:hover {
    background-color: #0089bd;
}

#furatpay-open-payment,
#furatpay-retry-payment,
#furatpay-reopen-payment {
    display: inline-block;
    padding: 12px 24px;
    background-color: #52c41a;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s ease;
    margin: 10px 0;
    width: auto;
    min-width: 100%;
    height: 60px;
}

#furatpay-open-payment:hover,
#furatpay-retry-payment:hover,
#furatpay-reopen-payment:hover {
    background-color: #389e0d;
    color: #ffffff;
    text-decoration: none;
}

#furatpay-open-payment:active,
#furatpay-retry-payment:active,
#furatpay-reopen-payment:active {
    transform: translateY(1px);
}

#furatpay-open-payment:disabled,
#furatpay-retry-payment:disabled,
#furatpay-reopen-payment:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}
</style>

<?php
get_footer('shop');
?> 