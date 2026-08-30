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
    // Paginated, Filtered & Sorted Catalog for Big Datasets
    public function getProducts(Request $request)
    {
        $query = Product::with(['category', 'images'])
            ->where('status', 'active');

        // Search title or description
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by product format (digital vs physical)
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Price range filters
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sorting
        switch ($request->get('sort', 'newest')) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Paginate results (default 12 items per page)
        $perPage = $request->get('per_page', 12);
        return response()->json($query->paginate($perPage));
    }

    // Featured Products Showcase for Home Page
    public function getFeaturedProducts()
    {
        $featured = Product::with(['category', 'images'])
            ->where('status', 'active')
            ->latest()
            ->take(8)
            ->get();

        return response()->json(['products' => $featured]);
    }

    // Single product details
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
        $taxPercent = Setting::where('key', 'tax_percentage')->value('value') ?? 5;
        $deliveryFee = Setting::where('key', 'delivery_fee')->value('value') ?? 250;

        return response()->json([
            'tax_percentage' => (float) $taxPercent,
            'delivery_fee' => (float) $deliveryFee,
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string',
            'shipping_address' => 'nullable|string',
            'payment_method' => 'required|in:cod,advance',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $subtotal = 0;
        $hasPhysical = false;
        $hasDigital = false;
        $orderItemsData = [];

        foreach ($request->items as $itemData) {
            $product = Product::findOrFail($itemData['id']);
            $qty = $product->type === 'digital' ? 1 : $itemData['quantity'];
            $lineTotal = (float) $product->price * $qty;

            $subtotal += $lineTotal;
            if ($product->type === 'digital') {
                $hasDigital = true;
            } else {
                $hasPhysical = true;
            }

            $orderItemsData[] = [
                'product_id' => $product->id,
                'unit_price' => $product->price,
                'quantity' => $qty,
                'line_total' => $lineTotal,
            ];
        }

        if ($hasDigital && !$hasPhysical && $request->payment_method === 'cod') {
            return response()->json(['message' => 'Cash on Delivery is not allowed for Digital downloads.'], 422);
        }

        $taxPercent = (float) (Setting::where('key', 'tax_percentage')->value('value') ?? 5);
        $flatDeliveryFee = (float) (Setting::where('key', 'delivery_fee')->value('value') ?? 250);
        $deliveryFee = $hasPhysical ? $flatDeliveryFee : 0;

        $discountAmount = 0;
        if ($request->promo_code) {
            $promo = PromoCode::where('code', strtoupper($request->promo_code))->where('status', 'active')->first();
            if ($promo) {
                $discountAmount = $promo->type === 'percentage' ? ($subtotal * $promo->value) / 100 : $promo->value;
            }
        }

        $taxableAmount = max(0, $subtotal - $discountAmount);
        $taxAmount = ($taxableAmount * $taxPercent) / 100;
        $totalAmount = $taxableAmount + $taxAmount + $deliveryFee;

        $order = Order::create([
            'user_id' => auth('sanctum')->check() ? auth('sanctum')->id() : null,
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'shipping_address' => $request->shipping_address,
            'product_id' => $orderItemsData[0]['product_id'],
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'payment_method' => $request->payment_method,
            'payment_status' => $request->payment_method === 'advance' ? 'paid' : 'pending',
            'status' => (!$hasPhysical && $hasDigital) ? 'delivered' : 'pending',
        ]);

        foreach ($orderItemsData as $item) {
            $order->items()->create($item);
        }

        return response()->json([
            'message' => 'Order placed successfully!',
            'order' => $order->load('items.product.images'),
        ], 201);
    }
}