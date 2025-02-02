const { registerPaymentMethod } = wc.wcBlocksRegistry;

const paymentMethod = {
    name: 'furatpay',
    label: 'FuratPay',
    content: <div>Payment method fields will go here</div>,
    edit: <div>Payment method fields will go here</div>,
    canMakePayment: () => true,
    ariaLabel: 'FuratPay',
    supports: {
        features: ['products']
    },
};

registerPaymentMethod(paymentMethod);