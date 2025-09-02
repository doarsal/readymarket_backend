<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // 1. Crear la tienda México
            $storeExists = DB::table('stores')->where('slug', 'mexico')->exists();

            if ($storeExists) {
                $this->command->info("ℹ️ Store 'México' already exists, skipping creation");
                DB::rollBack();
                return;
            }

            $storeId = DB::table('stores')->insertGetId([
                'name' => 'México',
                'slug' => 'mexico',
                'domain' => null,
                'subdomain' => 'mexico',
                'default_language' => 'es',
                'default_currency' => 'MXN',
                'timezone' => 'America/Mexico_City',
                'is_active' => true,
                'is_maintenance' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("✅ Store 'México' created with ID: {$storeId}");

            // 2. Obtener IDs de idiomas
            $languages = DB::table('languages')->whereIn('code', ['es', 'en'])->get();
            $spanishId = $languages->where('code', 'es')->first()->id ?? null;
            $englishId = $languages->where('code', 'en')->first()->id ?? null;

            // 3. Configurar idiomas de la tienda
            if ($spanishId) {
                DB::table('store_languages')->insert([
                    'store_id' => $storeId,
                    'language_id' => $spanishId,
                    'is_default' => true,
                    'is_active' => true,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("✅ Spanish language configured as default for store");
            }

            if ($englishId) {
                DB::table('store_languages')->insert([
                    'store_id' => $storeId,
                    'language_id' => $englishId,
                    'is_default' => false,
                    'is_active' => true,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("✅ English language configured for store");
            }

            // 4. Obtener IDs de monedas
            $currencies = DB::table('currencies')->whereIn('code', ['MXN', 'USD'])->get();
            $mxnId = $currencies->where('code', 'MXN')->first()->id ?? null;
            $usdId = $currencies->where('code', 'USD')->first()->id ?? null;

            // 5. Configurar monedas de la tienda
            if ($mxnId) {
                DB::table('store_currencies')->insert([
                    'store_id' => $storeId,
                    'currency_id' => $mxnId,
                    'is_default' => true,
                    'is_active' => true,
                    'sort_order' => 1,
                    'auto_update_rate' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("✅ MXN currency configured as default for store");
            }

            if ($usdId) {
                DB::table('store_currencies')->insert([
                    'store_id' => $storeId,
                    'currency_id' => $usdId,
                    'is_default' => false,
                    'is_active' => true,
                    'sort_order' => 2,
                    'auto_update_rate' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("✅ USD currency configured for store");
            }

            // 6. Configuraciones básicas de la tienda
            $configurations = [
                ['category' => 'general', 'key_name' => 'store_name', 'value' => 'Marketplace México', 'type' => 'string', 'is_public' => true],
                ['category' => 'general', 'key_name' => 'store_description', 'value' => 'Marketplace oficial de Microsoft para México', 'type' => 'text', 'is_public' => true],
                ['category' => 'general', 'key_name' => 'contact_email', 'value' => 'contacto@marketplace-mexico.com', 'type' => 'string', 'is_public' => true],
                ['category' => 'general', 'key_name' => 'support_phone', 'value' => '+52 55 1234 5678', 'type' => 'string', 'is_public' => true],
                ['category' => 'appearance', 'key_name' => 'theme_color', 'value' => '#0078d4', 'type' => 'string', 'is_public' => true],
                ['category' => 'appearance', 'key_name' => 'logo_url', 'value' => '/assets/logos/mexico-logo.png', 'type' => 'url', 'is_public' => true],
                ['category' => 'payment', 'key_name' => 'tax_rate', 'value' => '16', 'type' => 'integer', 'is_public' => false],
                ['category' => 'payment', 'key_name' => 'tax_name', 'value' => 'IVA', 'type' => 'string', 'is_public' => true],
                ['category' => 'localization', 'key_name' => 'country_code', 'value' => 'MX', 'type' => 'string', 'is_public' => true],
                ['category' => 'localization', 'key_name' => 'date_format', 'value' => 'd/m/Y', 'type' => 'string', 'is_public' => true],
            ];

            foreach ($configurations as $config) {
                DB::table('store_configurations')->insert([
                    'store_id' => $storeId,
                    'category' => $config['category'],
                    'key_name' => $config['key_name'],
                    'value' => $config['value'],
                    'type' => $config['type'],
                    'is_public' => $config['is_public'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command->info("✅ Store configurations created successfully");

            DB::commit();
            $this->command->info("🎉 Store 'México' created successfully with all configurations!");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ Error creating store: " . $e->getMessage());
            throw $e;
        }
    }
}
