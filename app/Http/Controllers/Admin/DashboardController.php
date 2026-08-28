<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $totalOrders = Order::count();
        // Calculate earnings from successful orders
        $totalEarnings = Order::where('status', 'success')->sum('total_amount');
        $totalUsers = User::count();
        $totalProducts = Product::count();
        $totalCategories = Category::count();

        // Fetch 5 most recent orders
        $recentOrders = Order::with('product')
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_earnings' => (float) $totalEarnings,
                'total_orders' => $totalOrders,
                'total_users' => $totalUsers,
                'total_products' => $totalProducts,
                'total_categories' => $totalCategories,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }
}
