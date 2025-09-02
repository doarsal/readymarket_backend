<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'id' => 1,
                'name' => 'Microsoft 365',
                'image' => '1/categorias/m365.png',
                'identifier' => 'bp1C56kg',
                'is_active' => true,
                'description' => 'Paquetes completos de productividad Microsoft 365'
            ],
            [
                'id' => 2,
                'name' => 'Suscripción y Perpetuo',
                'image' => '1/categorias/msp.png',
                'identifier' => 'Pq1YUjp',
                'is_active' => true,
                'description' => 'Licencias de suscripción y perpetuas'
            ],
            [
                'id' => 3,
                'name' => 'Microsoft Azure',
                'image' => '1/categorias/azure.png',
                'identifier' => 'h5njp3X',
                'is_active' => true,
                'description' => 'Servicios en la nube Microsoft Azure'
            ],
            [
                'id' => 4,
                'name' => 'Dynamics 365',
                'image' => '1/categorias/Dynamics-365-logo-2.png',
                'identifier' => 'iFGHJ1P8',
                'is_active' => true,
                'description' => 'Soluciones empresariales Dynamics 365'
            ]
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['identifier' => $category['identifier']],
                $category
            );
        }
    }
}
