// Immediate logging to check if file is loaded
console.log('FuratPay checkout.js file loaded');

jQuery(function($) {
    'use strict';

    // Log initialization
    console.log('FuratPay checkout.js initialized with jQuery');
    console.log('FuratPay data:', furatpayData);

    // Debug check if we're on checkout page
    if ($('form.woocommerce-checkout').length) {
        console.log('Classic checkout form found on page');
    } else if ($('.wc-block-checkout').length) {
        console.log('Block checkout form found on page');
    } else {
        console.log('No checkout form found on page');
    }

    // Create and show the payment waiting overlay
    function showPaymentWaitingOverlay() {
        console.log('Showing payment waiting overlay');
        const overlay = $('<div/>', {
            class: 'furatpay-payment-overlay',
            html: `
                <div class="furatpay-payment-status">
                    <h2>${furatpayData.i18n.waiting}</h2>
                    <p>${furatpayData.i18n.complete_payment}</p>
                    <div class="furatpay-spinner"></div>
                </div>
            `
        });
        $('body').append(overlay);
        $('body').addClass('furatpay-waiting');
    }

    // Remove the payment waiting overlay
    function removePaymentWaitingOverlay() {
        console.log('Removing payment waiting overlay');
        $('.furatpay-payment-overlay').remove();
        $('body').removeClass('furatpay-waiting');
    }

    // Function to open payment window with permission handling
    function openPaymentWindow(paymentUrl) {
        console.log('Attempting to open payment URL:', paymentUrl);
        
        // First try to open a small test window to trigger permission request
        const testWindow = window.open('about:blank', '_blank', 'width=1,height=1');
        
        if (!testWindow) {
            console.log('Initial popup blocked - showing manual button');
            return false;
        }
        
        // Close the test window
        testWindow.close();
        
        // Now try to open the actual payment window
        const paymentWindow = window.open(paymentUrl, '_blank');
        
        if (!paymentWindow) {
            console.log('Payment window failed to open');
            return false;
        }
        
        console.log('Payment window opened successfully');
        
        // Focus the payment window
        try {
            paymentWindow.focus();
        } catch (e) {
            console.log('Could not focus payment window:', e);
        }
        
        return true;
    }

    // Handle form submission for both classic and block checkout
    function handleCheckoutSubmit(e) {
        console.log('Checkout submit handler called');
        const selectedPaymentMethod = $('input[name="payment_method"]:checked').val();
        console.log('Selected payment method:', selectedPaymentMethod);
        
        if (selectedPaymentMethod === 'furatpay') {
            console.log('FuratPay payment method selected');
            const selectedService = $('input[name="furatpay_service"]:checked').val();
            console.log('Selected FuratPay service:', selectedService);
            
            if (!selectedService) {
                console.log('No service selected - showing error');
                e.preventDefault();
                e.stopPropagation();
                $('.woocommerce-notices-wrapper').html(
                    `<div class="woocommerce-error">${furatpayData.i18n.selectService}</div>`
                );
                return false;
            }
            
            // For classic checkout, show overlay immediately
            if (!$('.wc-block-checkout').length) {
                showPaymentWaitingOverlay();
            }
        }
    }

    // Bind to classic checkout form
    $('form.woocommerce-checkout').on('submit', function(e) {
        console.log('Classic checkout form submitted');
        return handleCheckoutSubmit(e);
    });

    // Bind to block checkout button
    $(document.body).on('click', '.wc-block-components-checkout-place-order-button', function(e) {
        console.log('Block checkout button clicked');
        return handleCheckoutSubmit(e);
    });

    // Bind to classic checkout button
    $(document.body).on('click', '#place_order', function(e) {
        console.log('Classic checkout button clicked');
        return handleCheckoutSubmit(e);
    });

    // Listen for successful order creation
    $(document.body).on('checkout_error', function() {
        console.log('Checkout error detected - removing overlay');
        removePaymentWaitingOverlay();
    });

    // Handle the checkout response
    $(document).ajaxComplete(function(event, xhr, settings) {
        console.log('AJAX request completed:', settings.url);

        // Only process checkout-related AJAX responses
        if (!settings.url || (!settings.url.includes('?wc-ajax=checkout') && !settings.url.includes('/?wc-ajax=checkout'))) {
            return;
        }

        console.log('Processing checkout AJAX response');
        console.log('Response text:', xhr.responseText);

        let response;
        try {
            response = JSON.parse(xhr.responseText);
            console.log('Parsed response:', response);
        } catch (e) {
            console.error('Failed to parse response:', e);
            console.log('Raw response:', xhr.responseText);
            return;
        }

        // Check if this is our payment method
        const isOurPayment = response.payment_method === 'furatpay' || 
                           (response.data && response.data.payment_method === 'furatpay');

        console.log('Is FuratPay payment?', isOurPayment);

        if (!isOurPayment) {
            return;
        }

        // Handle successful checkout
        if (response.result === 'success') {
            console.log('Successful checkout detected');

            // Extract payment URL
            const paymentUrl = response.furatpay_payment_url || 
                             (response.data && response.data.furatpay_payment_url);

            console.log('Payment URL:', paymentUrl);

            if (!paymentUrl) {
                console.error('No payment URL found in response');
                $('.woocommerce-notices-wrapper').html(`
                    <div class="woocommerce-error">
                        Payment URL not found. Please try again or contact support.
                    </div>
                `);
                return;
            }

            // Show waiting overlay if not already shown
            if (!$('.furatpay-payment-overlay').length) {
                showPaymentWaitingOverlay();
            }
                
            // Try to open the payment window
            const windowOpened = openPaymentWindow(paymentUrl);
            
            if (!windowOpened) {
                console.log('Payment window blocked - showing manual button');
                removePaymentWaitingOverlay();
                $('.woocommerce-notices-wrapper').html(`
                    <div class="woocommerce-notice">
                        <p>${furatpayData.i18n.popupBlocked}</p>
                        <p>
                            <a href="${paymentUrl}" 
                               target="_blank" 
                               class="button" 
                               onclick="window.furatpayShowOverlay()">
                                ${furatpayData.i18n.openPayment}
                            </a>
                        </p>
                    </div>
                `);
                
                window.furatpayShowOverlay = function() {
                    console.log('Manual payment button clicked');
                    showPaymentWaitingOverlay();
                };
            }

            // Get order ID
            const orderId = response.order_id || (response.data && response.data.order_id);
            console.log('Order ID:', orderId);

            if (orderId) {
                console.log('Starting payment status polling');
                pollPaymentStatus(orderId);
            } else {
                console.warn('No order ID found in response');
            }
        } else {
            console.error('Checkout failed:', response);
            removePaymentWaitingOverlay();
        }
    });

    // Poll for payment status
    function pollPaymentStatus(orderId) {
        console.log('Polling payment status for order:', orderId);
        
        const checkStatus = () => {
            console.log('Checking payment status...');
            
            $.ajax({
                url: furatpayData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'furatpay_check_payment_status',
                    nonce: furatpayData.nonce,
                    order_id: orderId
                }
            })
            .done(function(response) {
                console.log('Payment status response:', response);
                
                if (response.success) {
                    if (response.data.status === 'completed') {
                        console.log('Payment completed - redirecting');
                        window.location = response.data.redirect_url;
                    } else if (response.data.status === 'failed') {
                        console.log('Payment failed - showing error');
                        removePaymentWaitingOverlay();
                        $('.woocommerce-notices-wrapper').html(
                            `<div class="woocommerce-error">${response.data.message}</div>`
                        );
                    } else {
                        console.log('Payment still pending - continuing to poll');
                        setTimeout(checkStatus, 5000);
                    }
                }
            })
            .fail(function(error) {
                console.error('Failed to check payment status:', error);
                setTimeout(checkStatus, 5000);
            });
        };

        // Start polling
        checkStatus();
    }

    // Log that initialization is complete
    console.log('FuratPay checkout.js initialization complete');
}); 