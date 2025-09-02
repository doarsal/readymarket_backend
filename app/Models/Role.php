<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Role",
 *     type="object",
 *     title="Role",
 *     description="Role model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Administrator"),
 *     @OA\Property(property="slug", type="string", example="admin"),
 *     @OA\Property(property="description", type="string", example="Full system access"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="is_system", type="boolean", example=false),
 *     @OA\Property(property="permissions", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'is_active',
        'is_system'
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * Get the users that belong to this role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user')
                    ->withPivot('store_id')
                    ->withTimestamps();
    }

    /**
     * Get the permissions that belong to this role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
                    ->withTimestamps();
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions->contains('slug', $permissionSlug);
    }

    /**
     * Assign permission to role.
     */
    public function givePermission($permissionId)
    {
        return $this->permissions()->attach($permissionId);
    }

    /**
     * Remove permission from role.
     */
    public function revokePermission($permissionId)
    {
        return $this->permissions()->detach($permissionId);
    }

    /**
     * Sync permissions for this role.
     */
    public function syncPermissions(array $permissionIds)
    {
        $this->permissions()->sync($permissionIds);

        // Update the permissions cache
        $this->update([
            'permissions' => $this->permissions->pluck('slug')->toArray()
        ]);
    }

    /**
     * Scope for active roles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for system roles.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope for non-system roles.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }
}
