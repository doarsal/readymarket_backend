<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'MXN',
                'name' => 'Peso Mexicano',
                'symbol' => '$',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        foreach ($currencies as $currency) {
            // Solo insertar si no existe
            $exists = DB::table('currencies')->where('code', $currency['code'])->exists();
            if (!$exists) {
                DB::table('currencies')->insert($currency);
                $this->command->info("✅ Currency {$currency['code']} - {$currency['name']} created");
            } else {
                $this->command->info("ℹ️ Currency {$currency['code']} already exists, skipping");
            }
        }
    }
}
