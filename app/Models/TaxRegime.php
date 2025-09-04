<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "TaxRegime",
    type: "object",
    title: "Tax Regime",
    description: "Regímenes fiscales del SAT para México",
    properties: [
        new OA\Property(property: "id", type: "integer", format: "int64", description: "ID único del régimen fiscal"),
        new OA\Property(property: "sat_code", type: "integer", nullable: true, description: "Código del SAT"),
        new OA\Property(property: "name", type: "string", maxLength: 120, nullable: true, description: "Nombre del régimen fiscal"),
        new OA\Property(property: "relation", type: "integer", nullable: true, description: "Relación con otros regímenes"),
        new OA\Property(property: "store_id", type: "integer", nullable: true, description: "ID de la tienda"),
        new OA\Property(property: "active", type: "boolean", description: "Estado activo/inactivo"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Fecha de creación"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Fecha de actualización"),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", nullable: true, description: "Fecha de eliminación")
    ]
)]
class TaxRegime extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sat_code',
        'name',
        'relation',
        'store_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sat_code' => 'integer',
        'relation' => 'integer',
        'store_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Scopes para consultas comunes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeBySatCode($query, $satCode)
    {
        return $query->where('sat_code', $satCode);
    }

    // Relaciones
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cfdiUsages()
    {
        return $this->belongsToMany(CfdiUsage::class, 'tax_regime_cfdi_usage')
                    ->withPivot('active')
                    ->withTimestamps();
    }

    public function billingInformation()
    {
        return $this->hasMany(BillingInformation::class);
    }

    // Métodos helper
    public function getFormattedNameAttribute(): string
    {
        return $this->sat_code ? "{$this->sat_code} - {$this->name}" : $this->name ?? '';
    }

    public function isActive(): bool
    {
        return $this->active === true;
    }
}
