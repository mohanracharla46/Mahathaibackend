<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = ['category_id', 'sub_category', 'name', 'description', 'price', 'image_url', 'rating', 'is_available', 'addon_options', 'protein_choice', 'spice_options', 'size_options', 'suggested_item_ids'];

    protected $casts = [
        'price' => 'decimal:2',
        'rating' => 'decimal:2',
        'is_available' => 'boolean',
        'addon_options' => 'array',
        'protein_choice' => 'array',
        'spice_options' => 'array',
        'size_options' => 'array',
        'suggested_item_ids' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}

