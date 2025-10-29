<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'products';
    protected $primaryKey = 'idproduct';
    public $timestamps = true; // Cambiado a true

    protected $fillable = [
        // Campos de Microsoft (NO CAMBIAR)
        'ProductTitle',
        'ProductId',
        'SkuId',
        'Id',
        'SkuTitle',
        'Publisher',
        'SkuDescription',
        'UnitOfMeasure',
        'TermDuration',
        'BillingPlan',
        'Market',
        'Currency',
        'UnitPrice',
        'PricingTierRangeMin',
        'PricingTierRangeMax',
        'EffectiveStartDate',
        'EffectiveEndDate',
        'Tags',
        'ERPPrice',
        'Segment',

        // Campos propios mejorados
        'store_id',
        'category_id',
        'currency_id',
        'is_active',
        'is_top',
        'is_bestseller',
        'is_slide',
        'is_novelty',
        'prod_icon',
        'prod_slideimage',
        'prod_screenshot1',
        'prod_screenshot2',
        'prod_screenshot3',
        'prod_screenshot4',
        'prod_slide'
    ];

    protected $casts = [
        // Campos propios mejorados
        'is_active' => 'boolean',
        'is_top' => 'boolean',
        'is_bestseller' => 'boolean',
        'is_slide' => 'boolean',
        'is_novelty' => 'boolean',
        'deleted_at' => 'datetime'
    ];

    // Accessors for backward compatibility
    /**
     * Accessors actualizados
     */
    public function getTitleAttribute()
    {
        return $this->ProductTitle;
    }

    public function getDescriptionAttribute()
    {
        return $this->SkuDescription;
    }

    public function getProductDescriptionAttribute()
    {
        return $this->SkuDescription;
    }

    public function getLogoAttribute()
    {
        return $this->icon_url; // Actualizado
    }

    public function getStoreIdAttribute(): ?int
    {
        // Usar el nuevo campo store_id
        return $this->attributes['store_id'] ?? null;
    }

    // Price accessors that safely convert strings to decimals
    public function getUnitPriceDecimalAttribute()
    {
        return $this->UnitPrice ? (float) str_replace(',', '', $this->UnitPrice) : 0;
    }

    public function getErpPriceDecimalAttribute()
    {
        return $this->ERPPrice ? (float) str_replace(',', '', $this->ERPPrice) : 0;
    }

    public function getFormattedUnitPriceAttribute()
    {
        return number_format($this->unit_price_decimal, 2);
    }

    public function getFormattedErpPriceAttribute()
    {
        return number_format($this->erp_price_decimal, 2);
    }

    /**
     * Generate Microsoft Partner Center catalogItemId
     * Format: ProductId:SkuId:Id (same as old system)
     */
    public function getCatalogItemIdAttribute(): ?string
    {
        if (empty($this->ProductId) || empty($this->SkuId) || empty($this->Id)) {
            return null;
        }

        return $this->ProductId . ':' . $this->SkuId . ':' . $this->Id;
    }

    /**
     * Check if product has all required fields for Microsoft provisioning
     */
    public function hasValidCatalogItemId(): bool
    {
        return !empty($this->ProductId) && !empty($this->SkuId) && !empty($this->Id);
    }

    // Relationships
    public function store()
    {
        // Relación principal usando el nuevo campo store_id
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    // Relación legacy para compatibilidad (DEPRECATED)
    public function storeLegacy()
    {
        return $this->belongsTo(Store::class, 'prod_idstore', 'id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopePurchasable($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeCommercial($query)
    {
        return $query;
    }

    public function scopeTop($query)
    {
        return $query->where('top', 1);
    }

    public function scopeBestseller($query)
    {
        return $query->where('bestseller', 1);
    }

    public function scopeEffective($query)
    {
        $now = now()->format('Y-m-d H:i:s');
        return $query->where(function ($q) use ($now) {
            $q->where('EffectiveStartDate', '')
              ->orWhereNull('EffectiveStartDate')
              ->orWhere('EffectiveStartDate', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->where('EffectiveEndDate', '')
              ->orWhereNull('EffectiveEndDate')
              ->orWhere('EffectiveEndDate', '>=', $now);
        });
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('ProductId', $productId);
    }

    public function scopeBySku($query, $skuId)
    {
        return $query->where('SkuId', $skuId);
    }

    public function scopeByPublisher($query, $publisher)
    {
        return $query->where('Publisher', 'like', '%' . $publisher . '%');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('ProductTitle', 'like', '%' . $term . '%')
              ->orWhere('SkuDescription', 'like', '%' . $term . '%')
              ->orWhere('Publisher', 'like', '%' . $term . '%')
              ->orWhere('SkuTitle', 'like', '%' . $term . '%');
        });
    }

    // Accessors para precios (convertir a número cuando sea posible)
    public function getUnitPriceNumericAttribute()
    {
        return is_numeric($this->UnitPrice) ? (float) $this->UnitPrice : 0;
    }

    public function getERPPriceNumericAttribute()
    {
        return is_numeric($this->ERPPrice) ? (float) $this->ERPPrice : 0;
    }

    /**
     * Genera el catalogItemId para Microsoft Partner Center
     * Formato: ProductId:SkuId:Id (igual que el sistema anterior)
     */
    public function getMicrosoftCatalogItemIdAttribute()
    {
        if (!$this->ProductId || !$this->SkuId || !$this->Id) {
            return null;
        }

        return "{$this->ProductId}:{$this->SkuId}:{$this->Id}";
    }
}
