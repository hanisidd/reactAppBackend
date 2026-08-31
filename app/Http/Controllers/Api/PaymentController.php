<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Called right after an order is created with payment_method = 'advance'
     * (or the digital portion of a mixed COD order — see checkout()).
     * Returns the redirect target for the frontend to send the browser to.
     */
    public function initiate(Request $request, $orderId)
    {
        $request->validate([
            'gateway' => 'nullable|string',
        ]);

        $order = Order::findOrFail($orderId);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Order is already paid.'], 422);
        }

        $gatewayKey = $request->input('gateway', config('payment.default_gateway'));
        $gateway = PaymentGatewayManager::make($gatewayKey);

        $order->payment_gateway = $gateway->key();
        $order->save();

        try {
            $payload = $gateway->initiate($order);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($payload);
    }

    /**
     * Called by the frontend right after the browser is redirected back
     * from the gateway (e.g. /payment/return?tracker=...&order_id=...).
     * Independently re-verifies the payment server-to-server before
     * trusting anything from the query string.
     */
    public function verify(Request $request, string $gatewayKey)
    {
        return $this->processResult($gatewayKey, $request->all());
    }

    /**
     * Server-to-server webhook endpoint, if/when the gateway supports one.
     */
    public function callback(Request $request, string $gatewayKey)
    {
        return $this->processResult($gatewayKey, $request->all());
    }

    private function processResult(string $gatewayKey, array $payload)
    {
        $gateway = PaymentGatewayManager::make($gatewayKey);
        $result = $gateway->handleCallback($payload);

        if (!$result['order_number']) {
            return response()->json(['message' => 'Missing order reference.', 'success' => false], 422);
        }

        $order = Order::where('order_number', $result['order_number'])->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.', 'success' => false], 404);
        }

        // Idempotency guard — gateways may call this more than once.
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Already processed.', 'success' => true, 'order' => $order]);
        }

        $order->gateway_transaction_id = $result['transaction_id'];
        $order->gateway_response = $result['raw'];

        if ($result['success']) {
            $order->payment_status = 'paid';
            $order->status = $order->status === 'pending' ? 'confirmed' : $order->status;
        } else {
            $order->payment_status = 'failed';
        }

        $order->save();

        return response()->json([
            'message' => $result['success'] ? 'Payment successful.' : ($result['error'] ?? 'Payment failed.'),
            'success' => $result['success'],
            'order' => $order,
        ]);
    }
}
