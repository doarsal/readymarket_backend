<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Eliminar los constraints únicos problemáticos que incluyen el status
            $table->dropUnique('unique_user_active_cart');
            $table->dropUnique('unique_session_active_cart');
        });

        // Agregar un campo único para carritos activos usando triggers o lógica de aplicación
        // Por ahora, implementaremos la validación en el código de la aplicación
        // Ya que MySQL no soporta índices únicos parciales nativamente
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Restaurar los constraints originales
            $table->unique(['user_id', 'status'], 'unique_user_active_cart');
            $table->unique(['session_id', 'status'], 'unique_session_active_cart');
        });
    }
};
