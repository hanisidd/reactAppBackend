<?php

return [

    // Which gateway is used for the "advance" online-payment method.
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'jazzcash'),

    'jazzcash' => [
        'merchant_id' => env('JAZZCASH_MERCHANT_ID'),
        'password' => env('JAZZCASH_PASSWORD'),
        'integrity_salt' => env('JAZZCASH_INTEGRITY_SALT'),
        // Use the UAT sandbox endpoint while testing, switch to the live
        // endpoint (given by JazzCash on onboarding) for production.
        'endpoint' => env(
            'JAZZCASH_ENDPOINT',
            'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'
        ),
        'return_url' => env('JAZZCASH_RETURN_URL', env('APP_URL') . '/api/payments/jazzcash/callback'),
    ],

];
