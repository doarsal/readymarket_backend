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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180)->index();
            $table->text('description')->nullable();
            $table->string('icon', 45)->nullable()->comment('FontAwesome icon class');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['active', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
