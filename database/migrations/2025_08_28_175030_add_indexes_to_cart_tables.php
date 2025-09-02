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
        // Índices para tabla carts
        Schema::table('carts', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_carts_user_status');
            $table->index(['cart_token', 'status'], 'idx_carts_token_status');
            $table->index('status', 'idx_carts_status');
        });

        // Índices para tabla cart_items
        Schema::table('cart_items', function (Blueprint $table) {
            $table->index(['cart_id', 'status'], 'idx_cart_items_cart_status');
            $table->index(['product_id', 'sku_id'], 'idx_cart_items_product_sku');
            $table->index('status', 'idx_cart_items_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex('idx_carts_user_status');
            $table->dropIndex('idx_carts_token_status');
            $table->dropIndex('idx_carts_status');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('idx_cart_items_cart_status');
            $table->dropIndex('idx_cart_items_product_sku');
            $table->dropIndex('idx_cart_items_status');
        });
    }
};
