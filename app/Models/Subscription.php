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
        'modified_by',
        'effective_start_date',
        'commitment_end_date',
        'auto_renew_enabled',
        'billing_cycle',
        'cancellation_allowed_until_date'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'pricing' => 'decimal:2',
        'status' => 'integer',
        'auto_renew_enabled' => 'boolean',
        'effective_start_date' => 'datetime',
        'commitment_end_date' => 'datetime',
        'cancellation_allowed_until_date' => 'datetime',
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
     * Get the user through the order relationship
     */
    public function user()
    {
        return $this->hasOneThrough(
            User::class,
            Order::class,
            'id',           // Foreign key on orders table
            'id',           // Foreign key on users table
            'order_id',     // Local key on subscriptions table
            'user_id'       // Local key on orders table
        );
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
