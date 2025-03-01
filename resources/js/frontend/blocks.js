/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';

const PaymentServiceList = ({ services, selectedService, onSelect }) => {
    return (
        <ul className="furatpay-method-list">
            {services.map((service) => (
                <li key={service.id} className="furatpay-method-item">
                    <label>
                        <input
                            type="radio"
                            name="furatpay_service"
                            value={service.id}
                            checked={selectedService === service.id}
                            onChange={() => onSelect(service.id)}
                            required="required"
                            className="furatpay-service-radio"
                        />
                        {service.logo && (
                            <img
                                src={service.logo}
                                alt={service.name}
                                className="furatpay-method-logo"
                            />
                        )}
                        <span className="furatpay-method-name">
                            {service.name}
                        </span>
                    </label>
                </li>
            ))}
        </ul>
    );
};

const FuratPayComponent = ({ eventRegistration, emitResponse }) => {
    const [selectedService, setSelectedService] = useState(null);
    const [paymentServices, setPaymentServices] = useState([]);
    const { onPaymentSetup } = eventRegistration;

    // Fetch payment services
    useEffect(() => {
        const fetchServices = async () => {
            try {
                const response = await fetch(furatpayData.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'furatpay_get_payment_services',
                        nonce: furatpayData.nonce,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch payment services');
                }

                const data = await response.json();
                if (data.success && Array.isArray(data.data)) {
                    setPaymentServices(data.data);
                }
            } catch (error) {
                console.error('Error fetching payment services:', error);
            }
        };

        fetchServices();
    }, []);

    useEffect(() => {
        const unsubscribe = onPaymentSetup(() => {
            if (!selectedService) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: __('Please select a payment service.', 'woo_furatpay'),
                };
            }

            // Find the selected service details
            const service = paymentServices.find(s => s.id === selectedService);
            if (!service) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: __('Invalid payment service selected.', 'woo_furatpay'),
                };
            }

            console.log('FuratPay: Processing payment with service:', service);

            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        payment_method: PAYMENT_METHOD_NAME,
                        furatpay_service: selectedService.toString(),
                        furatpay_service_name: service.name
                    },
                },
            };
        });

        return () => unsubscribe();
    }, [onPaymentSetup, selectedService, paymentServices]);

    if (paymentServices.length === 0) {
        return (
            <div className="furatpay-payment-method-block">
                <p>{__('No payment services available.', 'woo_furatpay')}</p>
            </div>
        );
    }

    return (
        <div className="furatpay-payment-method-block">
            <PaymentServiceList
                services={paymentServices}
                selectedService={selectedService}
                onSelect={setSelectedService}
            />
        </div>
    );
};

const FuratPayLabel = () => {
    return (
        <div className="furatpay-block-label">
            <span>{furatpayData.title}</span>
            {furatpayData.icon && (
                <img
                    src={furatpayData.icon}
                    alt="FuratPay"
                    className="furatpay-icon"
                />
            )}
        </div>
    );
};

const options = {
    name: PAYMENT_METHOD_NAME,
    label: <FuratPayLabel />,
    content: <FuratPayComponent />,
    edit: <FuratPayComponent />,
    canMakePayment: () => true,
    ariaLabel: __('FuratPay payment method', 'woo_furatpay'),
    supports: {
        features: furatpayData.supports || [],
        showSavedPaymentMethods: false,
        showSaveOption: false,
    },
};

// Use the global wc object
const { registerPaymentMethod } = wc.wcBlocksRegistry;
registerPaymentMethod(options); 