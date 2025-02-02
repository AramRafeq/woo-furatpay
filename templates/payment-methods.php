<div class="furatpay-payment-methods">
    <p>Yooo!!!</p>
    <?php if (empty($payment_services)): ?>
        <p><?php _e('No payment methods available.', 'woo_furatpay'); ?></p>
    <?php else: ?>
        <ul class="furatpay-method-list">
            <?php foreach ($payment_services as $service): ?>
                <li class="furatpay-method-item">
                    <label>
                        <input 
                            type="radio" 
                            name="furatpay_service" 
                            value="<?php echo esc_attr($service['id']); ?>"
                            required
                        >
                        <?php if (!empty($service['logo_url'])): ?>
                            <img 
                                src="<?php echo esc_url($service['logo_url']); ?>" 
                                alt="<?php echo esc_attr($service['name']); ?>"
                                class="furatpay-method-logo"
                            >
                        <?php endif; ?>
                        <span class="furatpay-method-name">
                            <?php echo esc_html($service['name']); ?>
                        </span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>