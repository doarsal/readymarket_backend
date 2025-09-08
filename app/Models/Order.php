<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'cart_id',
        'store_id',
        'billing_information_id',
        'microsoft_account_id',
        'status',
        'payment_status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency_id',
        'exchange_rate',
        'exchange_rate_date',
        'payment_method',
        'payment_gateway',
        'transaction_id',
        'paid_at',
        'refunded_amount',
        'refunded_at',
        'refund_reason',
        'notes',
        'customer_notes',
        'tags',
        'metadata',
        'cancelled_at',
        'cancellation_reason',
        'processed_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'tags' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'processed_at' => 'datetime',
        'exchange_rate_date' => 'datetime'
    ];

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    /**
     * Relaciones
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function billingInformation(): BelongsTo
    {
        return $this->belongsTo(BillingInformation::class);
    }

    public function microsoftAccount(): BelongsTo
    {
        return $this->belongsTo(MicrosoftAccount::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id', 'cart_id');
    }

    /**
     * Scopes
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, string $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['delivered', 'fulfilled']);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByCustomer($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Métodos
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD-' . now()->year . '-';
        $lastOrder = self::where('order_number', 'like', $prefix . '%')
                        ->orderBy('order_number', 'desc')
                        ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, strlen($prefix));
            $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '000001';
        }

        return $prefix . $newNumber;
    }

    public function markAsPaid(): void
    {
        $this->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'status' => 'processing'
        ]);
    }

    public function markAsFulfilled(): void
    {
        $this->update([
            'status' => 'completed', // Para productos digitales
            'fulfillment_status' => 'fulfilled',
            'processed_at' => now()
        ]);
    }

    public function markAsDelivered(): void
    {
        // Para productos digitales, es lo mismo que fulfilled
        $this->markAsFulfilled();
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'fulfillment_status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason
        ]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function canBeRefunded(): bool
    {
        return $this->payment_status === 'paid' &&
               !in_array($this->status, ['refunded', 'cancelled']);
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            default => 'Desconocido'
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match($this->payment_status) {
            'pending' => 'Pendiente',
            'paid' => 'Pagado',
            'failed' => 'Falló',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'partial_refund' => 'Reembolso Parcial',
            default => 'Desconocido'
        };
    }

    /**
     * Crear orden desde carrito
     */
    public static function createFromCart(Cart $cart, array $orderData = []): self
    {
        // Obtener la moneda de la tienda o USD por defecto
        $currencyId = $cart->store->default_currency_id ?? 1; // USD por defecto

        // Obtener el tipo de cambio actual si no es USD
        $exchangeRate = 1.0;
        $exchangeRateDate = now();

        if ($currencyId != 1) { // Si no es USD, buscar tipo de cambio
            $currentRate = \App\Models\ExchangeRate::where('from_currency_id', 1) // Desde USD
                ->where('to_currency_id', $currencyId)
                ->where('is_active', true)
                ->latest('date')
                ->first();

            if ($currentRate) {
                $exchangeRate = $currentRate->rate;
                $exchangeRateDate = $currentRate->date;
            }
        }

        $order = self::create(array_merge([
            'order_number' => self::generateOrderNumber(),
            'user_id' => $cart->user_id,
            'cart_id' => $cart->id,
            'store_id' => $cart->store_id,
            'billing_information_id' => $orderData['billing_information_id'] ?? null,
            'subtotal' => $cart->subtotal,
            'tax_amount' => $cart->tax_amount,
            'total_amount' => $cart->total_amount,
            'currency_id' => $currencyId,
            'exchange_rate' => $exchangeRate,
            'exchange_rate_date' => $exchangeRateDate,
            'payment_method' => $orderData['payment_method'] ?? null,
            'payment_gateway' => $orderData['payment_gateway'] ?? null,
            'notes' => $orderData['notes'] ?? null,
        ], $orderData));

        // Crear order_items con SNAPSHOT COMPLETO
        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;
            $category = $product->category ?? null;

            // SNAPSHOT COMPLETO - TODOS LOS DATOS QUE PUEDEN CAMBIAR
            $order->items()->create([
                // Product reference
                'product_id' => $product->idproduct,

                // === COMPLETE PRODUCT SNAPSHOT ===
                'sku_id' => $product->SkuId,
                'product_title' => $product->ProductTitle,
                'product_description' => $product->SkuDescription,
                'publisher' => $product->Publisher,
                'segment' => $product->Segment,
                'market' => $product->Market,
                'license_duration' => $product->LicenseDuration,

                // Pricing snapshot
                'unit_price' => $cartItem->unit_price,
                'list_price' => $product->UnitPrice, // Precio original del producto
                'discount_amount' => max(0, ($product->UnitPrice ?? 0) - $cartItem->unit_price),
                'currency_id' => $currencyId, // Foreign key a currencies

                // Order specific
                'quantity' => $cartItem->quantity,
                'line_total' => $cartItem->total_price,

                // Category snapshot
                'category_name' => $category ? $category->name : null,
                'category_id_snapshot' => $category ? $category->id : null,

                // Product flags snapshot
                'is_top' => (bool) ($product->top ?? false),
                'is_bestseller' => (bool) ($product->bestseller ?? false),
                'is_novelty' => (bool) ($product->novelty ?? false),
                'is_active' => (bool) ($product->is_active ?? true),

                // Complete metadata snapshot
                'product_metadata' => [
                    // Core product data
                    'product_id' => $product->ProductId,
                    'sku_id' => $product->SkuId,
                    'market' => $product->Market,
                    'segment' => $product->Segment,
                    'publisher' => $product->Publisher,
                    'language' => $product->Language,
                    'currency_original' => $product->Currency,
                    'unit_price_original' => $product->UnitPrice,

                    // Categorization
                    'category_path' => $category ? [
                        'id' => $category->id,
                        'name' => $category->name,
                        'identifier' => $category->identifier
                    ] : null,

                    // Product attributes
                    'license_duration' => $product->LicenseDuration,
                    'service_tier' => $product->ServiceTier,
                    'description' => $product->SkuDescription,

                    // Product status at time of purchase
                    'was_top' => (bool) ($product->top ?? false),
                    'was_bestseller' => (bool) ($product->bestseller ?? false),
                    'was_novelty' => (bool) ($product->novelty ?? false),
                    'was_active' => (bool) ($product->is_active ?? true),

                    // Store info
                    'store_id' => $product->store_id,
                    'store_name' => $cart->store ? $cart->store->name : null,
                ],

                'pricing_metadata' => [
                    'original_price' => $product->UnitPrice,
                    'sale_price' => $cartItem->unit_price,
                    'discount_applied' => max(0, ($product->UnitPrice ?? 0) - $cartItem->unit_price),
                    'discount_percentage' => $product->UnitPrice > 0 ?
                        round(((($product->UnitPrice ?? 0) - $cartItem->unit_price) / ($product->UnitPrice ?? 1)) * 100, 2) : 0,
                    'currency_id' => $currencyId,
                    'exchange_rate' => $exchangeRate,
                    'exchange_rate_date' => $exchangeRateDate,
                    'cart_item_metadata' => $cartItem->metadata,
                ]
            ]);
        }

        return $order;
    }
}
