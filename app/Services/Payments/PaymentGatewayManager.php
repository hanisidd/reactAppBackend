<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * Resolve a gateway by key. To add another gateway later:
     * 1. Create App\Services\Payments\XyzGateway implementing PaymentGatewayInterface
     * 2. Add a case below
     * 3. Add its credentials to config/payment.php
     * No other code changes needed — PaymentController and checkout stay the same.
     */
    public static function make(string $gatewayKey): PaymentGatewayInterface
    {
        return match ($gatewayKey) {
            'safepay' => app(SafepayGateway::class),
            'jazzcash' => app(JazzCashGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gatewayKey}"),
        };
    }
}
