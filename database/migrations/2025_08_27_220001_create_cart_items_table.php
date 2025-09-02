<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('product_id'); // Cambiar a unsignedInteger para coincidir con idproduct
            $table->foreign('product_id')->references('idproduct')->on('products')->onDelete('cascade');
            $table->string('sku_id')->index();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->json('metadata')->nullable(); // Para datos adicionales como configuraciones
            $table->enum('status', ['active', 'saved_for_later', 'removed'])->default('active');
            $table->timestamps();

            // Indexes for performance
            $table->index(['cart_id', 'status']);
            $table->index(['product_id', 'sku_id']);

            // Prevent duplicate items in same cart
            $table->unique(['cart_id', 'product_id', 'sku_id'], 'unique_cart_product_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
