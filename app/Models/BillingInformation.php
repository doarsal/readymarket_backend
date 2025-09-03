<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "BillingInformation",
    type: "object",
    title: "Billing Information",
    description: "Información de facturación del usuario",
    properties: [
        new OA\Property(property: "id", type: "integer", format: "int64", description: "ID único de la información de facturación"),
        new OA\Property(property: "organization", type: "string", maxLength: 255, description: "Nombre de la organización"),
        new OA\Property(property: "rfc", type: "string", maxLength: 15, description: "RFC de la organización"),
        new OA\Property(property: "tax_regime_id", type: "integer", description: "ID del régimen fiscal"),
        new OA\Property(property: "postal_code", type: "string", maxLength: 10, description: "Código postal"),
        new OA\Property(property: "email", type: "string", format: "email", maxLength: 180, description: "Correo electrónico de facturación"),
        new OA\Property(property: "phone", type: "string", maxLength: 15, description: "Teléfono de contacto"),
        new OA\Property(property: "file", type: "string", maxLength: 120, nullable: true, description: "Archivo adjunto (constancia fiscal, etc.)"),
        new OA\Property(property: "active", type: "boolean", description: "Estado activo/inactivo"),
        new OA\Property(property: "config_id", type: "integer", description: "ID de configuración"),
        new OA\Property(property: "store_id", type: "integer", description: "ID de la tienda"),
        new OA\Property(property: "user_id", type: "integer", description: "ID del usuario propietario"),
        new OA\Property(property: "account_id", type: "integer", nullable: true, description: "ID de cuenta asociada"),
        new OA\Property(property: "code", type: "string", maxLength: 20, description: "Código único identificador"),
        new OA\Property(property: "is_default", type: "boolean", description: "Indica si es la información de facturación por defecto"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Fecha de creación"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Fecha de última actualización"),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", nullable: true, description: "Fecha de eliminación (soft delete)")
    ]
)]
class BillingInformation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'billing_information';

    protected $fillable = [
        'organization',
        'rfc',
        'tax_regime_id',
        'postal_code',
        'email',
        'phone',
        'file',
        'active',
        'config_id',
        'store_id',
        'user_id',
        'account_id',
        'code',
        'is_default'
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function taxRegime()
    {
        return $this->belongsTo(TaxRegime::class, 'tax_regime_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
