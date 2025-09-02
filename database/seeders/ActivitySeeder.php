<?php

namespace Database\Seeders;

use App\Models\Activity;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activities = [
            [
                'name' => 'Nueva compra',
                'description' => 'realiza una nueva compra',
                'icon' => 'fa-shopping-cart',
                'active' => true,
            ],
            [
                'name' => 'Nueva cuenta Microsoft',
                'description' => 'agrego tu cuenta Microsoft',
                'icon' => 'fa-windows',
                'active' => true,
            ],
            [
                'name' => 'Datos de la cuenta Microsoft',
                'description' => 'edito información de la cuenta Microsoft',
                'icon' => 'fa-edit',
                'active' => true,
            ],
            [
                'name' => 'Nuevos datos de facturación',
                'description' => 'agrego tus datos de facturación',
                'icon' => 'fa-file-invoice',
                'active' => true,
            ],
            [
                'name' => 'Edición de datos de facturación',
                'description' => 'edito la información de los datos de facturación',
                'icon' => 'fa-edit',
                'active' => true,
            ],
            [
                'name' => 'Editor de perfil personal',
                'description' => 'edito la información de tu perfil personal',
                'icon' => 'fa-user-edit',
                'active' => true,
            ],
            [
                'name' => 'Eliminación de registro',
                'description' => 'elimino tu registro',
                'icon' => 'fa-trash',
                'active' => true,
            ],
            [
                'name' => 'Eliminación de datos de facturación',
                'description' => 'elimino tus datos de facturación',
                'icon' => 'fa-file-times',
                'active' => true,
            ],
            [
                'name' => 'Verificación de perfil personal',
                'description' => 'verifico tu perfil personal por medio de correo electrónico',
                'icon' => 'fa-check-circle',
                'active' => true,
            ],
            [
                'name' => 'Registro en HexlyMaster',
                'description' => 'te registré en HexlyMaster',
                'icon' => 'fa-user-plus',
                'active' => true,
            ],
        ];

        foreach ($activities as $activity) {
            Activity::firstOrCreate(
                ['name' => $activity['name']],
                $activity
            );
        }
    }
}
