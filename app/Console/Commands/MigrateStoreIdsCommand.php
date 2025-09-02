<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateStoreIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:migrate-store-ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from prod_idstore to store_id field in products table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Migrando datos de prod_idstore a store_id...');

        try {
            // Contar registros a migrar
            $count = DB::table('products')
                ->whereNotNull('prod_idstore')
                ->whereNull('store_id')
                ->count();

            $this->info("📊 Registros a migrar: $count");

            if ($count > 0) {
                // Migrar datos
                $updated = DB::table('products')
                    ->whereNotNull('prod_idstore')
                    ->whereNull('store_id')
                    ->update(['store_id' => DB::raw('prod_idstore')]);

                $this->info("✅ Migrados: $updated registros");

                // Verificar migración con consulta más específica
                $verified = DB::table('products')
                    ->whereColumn('prod_idstore', 'store_id')
                    ->count();

                $this->info("🔍 Verificados: $verified registros coinciden");

                // Mostrar algunos ejemplos para debug
                $samples = DB::table('products')
                    ->select('idproduct', 'prod_idstore', 'store_id')
                    ->whereNotNull('store_id')
                    ->limit(5)
                    ->get();

                $this->info("� Ejemplos de migración:");
                foreach ($samples as $sample) {
                    $this->info("  ID: {$sample->idproduct} | prod_idstore: {$sample->prod_idstore} | store_id: {$sample->store_id}");
                }

                if ($verified == $updated) {
                    $this->info('🎉 Migración completada exitosamente!');
                    return Command::SUCCESS;
                } else {
                    $this->error('⚠️ Algunos registros no coinciden');
                    return Command::FAILURE;
                }
            } else {
                $this->info('ℹ️ No hay registros para migrar');
                return Command::SUCCESS;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error en migración: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
