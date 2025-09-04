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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('subscription_identifier');
            $table->string('offer_id')->nullable();
            $table->string('subscription_id')->nullable();
            $table->string('term_duration')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('friendly_name')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('pricing', 10, 2)->nullable();
            $table->integer('status')->default(1);
            $table->foreignId('microsoft_account_id')->constrained('microsoft_accounts')->onDelete('cascade');
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')->references('idproduct')->on('products')->onDelete('cascade');
            $table->string('sku_id');
            $table->string('created_by')->default('Marketplace');
            $table->string('modified_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['order_id', 'status'], 'idx_subscriptions_order_status');
            $table->index(['microsoft_account_id', 'status'], 'idx_subscriptions_ms_account_status');
            $table->index(['subscription_identifier'], 'idx_subscriptions_identifier');
            $table->index(['subscription_id'], 'idx_subscriptions_ms_id');
            $table->index(['sku_id'], 'idx_subscriptions_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
