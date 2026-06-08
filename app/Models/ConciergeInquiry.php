<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciergeInquiry extends Model
{
    protected $fillable = ['user_id', 'message', 'response', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
