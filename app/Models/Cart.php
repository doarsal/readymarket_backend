<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="Cart",
 *     type="object",
 *     title="Cart",
 *     description="Shopping cart model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="session_id", type="string", nullable=true, example="laravel_session_123"),
 *     @OA\Property(property="store_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="currency_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="status", type="string", enum={"active", "abandoned", "converted", "merged"}, example="active"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="subtotal", type="number", format="float", example=99.99),
 *     @OA\Property(property="tax_amount", type="number", format="float", example=15.99),
 *     @OA\Property(property="total_amount", type="number", format="float", example=115.98),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CartItem"))
 * )
 */
class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'cart_token',
        'store_id',
        'currency_id',
        'status',
        'expires_at',
        'subtotal',
        'tax_amount',
        'total_amount',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Relación con el usuario propietario del carrito
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con la tienda
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Relación con la moneda
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Relación con los items del carrito
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Items activos del carrito
     */
    public function activeItems(): HasMany
    {
        return $this->hasMany(CartItem::class)->where('status', 'active');
    }

    /**
     * Items guardados para después
     */
    public function savedItems(): HasMany
    {
        return $this->hasMany(CartItem::class)->where('status', 'saved_for_later');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Calcular totales del carrito
     */
    public function calculateTotals(): array
    {
        $subtotal = $this->activeItems()->sum('total_price');

        // Obtener tax rate desde store_configurations
        $taxRate = StoreConfiguration::get(
            $this->store_id,
            'tax',
            'rate',
            0.16 // 16% IVA México como fallback
        );

        $taxAmount = $subtotal * $taxRate;
        $total = $subtotal + $taxAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'tax_rate' => $taxRate,
            'total_amount' => round($total, 2),
            'items_count' => $this->activeItems()->sum('quantity'),
        ];
    }

    /**
     * Actualizar totales en la base de datos
     */
    public function updateTotals(): void
    {
        $totals = $this->calculateTotals();

        $this->update([
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total_amount' => $totals['total_amount'],
        ]);
    }

    /**
     * Verificar si el carrito está expirado
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Verificar si el carrito está vacío
     */
    public function isEmpty(): bool
    {
        return $this->activeItems()->count() === 0;
    }

    /**
     * Limpiar items del carrito
     */
    public function clearItems(): void
    {
        $this->items()->delete();
        $this->updateTotals();
    }

    /**
     * Convertir carrito a pedido (marcar como convertido)
     */
    public function markAsConverted(): void
    {
        $this->update(['status' => 'converted']);
    }

    /**
     * Marcar carrito como abandonado
     */
    public function markAsAbandoned(): void
    {
        $this->update(['status' => 'abandoned']);
    }

    /**
     * Generar un cart_token único
     */
    public static function generateCartToken(): string
    {
        do {
            $token = \Str::random(32);
        } while (self::where('cart_token', $token)->exists());

        return $token;
    }

    /**
     * Boot method para generar cart_token automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cart) {
            if (empty($cart->cart_token)) {
                $cart->cart_token = self::generateCartToken();
            }
        });
    }
}
