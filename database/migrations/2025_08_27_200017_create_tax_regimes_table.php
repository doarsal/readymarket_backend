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
        Schema::create('tax_regimes', function (Blueprint $table) {
            $table->id();
            $table->integer('sat_code')->nullable()->comment('Código del SAT');
            $table->string('name', 120)->nullable()->comment('Nombre del régimen fiscal');
            $table->integer('relation')->nullable()->comment('Relación con otros regímenes');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade')->comment('ID de la tienda');
            $table->boolean('active')->default(true)->comment('Estado activo/inactivo');
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['sat_code']);
            $table->index(['store_id']);
            $table->index(['active']);
            $table->index(['name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_regimes');
    }
};
