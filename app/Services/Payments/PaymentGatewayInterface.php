<?php

namespace App\Services\Payments;

use App\Models\Order;

interface PaymentGatewayInterface
{
    /**
     * Build the data needed to send the customer to the gateway's hosted
     * payment page. Returns an array shaped like:
     * [
     *   'redirect_url' => string,  // the URL/action to POST or GET to
     *   'method'       => 'POST'|'GET',
     *   'fields'       => array,   // form fields to submit (POST gateways)
     * ]
     */
    public function initiate(Order $order): array;

    /**
     * Verify & interpret an incoming callback/webhook from the gateway.
     * Must verify the signature/hash before trusting any payload data.
     *
     * Returns ['success' => bool, 'order_number' => string, 'transaction_id' => ?string, 'raw' => array]
     */
    public function handleCallback(array $payload): array;

    /**
     * Unique gateway identifier, e.g. "jazzcash", "easypaisa".
     */
    public function key(): string;
}
