<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'product_id',
        'total_amount',
        'status',
        'email_sent_at',
    ];

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $latestId = static::max('id') ?? 0;
                $order->order_number = 'ORD-' . (10000 + $latestId + 1);
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
