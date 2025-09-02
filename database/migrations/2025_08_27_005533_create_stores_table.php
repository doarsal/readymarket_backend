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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('domain')->nullable();
            $table->string('subdomain', 100)->nullable();
            $table->string('default_language', 5)->default('es');
            $table->string('default_currency', 3)->default('USD');
            $table->string('timezone', 50)->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_maintenance')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active']);
            $table->index(['slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
