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
        // Check if sku_id column exists and drop it
        if (Schema::hasColumn('cart_items', 'sku_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropIndex(['product_id', 'sku_id']);
                $table->dropUnique('unique_cart_product_sku');
                $table->dropIndex('cart_items_sku_id_index');
                $table->dropIndex('idx_cart_items_product_sku');
                $table->dropColumn('sku_id');
            });
        }

        // Add new indexes and constraints safely
        try {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->unique(['cart_id', 'product_id'], 'unique_cart_product');
                $table->index(['product_id'], 'idx_cart_items_product');
            });
        } catch (\Exception $e) {
            // Indexes might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('sku_id')->after('product_id');
        });
    }
};
