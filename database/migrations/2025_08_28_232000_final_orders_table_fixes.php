<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function(Blueprint $table) {
            // 1. Agregar foreign key para currency_id (la columna ya existe)
            $table->unsignedBigInteger('currency_id')->nullable()->after('store_id');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');

            // 2. Agregar fecha del tipo de cambio
            if (DB::connection()->getDriverName() === 'mysql') {
                $table->timestamp('exchange_rate_date')->nullable()->after('exchange_rate');
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            // 3. Actualizar ENUMs para productos digitales
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','completed','cancelled','refunded') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function(Blueprint $table) {
            // Revertir foreign key
            $table->dropForeign(['currency_id']);

            // Quitar columna
            $table->dropColumn('exchange_rate_date');
        });

        // Revertir ENUMs
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending'");
    }
};
