<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Activity",
    type: "object",
    title: "Activity",
    description: "Catálogo de actividades del marketplace",
    properties: [
        new OA\Property(property: "id", type: "integer", format: "int64", description: "ID único de la actividad"),
        new OA\Property(property: "name", type: "string", maxLength: 180, description: "Nombre de la actividad"),
        new OA\Property(property: "description", type: "string", description: "Descripción de la actividad"),
        new OA\Property(property: "icon", type: "string", maxLength: 45, nullable: true, description: "Clase de icono FontAwesome"),
        new OA\Property(property: "active", type: "boolean", description: "Estado activo/inactivo de la actividad"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Fecha de creación"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Fecha de última actualización"),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", nullable: true, description: "Fecha de eliminación (soft delete)")
    ]
)]
class Activity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [];

    /**
     * Scope to get only active activities
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get only inactive activities
     */
    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }

    /**
     * Get formatted icon with prefix if needed
     */
    public function getFormattedIconAttribute(): ?string
    {
        if (!$this->icon) {
            return null;
        }

        // Add 'fa ' prefix if not already present
        return str_starts_with($this->icon, 'fa ') ? $this->icon : 'fa ' . $this->icon;
    }
}
