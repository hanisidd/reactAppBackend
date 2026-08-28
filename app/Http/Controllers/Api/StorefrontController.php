<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class StorefrontController extends Controller
{
    // Fetch active products with category filter support
    public function getProducts(Request $request)
    {
        $query = Product::with(['category', 'images'])
            ->where('status', 'active');

        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        return response()->json([
            'products' => $query->latest()->get(),
        ]);
    }

    // Single product details with gallery
    public function getProduct($id)
    {
        $product = Product::with(['category', 'images'])
            ->where('status', 'active')
            ->findOrFail($id);

        return response()->json(['product' => $product]);
    }

    // Categories list
    public function getCategories()
    {
        return response()->json([
            'categories' => Category::all(),
        ]);
    }

    // Validate Promo Code
    public function validatePromoCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $promo = PromoCode::where('code', strtoupper($request->code))
            ->where('status', 'active')
            ->first();

        if (!$promo) {
            return response()->json(['message' => 'Invalid promo code.'], 404);
        }

        if ($promo->expires_at && $promo->expires_at->isPast()) {
            return response()->json(['message' => 'Promo code has expired.'], 422);
        }

        return response()->json([
            'message' => 'Promo code applied!',
            'promo' => $promo,
        ]);
    }

    // Retrieve Store Tax & Delivery Fee settings
    public function getCheckoutSettings()
    {
        $taxPercent = Setting::where('key', 'tax_percentage')->value('value') ?? 5; // Default 5%
        $deliveryFee = Setting::where('key', 'delivery_fee')->value('value') ?? 10; // Default $10

        return response()->json([
            'tax_percentage' => (float) $taxPercent,
            'delivery_fee' => (float) $deliveryFee,
        ]);
    }

    // Storefront Checkout Endpoint (Supports Guests & Logged-in Users)
    public function checkout(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string',
            'shipping_address' => 'required_if:has_physical,true|nullable|string',
            'product_id' => 'required|exists:products,id',
            'payment_method' => 'required|in:cod,advance',
            'promo_code' => 'nullable|string',
        ]);

        $product = Product::findOrFail($request->product_id);

        // Payment restriction rule: Digital products require Advance Payment
        if ($product->type === 'digital' && $request->payment_method === 'cod') {
            return response()->json([
                'message' => 'Cash on Delivery is not allowed for Digital Products. Please select Advance Payment.',
            ], 422);
        }

        // Fetch Tax and Delivery settings from DB
        $taxPercent = (float) (Setting::where('key', 'tax_percentage')->value('value') ?? 5);
        $flatDeliveryFee = (float) (Setting::where('key', 'delivery_fee')->value('value') ?? 10);

        // Physical items incur delivery fees; digital items do not
        $deliveryFee = $product->type === 'physical' ? $flatDeliveryFee : 0;
        $subtotal = (float) $product->price;

        // Apply Promo Code discount if provided
        $discountAmount = 0;
        if ($request->promo_code) {
            $promo = PromoCode::where('code', strtoupper($request->promo_code))
                ->where('status', 'active')
                ->first();

            if ($promo) {
                if ($promo->type === 'percentage') {
                    $discountAmount = ($subtotal * $promo->value) / 100;
                } else {
                    $discountAmount = $promo->value;
                }
            }
        }

        $taxableAmount = max(0, $subtotal - $discountAmount);
        $taxAmount = ($taxableAmount * $taxPercent) / 100;
        $totalAmount = $taxableAmount + $taxAmount + $deliveryFee;

        // Create Order
        $order = Order::create([
            'user_id' => auth('sanctum')->check() ? auth('sanctum')->id() : null,
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'shipping_address' => $request->shipping_address,
            'product_id' => $product->id,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'payment_method' => $request->payment_method,
            'payment_status' => $request->payment_method === 'advance' ? 'paid' : 'pending',
            'status' => 'failed', // Default status as requested
        ]);

        // Send Order Confirmation Email
        $this->sendOrderConfirmationEmail($order, $product);

        return response()->json([
            'message' => 'Order placed successfully!',
            'order' => $order->load('product'),
        ], 201);
    }

    private function sendOrderConfirmationEmail($order, $product)
    {
        try {
            $subject = "Order Confirmation - #{$order->order_number}";
            $body = "<h2>Thank you for your order, {$order->customer_name}!</h2>
                     <p>Order Number: <strong>#{$order->order_number}</strong></p>
                     <p>Product: {$product->title}</p>
                     <p>Total Paid: \${$order->total_amount}</p>
                     <p>Payment Method: " . strtoupper($order->payment_method) . "</p>";

            // If digital product and advance payment, attach digital file directly
            if ($product->type === 'digital' && $product->file_path && Storage::disk('public')->exists($product->file_path)) {
                $fullPath = Storage::disk('public')->path($product->file_path);
                Mail::html($body, function ($msg) use ($order, $subject, $fullPath, $product) {
                    $msg->to($order->customer_email)
                        ->subject($subject)
                        ->attach($fullPath, ['as' => $product->file_original_name]);
                });
                $order->email_sent_at = now();
                $order->save();
            } else {
                Mail::html($body, function ($msg) use ($order, $subject) {
                    $msg->to($order->customer_email)->subject($subject);
                });
            }
        } catch (\Exception $e) {
            // Log error silently without failing checkout request
            \Log::error("Order email error: " . $e->getMessage());
        }
    }
}
