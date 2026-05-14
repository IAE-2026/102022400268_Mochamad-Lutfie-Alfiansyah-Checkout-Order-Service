<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'checkout_id',
        'order_id',
        'payment_method',
        'amount',
        'status',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'checkout_id' => 'integer',
            'order_id' => 'integer',
            'amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
