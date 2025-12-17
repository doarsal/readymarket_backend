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
        // Solo verificar si el campo currency existe y crear currency_id
        if (Schema::hasColumn('order_items', 'currency')) {
            // 1. Agregar currency_id temporal
            Schema::table('order_items', function(Blueprint $table) {
                $table->unsignedBigInteger('currency_id_temp')->nullable()->after('discount_amount');
            });

            if (DB::connection()->getDriverName() === 'mysql') {
                // 2. Migrar datos existentes
                DB::statement("
                UPDATE order_items oi
                LEFT JOIN currencies C ON C.code = oi.currency
                SET oi.currency_id_temp = COALESCE(C.id, 1)
            ");
            }

            // 3. Asignar USD por defecto a registros nulos
            DB::table('order_items')->whereNull('currency_id_temp')->update(['currency_id_temp' => 1]);

            // 4. Eliminar campo currency
            Schema::table('order_items', function(Blueprint $table) {
                $table->dropColumn('currency');
            });

            // 5. Renombrar y agregar constraint
            Schema::table('order_items', function(Blueprint $table) {
                $table->renameColumn('currency_id_temp', 'currency_id');
            });

            Schema::table('order_items', function(Blueprint $table) {
                $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
            });
        } else {
            // El campo currency ya no existe, solo agregar currency_id si no existe
            if (!Schema::hasColumn('order_items', 'currency_id')) {
                Schema::table('order_items', function(Blueprint $table) {
                    $table->foreignId('currency_id')
                        ->default(1)
                        ->after('discount_amount')
                        ->constrained()
                        ->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'currency_id')) {
            Schema::table('order_items', function(Blueprint $table) {
                $table->dropForeign(['currency_id']);
                $table->dropColumn('currency_id');
                $table->string('currency', 3)->default('USD')->after('discount_amount');
            });
        }
    }
};
