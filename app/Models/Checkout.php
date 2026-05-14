<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Checkout extends Model
{
    protected $fillable = [
        'user_id',
        'cart_id',
        'shipping_address',
        'payment_method',
        'total_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckoutItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }
}
