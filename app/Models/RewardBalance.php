<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardBalance extends Model
{
    protected $fillable = [
        'user_id',
        'current_points',
        'lifetime_points',
        'redeemed_points',
        'last_earned_at',
    ];

    protected $casts = [
        'current_points' => 'integer',
        'lifetime_points' => 'integer',
        'redeemed_points' => 'integer',
        'last_earned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
