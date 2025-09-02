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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('title');
            $table->string('product_id'); // Microsoft ProductId like DG7GMGF0D7FV
            $table->string('sku_id'); // Microsoft SkuId like 0001
            $table->string('sku_title');
            $table->string('publisher')->default('Microsoft Corporation');
            $table->text('description');
            $table->string('segment')->default('Commercial');
            $table->string('market')->default('MX');
            $table->string('currency')->default('USD');
            $table->string('icon')->nullable();
            $table->string('slide_image')->nullable();
            $table->string('screenshot1')->nullable();
            $table->string('screenshot2')->nullable();
            $table->string('screenshot3')->nullable();
            $table->string('screenshot4')->nullable();
            $table->boolean('is_top')->default(false);
            $table->boolean('is_bestseller')->default(false);
            $table->boolean('is_slide')->default(false);
            $table->boolean('is_novelty')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sku_id']);
            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
