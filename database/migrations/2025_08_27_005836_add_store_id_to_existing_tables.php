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
        // Agregar store_id a categories como nullable primero
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('id');
        });

        // Agregar store_id y campos de precio a products como nullable primero
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('id');
            $table->decimal('base_price', 12, 2)->default(0)->after('currency');
            $table->unsignedBigInteger('base_currency_id')->nullable()->after('base_price');
        });

        // Crear un store por defecto si no existe
        $defaultStore = \App\Models\Store::firstOrCreate([
            'slug' => 'default'
        ], [
            'name' => 'Tienda Principal',
            'default_language' => 'es',
            'default_currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true
        ]);

        // Asignar todas las categorías y productos existentes al store por defecto
        \DB::table('categories')->whereNull('store_id')->update(['store_id' => $defaultStore->id]);
        \DB::table('products')->whereNull('store_id')->update(['store_id' => $defaultStore->id]);

        // Ahora agregar las foreign keys
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('store_id')->references('id')->on('stores');
            $table->index(['store_id', 'is_active']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('store_id')->references('id')->on('stores');
            $table->foreign('base_currency_id')->references('id')->on('currencies');
            $table->index(['store_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['base_currency_id']);
            $table->dropForeign(['store_id']);
            $table->dropColumn(['store_id', 'base_price', 'base_currency_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};
