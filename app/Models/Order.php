<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'phone_number',
        'email',
        'order_type',
        'service_type',
        'pickup_time',
        'delivery_address',
        'suite_apt',
        'order_items',
        'promo_code_id',
        'subtotal',
        'discount_amount',
        'total_amount',
        'status',
        'uber_delivery_id',
        'uber_delivery_status',
        'uber_tracking_url',
        'uber_delivery_fee',
        'uber_delivery_response',
        'uber_delivery_error',
        'uber_delivery_dispatched_at',
    ];

    protected $casts = [
        'order_items' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'uber_delivery_fee' => 'integer',
        'uber_delivery_response' => 'array',
        'uber_delivery_dispatched_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function rewardTransactions(): HasMany
    {
        return $this->hasMany(RewardTransaction::class);
    }
}
