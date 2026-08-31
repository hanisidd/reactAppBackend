<?php

return [

    // Which gateway is used for the "advance" online-payment method.
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'safepay'),

    'safepay' => [
        'client_id' => env('SAFEPAY_CLIENT_ID'),
        'secret_key' => env('SAFEPAY_SECRET_KEY'),
        'environment' => env('SAFEPAY_ENV', 'sandbox'), // 'sandbox' or 'production'
        'api_base' => env('SAFEPAY_API_BASE', 'https://sandbox.api.getsafepay.com'),
        'checkout_base' => env('SAFEPAY_CHECKOUT_BASE', 'https://sandbox.getsafepay.com'),
        'return_url' => env('SAFEPAY_RETURN_URL', env('FRONTEND_URL', 'http://localhost:5173') . '/payment/return'),
    ],

    // Kept for future use — see PaymentGatewayManager. Not active by default.
    'jazzcash' => [
        'merchant_id' => env('JAZZCASH_MERCHANT_ID'),
        'password' => env('JAZZCASH_PASSWORD'),
        'integrity_salt' => env('JAZZCASH_INTEGRITY_SALT'),
        'endpoint' => env(
            'JAZZCASH_ENDPOINT',
            'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'
        ),
        'return_url' => env('JAZZCASH_RETURN_URL', env('APP_URL') . '/api/payments/jazzcash/callback'),
    ],

];
