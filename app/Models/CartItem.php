<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="CartItem",
 *     type="object",
 *     title="Cart Item",
 *     description="Shopping cart item model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="cart_id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=1),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="unit_price", type="number", format="float", example=49.99),
 *     @OA\Property(property="total_price", type="number", format="float", example=99.98),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"active", "saved_for_later", "removed"}, example="active"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="product", ref="#/components/schemas/Product"),
 * )
 */
class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'metadata',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Boot method para actualizar totales automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        // Actualizar totales del carrito cuando se modifica un item
        static::saved(function ($cartItem) {
            $cartItem->cart->updateTotals();
        });

        static::deleted(function ($cartItem) {
            $cartItem->cart->updateTotals();
        });
    }

    /**
     * Relación con el carrito
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'idproduct');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSavedForLater($query)
    {
        return $query->where('status', 'saved_for_later');
    }

    /**
     * Actualizar cantidad y recalcular precio total
     */
    public function updateQuantity(int $quantity): void
    {
        $this->update([
            'quantity' => $quantity,
            'total_price' => $quantity * $this->unit_price,
        ]);
    }

    /**
     * Actualizar precio unitario y recalcular total
     */
    public function updatePrice(float $unitPrice): void
    {
        $this->update([
            'unit_price' => $unitPrice,
            'total_price' => $this->quantity * $unitPrice,
        ]);
    }

    /**
     * Guardar para después
     */
    public function saveForLater(): void
    {
        $this->update(['status' => 'saved_for_later']);
    }

    /**
     * Mover de "guardado" a carrito activo
     */
    public function moveToCart(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Obtener el precio total formateado
     */
    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_price, 2);
    }

    /**
     * Obtener el precio unitario formateado
     */
    public function getFormattedUnitPriceAttribute(): string
    {
        return number_format($this->unit_price, 2);
    }

    /**
     * Verificar si el item está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verificar si el item está guardado para después
     */
    public function isSavedForLater(): bool
    {
        return $this->status === 'saved_for_later';
    }
}
