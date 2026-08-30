<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['items.product.images', 'user'])
            ->latest()
            ->get();

        return response()->json(['orders' => $orders]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|string']);

        $order = Order::with(['items.product', 'product'])->findOrFail($id);
        $order->status = $request->status;

        if ($request->status === 'delivered') {
            $order->payment_status = 'paid';
        }

        $order->save();

        $emailSent = $this->sendStatusEmail($order, $request->status);

        return response()->json([
            'message' => $emailSent
                ? "Order status updated to {$request->status} & notification email sent."
                : "Order status updated to {$request->status}.",
            'order' => $order->load('items.product.images'),
        ]);
    }

    /**
     * Sends a status-change notification email using the admin-configured
     * template for the given status. Returns true if an email was sent.
     */
    private function sendStatusEmail(Order $order, string $status): bool
    {
        $templateMap = [
            'confirmed' => [
                'subjectKey' => 'order_confirmation_subject',
                'bodyKey' => 'order_confirmation_body',
                'defaultSubject' => 'Order Confirmed - {order_id}',
                'defaultBody' => '<p>Hi <strong>{customer_name}</strong>,</p><p>Your order <strong>{order_id}</strong> has been received and confirmed. We will notify you as it progresses.</p>',
            ],
            'preparing' => [
                'subjectKey' => 'order_preparing_subject',
                'bodyKey' => 'order_preparing_body',
                'defaultSubject' => 'Your Order {order_id} is Being Prepared',
                'defaultBody' => '<p>Hi <strong>{customer_name}</strong>,</p><p>Your order <strong>{order_id}</strong> for <strong>{product_name}</strong> is now being prepared.</p>',
            ],
            'delivered' => [
                'subjectKey' => 'order_delivered_subject',
                'bodyKey' => 'order_delivered_body',
                'defaultSubject' => 'Your Order {order_id} Has Been Delivered',
                'defaultBody' => '<p>Hi <strong>{customer_name}</strong>,</p><p>Your order <strong>{order_id}</strong> has been delivered. Thank you for shopping with us!</p>',
            ],
            'cancelled' => [
                'subjectKey' => 'order_cancelled_subject',
                'bodyKey' => 'order_cancelled_body',
                'defaultSubject' => 'Order {order_id} Cancelled',
                'defaultBody' => '<p>Hi <strong>{customer_name}</strong>,</p><p>Your order <strong>{order_id}</strong> has been cancelled. If this was a mistake, please contact our support team.</p>',
            ],
        ];

        if (!isset($templateMap[$status])) {
            return false;
        }

        $tpl = $templateMap[$status];

        $subjectTemplate = Setting::where('key', $tpl['subjectKey'])->value('value') ?? $tpl['defaultSubject'];
        $bodyTemplate = Setting::where('key', $tpl['bodyKey'])->value('value') ?? $tpl['defaultBody'];

        $productName = optional($order->product)->title
            ?? optional(optional($order->items->first())->product)->title
            ?? 'Your Order';

        $replacements = [
            '{customer_name}' => $order->customer_name,
            '{order_id}' => $order->order_number,
            '{product_name}' => $productName,
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subjectTemplate);
        $bodyHtml = str_replace(array_keys($replacements), array_values($replacements), $bodyTemplate);

        try {
            Mail::html($bodyHtml, function ($message) use ($order, $subject) {
                $message->to($order->customer_email, $order->customer_name)
                    ->subject($subject);
            });
            return true;
        } catch (\Exception $e) {
            // Status update should still succeed even if the mail server is unreachable.
            return false;
        }
    }

    public function sendProductEmail($id)
    {
        $order = Order::with('product')->findOrFail($id);

        if (!$order->product || !$order->product->file_path) {
            return response()->json([
                'message' => 'Cannot send email. Product does not have a digital file attached.',
            ], 422);
        }

        $filePath = $order->product->file_path;

        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'message' => 'File does not exist on server storage.',
            ], 404);
        }

        $fullSystemPath = Storage::disk('public')->path($filePath);
        $originalFileName = $order->product->file_original_name ?? basename($filePath);

        $subjectTemplate = Setting::where('key', 'digital_email_subject')->value('value')
            ?? "Your Digital Product Purchase - {product_name}";
        $bodyTemplate = Setting::where('key', 'digital_email_body')->value('value')
            ?? "<p>Hi <strong>{customer_name}</strong>,</p><p>Thank you for your order <strong>#{order_id}</strong>! Please find your product <strong>{product_name}</strong> attached to this email.</p>";

        $replacements = [
            '{customer_name}' => $order->customer_name,
            '{product_name}' => $order->product->title,
            '{order_id}' => $order->order_number,
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subjectTemplate);
        $bodyHtml = str_replace(array_keys($replacements), array_values($replacements), $bodyTemplate);

        try {
            Mail::html($bodyHtml, function ($message) use ($order, $subject, $fullSystemPath, $originalFileName) {
                $message->to($order->customer_email, $order->customer_name)
                    ->subject($subject)
                    ->attach($fullSystemPath, [
                        'as' => $originalFileName,
                    ]);
            });

            $order->status = 'success';
            $order->email_sent_at = now();
            $order->save();

            return response()->json([
                'message' => "Product file attached and email sent successfully to {$order->customer_email}.",
                'order' => $order->load(['product.images']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
    }
}