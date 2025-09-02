<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Users Management
            [
                'name' => 'View Users',
                'slug' => 'users.view',
                'group' => 'users',
                'description' => 'Can view users list and details'
            ],
            [
                'name' => 'Create Users',
                'slug' => 'users.create',
                'group' => 'users',
                'description' => 'Can create new users'
            ],
            [
                'name' => 'Edit Users',
                'slug' => 'users.edit',
                'group' => 'users',
                'description' => 'Can edit existing users'
            ],
            [
                'name' => 'Delete Users',
                'slug' => 'users.delete',
                'group' => 'users',
                'description' => 'Can delete users'
            ],
            [
                'name' => 'Manage User Roles',
                'slug' => 'users.roles',
                'group' => 'users',
                'description' => 'Can assign/remove roles to users'
            ],

            // Roles Management
            [
                'name' => 'View Roles',
                'slug' => 'roles.view',
                'group' => 'roles',
                'description' => 'Can view roles list and details'
            ],
            [
                'name' => 'Create Roles',
                'slug' => 'roles.create',
                'group' => 'roles',
                'description' => 'Can create new roles'
            ],
            [
                'name' => 'Edit Roles',
                'slug' => 'roles.edit',
                'group' => 'roles',
                'description' => 'Can edit existing roles'
            ],
            [
                'name' => 'Delete Roles',
                'slug' => 'roles.delete',
                'group' => 'roles',
                'description' => 'Can delete roles'
            ],
            [
                'name' => 'Manage Role Permissions',
                'slug' => 'roles.permissions',
                'group' => 'roles',
                'description' => 'Can assign/remove permissions to roles'
            ],

            // Permissions Management
            [
                'name' => 'View Permissions',
                'slug' => 'permissions.view',
                'group' => 'permissions',
                'description' => 'Can view permissions list'
            ],
            [
                'name' => 'Create Permissions',
                'slug' => 'permissions.create',
                'group' => 'permissions',
                'description' => 'Can create new permissions'
            ],
            [
                'name' => 'Edit Permissions',
                'slug' => 'permissions.edit',
                'group' => 'permissions',
                'description' => 'Can edit existing permissions'
            ],
            [
                'name' => 'Delete Permissions',
                'slug' => 'permissions.delete',
                'group' => 'permissions',
                'description' => 'Can delete permissions'
            ],

            // Products Management
            [
                'name' => 'View Products',
                'slug' => 'products.view',
                'group' => 'products',
                'description' => 'Can view products list and details'
            ],
            [
                'name' => 'Create Products',
                'slug' => 'products.create',
                'group' => 'products',
                'description' => 'Can create new products'
            ],
            [
                'name' => 'Edit Products',
                'slug' => 'products.edit',
                'group' => 'products',
                'description' => 'Can edit existing products'
            ],
            [
                'name' => 'Delete Products',
                'slug' => 'products.delete',
                'group' => 'products',
                'description' => 'Can delete products'
            ],

            // Categories Management
            [
                'name' => 'View Categories',
                'slug' => 'categories.view',
                'group' => 'categories',
                'description' => 'Can view categories list and details'
            ],
            [
                'name' => 'Create Categories',
                'slug' => 'categories.create',
                'group' => 'categories',
                'description' => 'Can create new categories'
            ],
            [
                'name' => 'Edit Categories',
                'slug' => 'categories.edit',
                'group' => 'categories',
                'description' => 'Can edit existing categories'
            ],
            [
                'name' => 'Delete Categories',
                'slug' => 'categories.delete',
                'group' => 'categories',
                'description' => 'Can delete categories'
            ],

            // Stores Management
            [
                'name' => 'View Stores',
                'slug' => 'stores.view',
                'group' => 'stores',
                'description' => 'Can view stores list and details'
            ],
            [
                'name' => 'Create Stores',
                'slug' => 'stores.create',
                'group' => 'stores',
                'description' => 'Can create new stores'
            ],
            [
                'name' => 'Edit Stores',
                'slug' => 'stores.edit',
                'group' => 'stores',
                'description' => 'Can edit existing stores'
            ],
            [
                'name' => 'Delete Stores',
                'slug' => 'stores.delete',
                'group' => 'stores',
                'description' => 'Can delete stores'
            ],
            [
                'name' => 'Manage Store Config',
                'slug' => 'stores.config',
                'group' => 'stores',
                'description' => 'Can manage store configurations'
            ],

            // Analytics
            [
                'name' => 'View Analytics',
                'slug' => 'analytics.view',
                'group' => 'analytics',
                'description' => 'Can view analytics dashboard'
            ],
            [
                'name' => 'View Detailed Analytics',
                'slug' => 'analytics.detailed',
                'group' => 'analytics',
                'description' => 'Can view detailed analytics and reports'
            ],

            // System Settings
            [
                'name' => 'View Settings',
                'slug' => 'settings.view',
                'group' => 'settings',
                'description' => 'Can view system settings'
            ],
            [
                'name' => 'Edit Settings',
                'slug' => 'settings.edit',
                'group' => 'settings',
                'description' => 'Can edit system settings'
            ],

            // Languages Management
            [
                'name' => 'View Languages',
                'slug' => 'languages.view',
                'group' => 'languages',
                'description' => 'Can view languages list'
            ],
            [
                'name' => 'Manage Languages',
                'slug' => 'languages.manage',
                'group' => 'languages',
                'description' => 'Can create, edit, delete languages'
            ],

            // Currencies Management
            [
                'name' => 'View Currencies',
                'slug' => 'currencies.view',
                'group' => 'currencies',
                'description' => 'Can view currencies list'
            ],
            [
                'name' => 'Manage Currencies',
                'slug' => 'currencies.manage',
                'group' => 'currencies',
                'description' => 'Can create, edit, delete currencies'
            ],

            // Translations Management
            [
                'name' => 'View Translations',
                'slug' => 'translations.view',
                'group' => 'translations',
                'description' => 'Can view translations'
            ],
            [
                'name' => 'Manage Translations',
                'slug' => 'translations.manage',
                'group' => 'translations',
                'description' => 'Can create, edit, delete translations'
            ]
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }
}
