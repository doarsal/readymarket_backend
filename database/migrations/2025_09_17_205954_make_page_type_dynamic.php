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
        Schema::table('page_views', function (Blueprint $table) {
            // Cambiar page_type de ENUM a VARCHAR para ser completamente dinámico
            $table->string('page_type', 100)->change()->comment('Tipo de página (dinámico basado en route name)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            // Revertir a ENUM original
            $table->enum('page_type', [
                'home','contact','products','category','product','auth','profile',
                'billing','orders','payment','admin','search','other'
            ])->change()->comment('Tipo de página visitada');
        });
    }
};
