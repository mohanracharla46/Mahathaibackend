<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = ['user_id', 'dining_experience', 'rating', 'review_notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
