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
        // Crear constraints únicos solo para carritos activos usando MySQL
        // Esto permite múltiples carritos converted/abandoned/merged por usuario

        // Para MySQL 8.0+ podemos usar functional indexes con expresiones
        DB::statement('
            CREATE UNIQUE INDEX unique_user_active_cart
            ON carts (user_id, (CASE WHEN status = "active" THEN 1 ELSE NULL END))
        ');

        DB::statement('
            CREATE UNIQUE INDEX unique_session_active_cart
            ON carts (session_id, (CASE WHEN status = "active" THEN 1 ELSE NULL END))
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX unique_user_active_cart ON carts');
        DB::statement('DROP INDEX unique_session_active_cart ON carts');
    }
};
