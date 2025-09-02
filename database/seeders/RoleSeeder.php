<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Super Administrator - Full access to everything
        $superAdmin = Role::firstOrCreate([
            'slug' => 'super-admin'
        ], [
            'name' => 'Super Administrator',
            'description' => 'Full system access with all permissions',
            'is_active' => true,
            'is_system' => true
        ]);

        // Administrator - Most permissions except system-level
        $admin = Role::firstOrCreate([
            'slug' => 'admin'
        ], [
            'name' => 'Administrator',
            'description' => 'Administrator with management permissions',
            'is_active' => true
        ]);

        // Store Manager - Store-specific management
        $storeManager = Role::firstOrCreate([
            'slug' => 'store-manager'
        ], [
            'name' => 'Store Manager',
            'description' => 'Can manage store products, categories and settings',
            'is_active' => true
        ]);

        // Product Manager - Product and category management
        $productManager = Role::firstOrCreate([
            'slug' => 'product-manager'
        ], [
            'name' => 'Product Manager',
            'description' => 'Can manage products and categories',
            'is_active' => true
        ]);

        // Analyst - Read-only access with analytics
        $analyst = Role::firstOrCreate([
            'slug' => 'analyst'
        ], [
            'name' => 'Analyst',
            'description' => 'Read-only access with analytics capabilities',
            'is_active' => true
        ]);

        // Editor - Basic content editing
        $editor = Role::firstOrCreate([
            'slug' => 'editor'
        ], [
            'name' => 'Editor',
            'description' => 'Can edit products and content',
            'is_active' => true
        ]);

        // Viewer - Read-only access
        $viewer = Role::firstOrCreate([
            'slug' => 'viewer'
        ], [
            'name' => 'Viewer',
            'description' => 'Read-only access to system',
            'is_active' => true
        ]);

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles()
    {
        $superAdmin = Role::where('slug', 'super-admin')->first();
        $admin = Role::where('slug', 'admin')->first();
        $storeManager = Role::where('slug', 'store-manager')->first();
        $productManager = Role::where('slug', 'product-manager')->first();
        $analyst = Role::where('slug', 'analyst')->first();
        $editor = Role::where('slug', 'editor')->first();
        $viewer = Role::where('slug', 'viewer')->first();

        // Super Admin gets ALL permissions
        $allPermissions = Permission::all();
        $superAdmin->permissions()->sync($allPermissions->pluck('id'));

        // Admin gets most permissions except user/role management
        $adminPermissions = Permission::whereNotIn('group', ['users', 'roles', 'permissions'])->get();
        $admin->permissions()->sync($adminPermissions->pluck('id'));

        // Store Manager permissions
        $storeManagerPermissions = Permission::whereIn('group', [
            'stores', 'products', 'categories', 'languages', 'currencies', 'translations'
        ])->whereNotIn('slug', [
            'stores.delete'
        ])->get();
        $storeManager->permissions()->sync($storeManagerPermissions->pluck('id'));

        // Product Manager permissions
        $productManagerPermissions = Permission::whereIn('group', [
            'products', 'categories'
        ])->get();
        $productManager->permissions()->sync($productManagerPermissions->pluck('id'));

        // Analyst permissions (read-only + analytics)
        $analystPermissions = Permission::where(function($query) {
            $query->where('slug', 'like', '%.view')
                  ->orWhere('group', 'analytics');
        })->get();
        $analyst->permissions()->sync($analystPermissions->pluck('id'));

        // Editor permissions (can edit but not delete)
        $editorPermissions = Permission::whereIn('group', [
            'products', 'categories', 'translations'
        ])->whereNotIn('slug', [
            'products.delete',
            'categories.delete'
        ])->get();
        $editor->permissions()->sync($editorPermissions->pluck('id'));

        // Viewer permissions (only view permissions)
        $viewerPermissions = Permission::where('slug', 'like', '%.view')->get();
        $viewer->permissions()->sync($viewerPermissions->pluck('id'));
    }
}
