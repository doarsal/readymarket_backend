<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id')->nullable()->index();
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['active', 'abandoned', 'converted', 'merged'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['session_id', 'status']);
            $table->index(['expires_at']);

            // Unique constraint: one active cart per user or session
            $table->unique(['user_id', 'status'], 'unique_user_active_cart');
            $table->unique(['session_id', 'status'], 'unique_session_active_cart');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
