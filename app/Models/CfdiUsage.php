<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "CfdiUsage",
    type: "object",
    title: "CFDI Usage",
    description: "Uso de CFDI según el catálogo del SAT México",
    properties: [
        new OA\Property(property: "id", type: "integer", format: "int64", description: "ID único del uso de CFDI"),
        new OA\Property(property: "code", type: "string", maxLength: 5, description: "Código SAT del uso de CFDI (ej: G01, D01, etc.)"),
        new OA\Property(property: "description", type: "string", maxLength: 255, description: "Descripción del uso de CFDI"),
        new OA\Property(property: "applies_to_physical", type: "boolean", description: "Si aplica para personas físicas"),
        new OA\Property(property: "applies_to_moral", type: "boolean", description: "Si aplica para personas morales"),
        new OA\Property(property: "applicable_tax_regimes", type: "array", items: new OA\Items(type: "string"), description: "Códigos de regímenes fiscales aplicables (JSON)"),
        new OA\Property(property: "active", type: "boolean", description: "Estado activo/inactivo"),
        new OA\Property(property: "store_id", type: "integer", nullable: true, description: "ID de la tienda"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Fecha de creación"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Fecha de actualización"),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", nullable: true, description: "Fecha de eliminación")
    ]
)]
class CfdiUsage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'description',
        'applies_to_physical',
        'applies_to_moral',
        'applicable_tax_regimes',
        'active',
        'store_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'applies_to_physical' => 'boolean',
        'applies_to_moral' => 'boolean',
        'applicable_tax_regimes' => 'json',
        'active' => 'boolean',
    ];

    /**
     * Get the store that owns this CFDI usage.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get all tax regimes associated with this CFDI usage.
     */
    public function taxRegimes()
    {
        return $this->belongsToMany(TaxRegime::class, 'tax_regime_cfdi_usage')
                    ->withPivot('active')
                    ->withTimestamps();
    }

    /**
     * Get all billing information that uses this CFDI usage.
     */
    public function billingInformation()
    {
        return $this->hasMany(BillingInformation::class);
    }

    /**
     * Scope a query to only include active CFDI usages.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to filter by person type (physical or moral)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $isPhysical
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPersonType($query, bool $isPhysical = true)
    {
        if ($isPhysical) {
            return $query->where('applies_to_physical', true);
        } else {
            return $query->where('applies_to_moral', true);
        }
    }

    /**
     * Get CFDI usages compatible with a specific tax regime code
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $taxRegimeCode
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompatibleWithTaxRegime($query, $taxRegimeCode)
    {
        return $query->whereJsonContains('applicable_tax_regimes', $taxRegimeCode);
    }
}
