<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 🛡️ PROTECCIÓN: Solo crea datos, NO elimina existentes
     */
    public function run(): void
    {
        $this->command->info('🛡️ MODO SEGURO: Solo creando datos nuevos, preservando existentes');

        // Verificar datos existentes antes de sembrar
        $this->checkExistingData();

        $this->call([
            // RBAC System seeders (must be first)
            PermissionSeeder::class,
            RoleSeeder::class,

            // Data seeders - SOLO si no existen datos
            CategorySeeder::class,
            ActivitySeeder::class,
            // ProductSeeder::class, // COMENTADO - No crear productos automáticamente
        ]);

        // Create admin user SOLO si no existe
        $existingAdmin = User::where('email', 'admin@marketplace.com')->first();
        if (!$existingAdmin) {
            $this->command->info('👤 Creando usuario administrador...');
            $adminUser = User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@marketplace.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'is_active' => true
            ]);

            // Assign super-admin role to admin user
            $superAdminRole = \App\Models\Role::where('slug', 'super-admin')->first();
            if ($superAdminRole) {
                $adminUser->assignRole($superAdminRole->id);
                $this->command->info('🔑 Rol super-admin asignado al usuario administrador');
            }
        } else {
            $this->command->info('👤 Usuario administrador ya existe, omitiendo creación');
        }

        $this->command->info('✅ Seeders completados de forma segura');
    }

    /**
     * Verificar datos existentes y mostrar resumen
     */
    private function checkExistingData()
    {
        $tables = ['users', 'categories', 'products', 'roles', 'permissions'];

        foreach ($tables as $table) {
            if (\Schema::hasTable($table)) {
                $count = \DB::table($table)->count();
                $this->command->info("📊 {$table}: {$count} registros existentes");
            }
        }
    }
}
