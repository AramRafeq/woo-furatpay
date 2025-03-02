jQuery(function($) {
    var paymentUrl = furatpayData.paymentUrl;
    var returnUrl = furatpayData.returnUrl;
    var orderId = furatpayData.orderId;
    var checkInterval;
    var paymentWindow = null;

    function openPaymentWindow() {
        var testWindow = window.open('about:blank', 'test');
        if (!testWindow || testWindow.closed) {
            showSection('popup-blocked');
            return false;
        }
        testWindow.close();

        paymentWindow = window.open(paymentUrl, 'FuratPayment');
        if (!paymentWindow || paymentWindow.closed) {
            showSection('popup-blocked');
            return false;
        }

        paymentWindow.focus();
        showSection('payment-status');
        return true;
    }

    function showSection(section) {
        $('.furatpay-payment-section').hide();
        $('#furatpay-' + section).show();
    }

    function startPaymentCheck() {
        if (checkInterval) {
            clearInterval(checkInterval);
        }
        checkInterval = setInterval(checkPaymentStatus, 5000);
    }

    function checkPaymentStatus() {
        if (paymentWindow && paymentWindow.closed) {
            showSection('payment-retry');
        }

        $.ajax({
            url: furatpayData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'furatpay_check_payment_status',
                order_id: orderId,
                nonce: furatpayData.nonce
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
                }
            }
        });
    }

    openPaymentWindow();
    startPaymentCheck();

    $('#furatpay-retry-payment, #furatpay-reopen-payment').on('click', function(e) {
        e.preventDefault();
        if (openPaymentWindow()) {
            paymentWindow.focus();
        }
    });
}); 