<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'locale' => 'en_US',
                'flag_icon' => '🇺🇸',
                'is_rtl' => false,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'es',
                'name' => 'Español',
                'locale' => 'es_MX',
                'flag_icon' => '🇲🇽',
                'is_rtl' => false,
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        foreach ($languages as $language) {
            // Solo insertar si no existe
            $exists = DB::table('languages')->where('code', $language['code'])->exists();
            if (!$exists) {
                DB::table('languages')->insert($language);
                $this->command->info("✅ Language {$language['code']} - {$language['name']} created");
            } else {
                $this->command->info("ℹ️ Language {$language['code']} already exists, skipping");
            }
        }
    }
}
