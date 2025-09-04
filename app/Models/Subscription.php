<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'subscriptions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'order_id',
        'subscription_identifier',
        'offer_id',
        'subscription_id',
        'term_duration',
        'transaction_type',
        'friendly_name',
        'quantity',
        'pricing',
        'status',
        'microsoft_account_id',
        'product_id',
        'sku_id',
        'created_by',
        'modified_by'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'pricing' => 'decimal:2',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the order that owns the subscription
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the Microsoft account that owns the subscription
     */
    public function microsoftAccount()
    {
        return $this->belongsTo(MicrosoftAccount::class, 'microsoft_account_id');
    }

    /**
     * Get the product associated with the subscription
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'idproduct');
    }

    /**
     * Scope for active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope for a specific Microsoft account
     */
    public function scopeForMicrosoftAccount($query, $accountId)
    {
        return $query->where('microsoft_account_id', $accountId);
    }
}
