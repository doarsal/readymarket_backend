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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('language_id')->constrained();
            $table->string('translatable_type');
            $table->unsignedBigInteger('translatable_id');
            $table->string('field', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'language_id', 'translatable_type', 'translatable_id', 'field'], 'unique_translation');
            $table->index(['translatable_type', 'translatable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
