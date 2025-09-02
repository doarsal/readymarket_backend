<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Esta migración es solo para que Laravel reconozca la tabla
        // La tabla ya existe en la base de datos con 31,514 registros
        // NO ejecutar esta migración si la tabla ya existe

        if (!Schema::hasTable('postalcodes')) {
            Schema::create('postalcodes', function (Blueprint $table) {
                $table->unsignedInteger('idpostalcode')->autoIncrement()->primary();
                $table->char('pc_postalcode', 8)->nullable()->charset('utf8mb3')->collation('utf8mb3_unicode_ci');
                $table->string('pc_city', 180)->nullable()->charset('utf8mb3')->collation('utf8mb3_unicode_ci');
                $table->string('pc_state', 45)->nullable();
                $table->string('pc_countrycode', 3)->nullable();
                $table->string('pc_culture', 10)->nullable();
                $table->string('pc_lang', 10)->nullable();
                $table->string('pc_statelarge', 120)->nullable();
                $table->string('pc_countrylarge', 45)->nullable();

                // Índices para optimizar búsquedas
                $table->index(['pc_postalcode']);
                $table->index(['pc_city']);
                $table->index(['pc_state']);
                $table->index(['pc_countrycode']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postalcodes');
    }
};
