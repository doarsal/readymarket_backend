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
        'metadata',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Appends para compatibilidad con frontend
     */
    protected $appends = ['unit_price', 'total_price'];

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
     * Obtener precio unitario dinámico del producto convertido a la moneda de la tienda
     */
    public function getUnitPriceAttribute(): float
    {
        if (!$this->product) {
            return 0.0;
        }

        // Usar el CurrencyService para obtener precios consistentes
        $currencyService = app(\App\Services\CurrencyService::class);
        $storeId = config('app.store_id', 1);

        try {
            $priceInfo = $currencyService->getProductPrice($this->product, $storeId);
            return (float) $priceInfo['amount'];
        } catch (\Exception $e) {
            \Log::error('Error calculating cart item unit price', [
                'cart_item_id' => $this->id,
                'product_id' => $this->product_id,
                'error' => $e->getMessage()
            ]);

            // Fallback a cálculo manual
            return $this->calculateFallbackPrice();
        }
    }

    /**
     * Método fallback para calcular precio si falla el CurrencyService
     */
    private function calculateFallbackPrice(): float
    {
        if (!$this->product) {
            return 0.0;
        }

        // Obtener precio del producto en USD
        $erpPrice = $this->product->ERPPrice ? (float) str_replace(',', '', $this->product->ERPPrice) : 0;
        $unitPrice = $this->product->UnitPrice ? (float) str_replace(',', '', $this->product->UnitPrice) : 0;

        $unitPriceUSD = $unitPrice > 0 ? $unitPrice : $erpPrice;

        if ($unitPriceUSD <= 0) {
            return 0.0;
        }

        // Obtener tipo de cambio actual usando el mismo método que en CurrencyService
        $exchangeRate = $this->getCurrentExchangeRate();

        return round($unitPriceUSD * $exchangeRate, 2);
    }

    /**
     * Obtener precio total dinámico (cantidad * precio unitario)
     */
    public function getTotalPriceAttribute(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }

    /**
     * Obtener tipo de cambio actual USD -> Moneda de la tienda
     * Usa el mismo servicio que el resto del sistema para garantizar consistencia
     */
    protected function getCurrentExchangeRate(): float
    {
        try {
            $currencyService = app(\App\Services\CurrencyService::class);
            $storeId = config('app.store_id', 1);

            // Obtener moneda de la tienda
            $storeCurrency = $currencyService->getStoreCurrency($storeId);
            if (!$storeCurrency) {
                return 18.50; // Fallback
            }

            // Obtener moneda USD
            $usdCurrency = $currencyService->getCurrencyByCode('USD');
            if (!$usdCurrency) {
                return 18.50; // Fallback
            }

            // Si la moneda de la tienda es USD, no hay conversión
            if ($storeCurrency->code === 'USD') {
                return 1.0;
            }

            // Usar el servicio de conversión
            $converted = $currencyService->convertAmount(1.0, $usdCurrency->id, $storeCurrency->id);

            return $converted > 0 ? $converted : 18.50; // Fallback si la conversión falla

        } catch (\Exception $e) {
            \Log::error('Error getting exchange rate for cart item', [
                'cart_item_id' => $this->id ?? null,
                'error' => $e->getMessage()
            ]);

            return 18.50; // Fallback seguro
        }
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
     * Actualizar cantidad únicamente
     */
    public function updateQuantity(int $quantity): void
    {
        $this->update(['quantity' => $quantity]);
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
