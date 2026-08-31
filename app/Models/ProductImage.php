<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductImage extends Model
{use  HasFactory;
    protected $fillable = [
        'product_id',
        'image_path',
        'order',
        'is_cover',
    ];

    protected $casts = [
        'is_cover' => 'boolean',
        'order' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
