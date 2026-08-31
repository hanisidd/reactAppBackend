<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Called right after an order is created with payment_method = 'advance'.
     * Returns the redirect target + signed fields for the frontend to
     * auto-submit to the gateway's hosted page.
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

        $payload = $gateway->initiate($order);

        return response()->json($payload);
    }

    /**
     * JazzCash (and future gateways) POST the transaction result here.
     * This must independently verify the payload — never trust status
     * fields without checking the signature (handled inside the gateway).
     */
    public function callback(Request $request, string $gatewayKey)
    {
        $gateway = PaymentGatewayManager::make($gatewayKey);
        $result = $gateway->handleCallback($request->all());

        if (!$result['order_number']) {
            return response()->json(['message' => 'Missing order reference.'], 422);
        }

        $order = Order::where('order_number', $result['order_number'])->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Idempotency guard: if we've already marked this paid (e.g. the
        // gateway retries the webhook), don't reprocess it.
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Already processed.', 'order' => $order]);
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
