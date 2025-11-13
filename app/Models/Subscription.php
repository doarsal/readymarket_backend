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
     * Append computed attributes to JSON
     */
    protected $appends = [
        'next_renewal_date',
        'renewal_frequency',
        'days_until_renewal',
        'expiration_info'
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

    /**
     * Get the next renewal date based on commitment_end_date
     * This is when the subscription will renew if auto_renew_enabled is true
     */
    public function getNextRenewalDateAttribute()
    {
        // If auto-renewal is disabled or it's a perpetual license, no renewal
        if (!$this->auto_renew_enabled || $this->isPerpetual()) {
            return null;
        }

        // Return commitment end date as next renewal
        return $this->commitment_end_date;
    }

    /**
     * Get renewal frequency in human-readable format
     */
    public function getRenewalFrequencyAttribute()
    {
        if (!$this->auto_renew_enabled || $this->isPerpetual()) {
            return 'No se renueva';
        }

        $billingCycle = strtolower($this->billing_cycle ?? '');

        return match($billingCycle) {
            'monthly' => 'Mensual',
            'annual', 'yearly' => 'Anual',
            'triennial' => 'Cada 3 años',
            'one_time', 'onetime' => 'Compra única',
            default => ucfirst($billingCycle)
        };
    }

    /**
     * Get days until next renewal
     */
    public function getDaysUntilRenewalAttribute()
    {
        $nextRenewal = $this->next_renewal_date;

        if (!$nextRenewal) {
            return null;
        }

        $now = now();

        if ($nextRenewal->isPast()) {
            return 0; // Already passed
        }

        return $now->diffInDays($nextRenewal);
    }

    /**
     * Check if this is a perpetual license (one-time purchase)
     */
    public function isPerpetual()
    {
        $billingCycle = strtolower($this->billing_cycle ?? '');

        return in_array($billingCycle, ['one_time', 'onetime', 'one-time']) ||
               empty($this->term_duration);
    }

    /**
     * Check if subscription will renew
     */
    public function willRenew()
    {
        return $this->auto_renew_enabled && !$this->isPerpetual();
    }

    /**
     * Get formatted expiration or renewal info
     */
    public function getExpirationInfoAttribute()
    {
        if ($this->isPerpetual()) {
            return 'Licencia perpetua - no expira';
        }

        if (!$this->commitment_end_date) {
            return 'Fecha no disponible';
        }

        if ($this->auto_renew_enabled) {
            $date = $this->commitment_end_date->format('d/m/Y');
            $frequency = $this->renewal_frequency;
            return "Se renueva {$frequency} el {$date}";
        }

        return 'Expira el ' . $this->commitment_end_date->format('d/m/Y');
    }
}
