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
        $orders = Order::with(['product.images'])
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function toggleStatus($id)
    {
        $order = Order::findOrFail($id);
        $order->status = $order->status === 'failed' ? 'success' : 'failed';
        $order->save();

        return response()->json([
            'message' => "Order status updated to {$order->status}.",
            'order' => $order->load(['product.images']),
        ]);
    }

    public function sendProductEmail($id)
    {
        $order = Order::with('product')->findOrFail($id);

        if (!$order->product || !$order->product->file_path) {
            return response()->json([
                'message' => 'Cannot send email. Product does not have a digital file attached.',
            ], 422);
        }

        // 1. Get full server path of the digital file
        $filePath = $order->product->file_path;
        
        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'message' => 'File does not exist on server storage.',
            ], 404);
        }

        $fullSystemPath = Storage::disk('public')->path($filePath);
        $originalFileName = $order->product->file_original_name ?? basename($filePath);

        // 2. Fetch custom email template settings
        $subjectTemplate = Setting::where('key', 'digital_email_subject')->value('value') 
            ?? "Your Digital Product Purchase - {product_name}";
        $bodyTemplate = Setting::where('key', 'digital_email_body')->value('value') 
            ?? "<p>Hi <strong>{customer_name}</strong>,</p><p>Thank you for your order <strong>#{order_id}</strong>! Please find your product <strong>{product_name}</strong> attached to this email.</p>";

        // 3. Replace dynamic variables
        $replacements = [
            '{customer_name}' => $order->customer_name,
            '{product_name}'  => $order->product->title,
            '{order_id}'       => $order->order_number,
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subjectTemplate);
        $bodyHtml = str_replace(array_keys($replacements), array_values($replacements), $bodyTemplate);

        try {
            // 4. Send email with direct file attachment
            Mail::html($bodyHtml, function ($message) use ($order, $subject, $fullSystemPath, $originalFileName) {
                $message->to($order->customer_email, $order->customer_name)
                        ->subject($subject)
                        ->attach($fullSystemPath, [
                            'as' => $originalFileName, // Sets attachment name to customer-friendly name
                        ]);
            });

            // 5. Automatically mark status as success and set timestamp
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