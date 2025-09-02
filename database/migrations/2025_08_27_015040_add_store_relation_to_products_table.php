<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * SOLO AGREGA campos y indices - NO MODIFICA DATOS EXISTENTES
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Verificar si el campo ya existe antes de agregarlo
            if (!Schema::hasColumn('products', 'store_id')) {
                $table->unsignedBigInteger('store_id')->nullable()->after('prod_idstore');
                $table->index('store_id');
            }

            // Agregar índices para mejorar performance si no existen
            $indexes = [
                'ProductId',
                'SkuId',
                'Publisher',
                'Market',
                'Currency',
                'prod_active',
                'top',
                'bestseller',
                'slide',
                'novelty'
            ];

            foreach ($indexes as $column) {
                if (Schema::hasColumn('products', $column)) {
                    try {
                        $table->index($column);
                    } catch (Exception $e) {
                        // Index might already exist, continue
                    }
                }
            }

            // Índices compuestos para consultas comunes
            try {
                $table->index(['prod_active', 'prod_idstore'], 'idx_active_store');
                $table->index(['Market', 'Currency'], 'idx_market_currency');
                $table->index(['Publisher', 'prod_active'], 'idx_publisher_active');
            } catch (Exception $e) {
                // Indexes might already exist
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Solo eliminar lo que agregamos
            if (Schema::hasColumn('products', 'store_id')) {
                $table->dropIndex(['store_id']);
                $table->dropColumn('store_id');
            }

            // Eliminar índices agregados (si existen)
            $indexesToDrop = [
                'products_ProductId_index',
                'products_SkuId_index',
                'products_Publisher_index',
                'products_Market_index',
                'products_Currency_index',
                'products_prod_active_index',
                'products_top_index',
                'products_bestseller_index',
                'products_slide_index',
                'products_novelty_index',
                'idx_active_store',
                'idx_market_currency',
                'idx_publisher_active'
            ];

            foreach ($indexesToDrop as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Exception $e) {
                    // Index might not exist, continue
                }
            }
        });
    }
};
