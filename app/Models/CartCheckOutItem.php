<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CartCheckOutItem extends Pivot
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'check_out_item_id',
        'quantity',
    ];

    //Relationships

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function checkOutItem(): BelongsTo
    {
        return $this->belongsTo(CheckOutItem::class);
    }
}
