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
        // Drop existing order_items table if exists
        Schema::dropIfExists('order_items');

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            // Product reference (can be null if product is deleted later)
            $table->unsignedInteger('product_id')->nullable();
            $table->foreign('product_id')->references('idproduct')->on('products')->onDelete('set null');

            // === COMPLETE PRODUCT SNAPSHOT - CRITICAL FOR ORDERS ===
            // Basic product info at time of purchase
            $table->string('sku_id')->comment('SKU at time of purchase');
            $table->string('product_title')->comment('Product name at time of purchase');
            $table->text('product_description')->nullable()->comment('Product description at time of purchase');
            $table->string('publisher')->nullable()->comment('Publisher at time of purchase');
            $table->string('segment')->nullable()->comment('Segment at time of purchase');
            $table->string('market')->nullable()->comment('Market at time of purchase');
            $table->string('license_duration')->nullable()->comment('License duration at time of purchase');

            // Pricing snapshot
            $table->decimal('unit_price', 12, 2)->comment('Unit price at time of purchase');
            $table->decimal('list_price', 12, 2)->nullable()->comment('List price at time of purchase');
            $table->decimal('discount_amount', 12, 2)->default(0)->comment('Discount applied per unit');
            $table->string('currency', 3)->default('USD')->comment('Currency at time of purchase');

            // Order specific data
            $table->integer('quantity')->default(1)->comment('Quantity ordered');
            $table->decimal('line_total', 12, 2)->comment('Total for this line (unit_price * quantity - discount)');

            // Product categorization snapshot
            $table->string('category_name')->nullable()->comment('Category name at time of purchase');
            $table->unsignedBigInteger('category_id_snapshot')->nullable()->comment('Category ID at time of purchase');

            // Product flags snapshot
            $table->boolean('is_top')->default(false)->comment('Was top product at time of purchase');
            $table->boolean('is_bestseller')->default(false)->comment('Was bestseller at time of purchase');
            $table->boolean('is_novelty')->default(false)->comment('Was novelty at time of purchase');
            $table->boolean('is_active')->default(true)->comment('Was active at time of purchase');

            // Additional snapshot data
            $table->json('product_metadata')->nullable()->comment('Complete product data snapshot');
            $table->json('pricing_metadata')->nullable()->comment('Pricing rules and discounts applied');

            // Fulfillment data
            $table->enum('fulfillment_status', ['pending', 'processing', 'fulfilled', 'cancelled', 'refunded'])
                  ->default('pending')->comment('Individual item fulfillment status');
            $table->timestamp('fulfilled_at')->nullable()->comment('When this item was fulfilled');

            // Refund data
            $table->decimal('refunded_amount', 12, 2)->default(0)->comment('Amount refunded for this item');
            $table->timestamp('refunded_at')->nullable()->comment('When this item was refunded');
            $table->text('refund_reason')->nullable()->comment('Reason for refund');

            $table->timestamps();

            // Indexes for performance
            $table->index(['order_id', 'fulfillment_status']);
            $table->index(['product_id']);
            $table->index(['sku_id']);
            $table->index(['fulfillment_status']);
            $table->index(['order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
