<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="MicrosoftAccount",
 *     type="object",
 *     title="Microsoft Account",
 *     description="Microsoft Account model",
 *     @OA\Property(property="id", type="integer", format="int64", description="Account ID"),
 *     @OA\Property(property="user_id", type="integer", format="int64", description="User ID"),
 *     @OA\Property(property="microsoft_id", type="string", nullable=true, description="Microsoft Partner Center ID"),
 *     @OA\Property(property="domain", type="string", description="Domain name"),
 *     @OA\Property(property="domain_concatenated", type="string", description="Full Microsoft domain"),
 *     @OA\Property(property="first_name", type="string", description="First name"),
 *     @OA\Property(property="last_name", type="string", description="Last name"),
 *     @OA\Property(property="email", type="string", format="email", description="Email address"),
 *     @OA\Property(property="phone", type="string", description="Phone number"),
 *     @OA\Property(property="organization", type="string", description="Organization name"),
 *     @OA\Property(property="address", type="string", description="Address"),
 *     @OA\Property(property="city", type="string", description="City"),
 *     @OA\Property(property="state_code", type="string", description="State code"),
 *     @OA\Property(property="state_name", type="string", description="State name"),
 *     @OA\Property(property="postal_code", type="string", description="Postal code"),
 *     @OA\Property(property="country_code", type="string", description="Country code"),
 *     @OA\Property(property="country_name", type="string", description="Country name"),
 *     @OA\Property(property="language_code", type="string", description="Language code"),
 *     @OA\Property(property="culture", type="string", description="Culture code"),
 *     @OA\Property(property="is_active", type="boolean", description="Is account active"),
 *     @OA\Property(property="is_default", type="boolean", description="Is default account"),
 *     @OA\Property(property="is_current", type="boolean", description="Is current account"),
 *     @OA\Property(property="is_pending", type="boolean", description="Is account pending"),
 *     @OA\Property(property="configuration_id", type="integer", nullable=true, description="Configuration ID"),
 *     @OA\Property(property="store_id", type="integer", nullable=true, description="Store ID"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Created at"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Updated at"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, description="Deleted at")
 * )
 */
class MicrosoftAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'microsoft_id',
        'domain',
        'domain_concatenated',
        'first_name',
        'last_name',
        'email',
        'phone',
        'organization',
        'address',
        'city',
        'state_code',
        'state_name',
        'postal_code',
        'country_code',
        'country_name',
        'language_code',
        'culture',
        'is_active',
        'is_default',
        'is_current',
        'is_pending',
        'configuration_id',
        'store_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_current' => 'boolean',
        'is_pending' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $appends = [
        'full_name',
        'admin_email',
        'status_text',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getAdminEmailAttribute(): string
    {
        return 'admin@' . $this->domain_concatenated;
    }

    public function getStatusTextAttribute(): string
    {
        if ($this->is_pending) {
            return 'Pendiente';
        }
        if (!$this->is_active) {
            return 'Inactiva';
        }
        return 'Activa';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_pending', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForUserConfig($query, int $userId, int $configId, int $storeId)
    {
        return $query->where('user_id', $userId)
                    ->where('configuration_id', $configId)
                    ->where('store_id', $storeId);
    }

    // Métodos de utilidad
    public function markAsDefault(): bool
    {
        // Primero quitar el default de todas las otras cuentas del usuario
        static::where('user_id', $this->user_id)
              ->where('configuration_id', $this->configuration_id)
              ->where('store_id', $this->store_id)
              ->where('id', '!=', $this->id)
              ->update(['is_default' => false]);

        // Marcar esta como default
        return $this->update(['is_default' => true]);
    }

    public function activate(): bool
    {
        return $this->update([
            'is_active' => true,
            'is_pending' => false,
        ]);
    }

    public function formatDomain(string $rawDomain): string
    {
        // Limpiar el dominio como en el sistema viejo
        $search = ['http://www.', 'https://www.', 'www.', 'http://', 'https://', 'ftp://'];
        $replace = ['', '', '', '', '', ''];
        $cleanDomain = str_replace($search, $replace, $rawDomain);

        $parts = explode('.', $cleanDomain);
        return $parts[0] ?? '';
    }

    public function generateDomainConcatenated(string $domain): string
    {
        $cleanDomain = $this->formatDomain($domain);
        return $cleanDomain . '.onmicrosoft.com';
    }

    // Validaciones
    public function isDomainAvailable(string $domain, ?int $userId = null, ?int $excludeId = null): bool
    {
        $query = static::where('domain', $domain);

        // Si se proporciona userId, filtrar por usuario específico
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->count() === 0;
    }

    // Eventos del modelo
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            // Auto-generar domain_concatenated
            if ($account->domain && !$account->domain_concatenated) {
                $account->domain_concatenated = $account->generateDomainConcatenated($account->domain);
            }
        });

        static::updating(function ($account) {
            // Actualizar domain_concatenated si cambia el domain
            if ($account->isDirty('domain')) {
                $account->domain_concatenated = $account->generateDomainConcatenated($account->domain);
            }
        });
    }
}
