<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'metadata',
    ];
    protected $casts    = [
        'expires_at' => 'datetime',
        'metadata'   => 'array',
    ];
    /**
     * Appends para compatibilidad con frontend
     */
    protected $appends = ['subtotal', 'tax_amount', 'total_amount'];

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

    public function checkOutItems(): BelongsToMany
    {
        return $this->belongsToMany(CheckOutItem::class);
    }

    public function cartCheckOutItems(): HasMany
    {
        return $this->hasMany(CartCheckOutItem::class);
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
        return $query->whereNotNull('expires_at')->where('expires_at', '<', now());
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
     * Calcular subtotal dinámico
     */
    public function getSubtotalItemsAttribute(): float
    {
        return round($this->activeItems->sum(function($item) {
            return $item->total_price; // Usa el accessor dinámico del CartItem
        }), 2);
    }

    /**
     * Calcular subtotal dinámico
     */
    public function getSubtotalAttribute(): float
    {
        return round($this->subtotal_items + $this->subtotal_check_out_items, 2);
    }

    /**
     * Calcular subtotal de los check out items
     */
    public function getSubtotalCheckOutItemsAttribute(): float
    {
        return round($this->cartCheckOutItems()->with('checkOutItem')->get()->sum(function(CartCheckOutItem $item) {
            if(!$item->status) {
                return 0;
            }

            return $item->checkOutItem->getPriceWithCart($this) ?? 0;
        }), 2);
    }

    public function getCheckOutItems(): Collection
    {
        return $this->cartCheckOutItems()->whereHas('checkOutItem', function($query) {
            $query->where('is_active', true);
        })->with('checkOutItem')->get()->map(function(CartCheckOutItem $cartCheckOutItem) {
            $item = $cartCheckOutItem->checkOutItem;
            return [
                'id'                   => $cartCheckOutItem->getKey(),
                'check_out_item_id'    => $item->getKey(),
                'item'                 => $item->item,
                'description'          => $item->description,
                'price'                => number_format($item->getPriceWithCart($this), 2),
                'default'              => $item->default,
                'min_cart_amount'      => $item->min_cart_amount ? number_format($item->min_cart_amount, 2) : null,
                'max_cart_amount'      => $item->max_cart_amount ? number_format($item->max_cart_amount, 2) : null,
                'percentage_of_amount' => $item->percentage_of_amount,
                'help_text'            => $item->help_text,
                'help_cta'             => $item->help_cta,
                'status'               => $cartCheckOutItem->status,
            ];
        })->values();
    }

    /**
     * Calcular tax amount dinámico
     */
    public function getTaxAmountAttribute(): float
    {
        $taxRate = config('facturalo.taxes.iva.rate', 0.16);

        return round($this->subtotal * $taxRate, 2);
    }

    /**
     * Calcular total amount dinámico
     */
    public function getTotalAmountAttribute(): float
    {
        return round($this->subtotal + $this->tax_amount, 2);
    }

    /**
     * Calcular totales del carrito (mantener para compatibilidad)
     */
    public function calculateTotals(): array
    {
        $subtotal    = $this->subtotal;
        $taxAmount   = $this->tax_amount;
        $totalAmount = $this->total_amount;

        return [
            'subtotal'     => $subtotal,
            'tax_amount'   => $taxAmount,
            'tax_rate'     => config('facturalo.taxes.iva.rate', 0.16),
            'total_amount' => $totalAmount,
            'items_count'  => $this->activeItems->sum('quantity'),
        ];
    }

    /**
     * Actualizar totales en la base de datos (YA NO HACE NADA, mantener para compatibilidad)
     */
    public function updateTotals(): void
    {
        // Método vacío para mantener compatibilidad con código existente
        // Los totales ahora se calculan dinámicamente
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

        static::creating(function($cart) {
            if (empty($cart->cart_token)) {
                $cart->cart_token = self::generateCartToken();
            }
        });
    }
}
