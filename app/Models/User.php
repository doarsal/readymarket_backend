<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="first_name", type="string", example="John"),
 *     @OA\Property(property="last_name", type="string", example="Doe"),
 *     @OA\Property(property="full_name", type="string", example="John Doe", description="Computed field combining first_name and last_name"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+1234567890"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="is_verified", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="roles", type="array", @OA\Items(type="object"))
 * )
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'avatar',
        'is_active',
        'is_verified',
        'last_login_at',
        'last_login_ip',
        'timezone',
        'locale',
        'failed_login_attempts',
        'locked_until',
        'two_factor_enabled',
        'two_factor_secret',
        'role',
        'permissions',
        'preferences',
        'password_changed_at',
        'force_password_change',
        'terms_accepted_at',
        'created_by_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'force_password_change' => 'boolean',
            'permissions' => 'array',
            'preferences' => 'array',
            'failed_login_attempts' => 'integer',
        ];
    }

    /**
     * Get the roles for the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')
                    ->withPivot('store_id')
                    ->withTimestamps();
    }

    /**
     * Get the payment cards for the user.
     */
    public function paymentCards()
    {
        return $this->hasMany(PaymentCard::class);
    }

    /**
     * Get the active payment cards for the user.
     */
    public function activePaymentCards()
    {
        return $this->hasMany(PaymentCard::class)->where('is_active', true);
    }

    /**
     * Get the default payment card for the user.
     */
    public function defaultPaymentCard()
    {
        return $this->hasOne(PaymentCard::class)->where('is_default', true)->where('is_active', true);
    }

    /**
     * Get all permissions for the user through roles.
     */
    public function permissions()
    {
        return $this->roles->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id');
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions()->contains('slug', $permissionSlug);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles->contains('slug', $roleSlug);
    }

    /**
     * Get user roles for a specific store.
     */
    public function rolesForStore($storeId = null)
    {
        if ($storeId) {
            return $this->roles()->wherePivot('store_id', $storeId)->get();
        }

        return $this->roles()->whereNull('role_user.store_id')->get();
    }

    /**
     * Assign role to user.
     */
    public function assignRole($roleId, $storeId = null)
    {
        return $this->roles()->attach($roleId, ['store_id' => $storeId]);
    }

    /**
     * Remove role from user.
     */
    public function removeRole($roleId, $storeId = null)
    {
        $query = $this->roles()->wherePivot('role_id', $roleId);

        if ($storeId) {
            $query->wherePivot('store_id', $storeId);
        } else {
            $query->whereNull('role_user.store_id');
        }

        return $query->detach();
    }

    /**
     * Get full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get name attribute for backward compatibility.
     */
    public function getNameAttribute(): string
    {
        return $this->getFullNameAttribute();
    }

    // ======= MÉTODOS DE SEGURIDAD =======

    /**
     * Check if user account is locked
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if user account is active
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->isLocked();
    }

    /**
     * Lock user account for specified minutes
     */
    public function lockAccount(int $minutes = 30): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes),
        ]);
    }

    /**
     * Unlock user account
     */
    public function unlockAccount(): void
    {
        $this->update([
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedLogins(): void
    {
        $attempts = $this->failed_login_attempts + 1;

        $this->update(['failed_login_attempts' => $attempts]);

        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $this->lockAccount(30); // 30 minutes lock
        }
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedLogins(): void
    {
        $this->update(['failed_login_attempts' => 0]);
    }

    /**
     * Update last login information
     */
    public function updateLastLogin(string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip ?? request()->ip(),
        ]);
    }

    /**
     * Check if password needs to be changed
     */
    public function needsPasswordChange(): bool
    {
        if ($this->force_password_change) {
            return true;
        }

        // Force password change after 90 days
        if ($this->password_changed_at) {
            return $this->password_changed_at->addDays(90)->isPast();
        }

        return false;
    }

    /**
     * Mark password as changed
     */
    public function markPasswordChanged(): void
    {
        $this->update([
            'password_changed_at' => now(),
            'force_password_change' => false,
        ]);
    }

    /**
     * Check if user has specific role
     */
    public function hasSimpleRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasSimpleRole('admin');
    }

    /**
     * Check if user is manager
     */
    public function isManager(): bool
    {
        return $this->hasSimpleRole('manager') || $this->isAdmin();
    }

    // ======= MÉTODOS DE SOFT DELETE =======

    /**
     * Soft delete user account
     */
    public function softDeleteAccount(): bool
    {
        $this->update(['is_active' => false]);
        return $this->delete(); // Soft delete
    }

    /**
     * Restore soft deleted user
     */
    public function restoreAccount(): bool
    {
        $this->restore();
        return $this->update(['is_active' => true]);
    }

    /**
     * Permanently delete user and all related data
     */
    public function permanentDeleteAccount(): bool
    {
        // Delete related data first
        $this->tokens()->delete(); // Delete all tokens

        // Delete related records if any
        // $this->microsoftAccounts()->delete(); // If user has microsoft accounts

        return $this->forceDelete(); // Permanent delete
    }

    /**
     * Check if user can be permanently deleted
     */
    public function canBePermanentlyDeleted(): bool
    {
        // Add business logic here
        // For example, check if user has important data that shouldn't be deleted
        return $this->trashed(); // Only allow permanent delete if already soft deleted
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
