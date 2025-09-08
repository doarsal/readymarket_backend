<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="OrderItem",
 *     type="object",
 *     title="Order Item",
 *     description="Order item with complete product snapshot",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="order_id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=1, nullable=true),
 *     @OA\Property(property="sku_id", type="string", example="CFQ7TTC0LH18:0001"),
 *     @OA\Property(property="product_title", type="string", example="Microsoft 365 Business Premium"),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="unit_price", type="number", format="float", example=49.99),
 *     @OA\Property(property="line_total", type="number", format="float", example=99.98),
 *     @OA\Property(property="fulfillment_status", type="string", enum={"pending", "processing", "fulfilled", "cancelled", "refunded"}),
 *     @OA\Property(property="product_metadata", type="object"),
 *     @OA\Property(property="pricing_metadata", type="object"),
 * )
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'sku_id',
        'product_title',
        'product_description',
        'publisher',
        'segment',
        'market',
        'license_duration',
        'unit_price',
        'list_price',
        'discount_amount',
        'currency_id', // Cambiado de 'currency' a 'currency_id'
        'quantity',
        'line_total',
        'category_name',
        'category_id_snapshot',
        'is_top',
        'is_bestseller',
        'is_novelty',
        'is_active',
        'product_metadata',
        'pricing_metadata',
        'fulfillment_status',
        'fulfillment_error',
        'fulfilled_at',
        'processing_started_at',
        'refunded_amount',
        'refunded_at',
        'refund_reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'list_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'is_top' => 'boolean',
        'is_bestseller' => 'boolean',
        'is_novelty' => 'boolean',
        'is_active' => 'boolean',
        'product_metadata' => 'array',
        'pricing_metadata' => 'array',
        'fulfilled_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'idproduct');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('fulfillment_status', 'pending');
    }

    public function scopeFulfilled($query)
    {
        return $query->where('fulfillment_status', 'fulfilled');
    }

    public function scopeRefunded($query)
    {
        return $query->where('fulfillment_status', 'refunded');
    }

    /**
     * Methods
     */
    public function markAsFulfilled(): void
    {
        $this->update([
            'fulfillment_status' => 'fulfilled',
            'fulfilled_at' => now()
        ]);
    }

    public function refund(float $amount, string $reason = null): void
    {
        $this->update([
            'fulfillment_status' => 'refunded',
            'refunded_amount' => $amount,
            'refunded_at' => now(),
            'refund_reason' => $reason
        ]);
    }

    public function getDiscountPercentageAttribute(): float
    {
        if (!$this->list_price || $this->list_price <= 0) {
            return 0;
        }

        return round((($this->list_price - $this->unit_price) / $this->list_price) * 100, 2);
    }

    public function getTotalDiscountAttribute(): float
    {
        return $this->discount_amount * $this->quantity;
    }

    public function getIsRefundableAttribute(): bool
    {
        return in_array($this->fulfillment_status, ['pending', 'processing', 'fulfilled'])
               && $this->refunded_amount < $this->line_total;
    }
}
