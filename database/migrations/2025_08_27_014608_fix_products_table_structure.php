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
        // Drop existing products table if it exists
        Schema::dropIfExists('products');

        // Create products table with correct structure
        Schema::create('products', function (Blueprint $table) {
            $table->increments('idproduct');
            $table->string('ProductTitle', 255)->nullable();
            $table->string('ProductId', 45)->nullable();
            $table->string('SkuId', 45)->nullable();
            $table->string('Id', 45)->nullable();
            $table->string('SkuTitle', 255)->nullable();
            $table->string('Publisher', 180)->nullable();
            $table->text('SkuDescription')->nullable();
            $table->string('UnitOfMeasure', 255)->nullable();
            $table->string('TermDuration', 180)->nullable();
            $table->string('BillingPlan', 180)->nullable();
            $table->string('Market', 180)->nullable();
            $table->string('Currency', 180)->nullable();
            $table->string('UnitPrice', 180)->nullable();
            $table->string('PricingTierRangeMin', 180)->nullable();
            $table->string('PricingTierRangeMax', 180)->nullable();
            $table->string('EffectiveStartDate', 180)->nullable();
            $table->string('EffectiveEndDate', 180)->nullable();
            $table->string('Tags', 180)->nullable();
            $table->string('ERPPrice', 180)->nullable();
            $table->string('Segment', 180)->nullable();
            $table->string('prod_idsperiod', 80)->nullable();
            $table->unsignedInteger('prod_idcategory')->nullable();
            $table->unsignedInteger('prod_idsubcategory')->nullable();
            $table->unsignedInteger('prod_idconfig')->nullable();
            $table->unsignedInteger('prod_idstore')->nullable();
            $table->integer('prod_idcurrency')->nullable();
            $table->string('prod_slide', 180)->nullable();
            $table->unsignedInteger('prod_active')->nullable();
            $table->string('prod_icon', 180)->nullable();
            $table->string('prod_slideimage', 180)->nullable();
            $table->string('prod_screenshot1', 180)->nullable();
            $table->string('prod_screenshot2', 180)->nullable();
            $table->string('prod_screenshot3', 180)->nullable();
            $table->string('prod_screenshot4', 180)->nullable();
            $table->integer('top')->nullable();
            $table->integer('bestseller')->nullable();
            $table->integer('slide')->nullable();
            $table->integer('novelty')->nullable();
            $table->string('created_at', 45)->nullable();
            $table->string('updated_at', 45)->nullable();
            $table->string('deleted_at', 45)->nullable();

            // Primary key
            //$table->primary('idproduct');

            // Indexes
            $table->index('prod_idcategory');
            $table->index('prod_idsubcategory');
            $table->index('prod_idconfig');
            $table->index('prod_idstore');
            $table->index('prod_idcurrency');
            $table->index('prod_idsperiod');
            $table->index(['ProductId', 'SkuId']);
            $table->index(['prod_active', 'prod_idcategory']);
            $table->index(['Publisher', 'prod_active']);
            $table->index(['Market', 'Currency']);
            $table->index(['top', 'bestseller', 'slide', 'novelty']);
            $table->index('EffectiveStartDate');
            $table->index('EffectiveEndDate');

            // Foreign key constraints (if tables exist)
            // $table->foreign('prod_idcategory')->references('id')->on('categories')->onDelete('set null');
            // $table->foreign('prod_idstore')->references('id')->on('stores')->onDelete('set null');
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
