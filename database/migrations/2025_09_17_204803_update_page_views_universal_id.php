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
            // Agregar campo universal para ID
            $table->unsignedBigInteger('resource_id')->nullable()->after('query_params')->comment('ID universal del recurso visitado (producto, categoría, etc.)');

            // Eliminar campos específicos y sus foreign keys
            $table->dropForeign(['category_id']);
            $table->dropForeign(['store_id']);
            $table->dropColumn(['category_id', 'product_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            // Restaurar campos específicos
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();

            // Restaurar foreign keys
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');

            // Eliminar campo universal
            $table->dropColumn('resource_id');
        });
    }
};
