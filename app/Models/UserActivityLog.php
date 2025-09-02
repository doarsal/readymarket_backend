<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UserActivityLog",
    type: "object",
    title: "User Activity Log",
    description: "Registro de actividades realizadas por usuarios",
    properties: [
        new OA\Property(property: "id", type: "integer", format: "int64", description: "ID único del log"),
        new OA\Property(property: "activity_id", type: "integer", description: "ID de la actividad"),
        new OA\Property(property: "user_id", type: "integer", description: "ID del usuario"),
        new OA\Property(property: "module", type: "string", maxLength: 120, nullable: true, description: "Módulo donde se ejecutó"),
        new OA\Property(property: "title", type: "string", maxLength: 120, nullable: true, description: "Título descriptivo"),
        new OA\Property(property: "reference_id", type: "string", maxLength: 255, nullable: true, description: "ID de referencia"),
        new OA\Property(property: "metadata", type: "object", nullable: true, description: "Datos adicionales"),
        new OA\Property(property: "ip_address", type: "string", nullable: true, description: "Dirección IP"),
        new OA\Property(property: "user_agent", type: "string", nullable: true, description: "User Agent del navegador"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Fecha de creación"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Fecha de actualización"),
        new OA\Property(
            property: "activity",
            ref: "#/components/schemas/Activity",
            description: "Información de la actividad"
        ),
        new OA\Property(
            property: "user",
            ref: "#/components/schemas/User",
            description: "Información del usuario"
        )
    ]
)]
class UserActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'user_id',
        'module',
        'title',
        'reference_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $with = ['activity', 'user'];

    /**
     * Relación con la actividad
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para filtrar por usuario
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para filtrar por actividad
     */
    public function scopeForActivity($query, $activityId)
    {
        return $query->where('activity_id', $activityId);
    }

    /**
     * Scope para filtrar por módulo
     */
    public function scopeForModule($query, $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        $query->whereDate('created_at', '>=', $startDate);

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Obtener logs recientes del usuario
     */
    public static function getRecentForUser($userId, $limit = 10)
    {
        return static::forUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Crear log de actividad de forma estática
     */
    public static function logActivity(
        int $activityId,
        int $userId,
        ?string $module = null,
        ?string $title = null,
        ?string $referenceId = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'activity_id' => $activityId,
            'user_id' => $userId,
            'module' => $module,
            'title' => $title,
            'reference_id' => $referenceId,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
