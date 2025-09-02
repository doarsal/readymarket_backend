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
        Schema::create('store_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('category', 50);
            $table->string('key_name', 100);
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'text', 'json', 'boolean', 'integer', 'file', 'url'])->default('string');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'category', 'key_name']);
            $table->index(['store_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_configurations');
    }
};
