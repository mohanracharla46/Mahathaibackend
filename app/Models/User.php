<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password',
        'role',
        'last_ordered_on',
        'following_email',
        'following_sms',
        'points_remaining',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_ordered_on' => 'datetime',
            'following_email' => 'boolean',
            'following_sms' => 'boolean',
            'points_remaining' => 'integer',
        ];
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function rewardBalance()
    {
        return $this->hasOne(RewardBalance::class);
    }

    public function rewardTransactions()
    {
        return $this->hasMany(RewardTransaction::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function contactMessages()
    {
        return $this->hasMany(ContactMessage::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    public function conciergeInquiries()
    {
        return $this->hasMany(ConciergeInquiry::class);
    }

    public function giftCards()
    {
        return $this->hasMany(GiftCard::class, 'buyer_user_id');
    }
}
