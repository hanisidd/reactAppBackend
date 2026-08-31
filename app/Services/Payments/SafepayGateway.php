<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Safepay (getsafepay.com) redirect checkout integration.
 *
 * Flow:
 *  1. initiate(): POST to Safepay's /order/v1/init to create a "tracker"
 *     for this order's amount, get back a tracker token.
 *  2. Frontend redirects the browser (simple window.location, no signed
 *     form needed) to Safepay's hosted checkout with that token.
 *  3. Safepay redirects the browser back to our return_url with
 *     ?tracker=...&order_id=... in the query string.
 *  4. handleCallback() takes those query params and calls Safepay's
 *     status endpoint server-to-server to confirm the real payment state
 *     — we never trust the redirect query params alone, since those are
 *     attacker-controllable from the browser.
 *
 * NOTE: exact endpoint paths/response shapes should be confirmed against
 * your live Safepay merchant dashboard docs before going to production —
 * Safepay (like most gateways) versions its API and the illustrative
 * paths below may need adjusting to match your account's API version.
 */
class SafepayGateway implements PaymentGatewayInterface
{
    private string $apiBase;
    private string $checkoutBase;
    private string $clientId;
    private string $secretKey;
    private string $returnUrl;
    private string $environment;

    public function __construct()
    {
        $this->apiBase = config('payment.safepay.api_base');
        $this->checkoutBase = config('payment.safepay.checkout_base');
        $this->clientId = config('payment.safepay.client_id');
        $this->secretKey = config('payment.safepay.secret_key');
        $this->returnUrl = config('payment.safepay.return_url');
        $this->environment = config('payment.safepay.environment');
    }

    public function key(): string
    {
        return 'safepay';
    }

    public function initiate(Order $order): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$this->apiBase}/order/v1/init", [
            'client' => $this->clientId,
            'amount' => (int) round($order->total_amount * 100), // smallest currency unit
            'currency' => 'PKR',
            'merchant_api_key' => $this->secretKey,
            'order_id' => $order->order_number,
            'source' => 'checkout_link',
        ]);

        if (!$response->successful()) {
            Log::error('Safepay init failed', ['body' => $response->body()]);
            throw new \RuntimeException('Unable to start Safepay checkout. Please try again.');
        }

        $token = $response->json('data.tracker.token');

        if (!$token) {
            throw new \RuntimeException('Safepay did not return a checkout token.');
        }

        $redirectUrl = "{$this->checkoutBase}/checkout/pay"
            . "?tracker={$token}"
            . "&env={$this->environment}"
            . "&redirect_url=" . urlencode($this->returnUrl . '?order_id=' . $order->order_number);

        return [
            'redirect_url' => $redirectUrl,
            'method' => 'GET', // simple browser redirect, no form to build
            'fields' => [],
        ];
    }

    public function handleCallback(array $payload): array
    {
        $tracker = $payload['tracker'] ?? null;
        $orderNumber = $payload['order_id'] ?? null;

        if (!$tracker || !$orderNumber) {
            return [
                'success' => false,
                'order_number' => $orderNumber,
                'transaction_id' => null,
                'raw' => $payload,
                'error' => 'Missing tracker or order reference.',
            ];
        }

        // Server-to-server verification — do not trust the query string alone.
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->get("{$this->apiBase}/order/v1/status", [
            'tracker' => $tracker,
            'merchant_api_key' => $this->secretKey,
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'order_number' => $orderNumber,
                'transaction_id' => $tracker,
                'raw' => $payload,
                'error' => 'Could not verify payment with Safepay.',
            ];
        }

        $status = $response->json('data.state') ?? $response->json('data.status');
        $success = in_array($status, ['COMPLETED', 'TRACKER_COMPLETED', 'PAID'], true);

        return [
            'success' => $success,
            'order_number' => $orderNumber,
            'transaction_id' => $tracker,
            'raw' => array_merge($payload, ['safepay_status' => $status]),
            'error' => $success ? null : "Payment not completed (status: {$status}).",
        ];
    }
}
