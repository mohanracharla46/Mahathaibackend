<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCard extends Model
{
    protected $fillable = [
        'buyer_user_id',
        'recipient_name',
        'recipient_email',
        'sender_name',
        'sender_email',
        'card_type',
        'theme',
        'amount',
        'custom_message',
        'transmission_date',
        'gift_card_code',
        'balance',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'transmission_date' => 'date',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}
