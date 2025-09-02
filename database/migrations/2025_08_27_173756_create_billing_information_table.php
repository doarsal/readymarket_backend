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
        Schema::create('billing_information', function (Blueprint $table) {
            $table->id();
            $table->string('organization', 255);
            $table->string('rfc', 15);
            $table->unsignedInteger('tax_regime_id');
            $table->string('postal_code', 10);
            $table->string('email', 180);
            $table->string('phone', 15);
            $table->string('file', 120)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('config_id');
            $table->unsignedInteger('store_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('account_id')->nullable();
            $table->string('code', 20);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('tax_regime_id');
            $table->index('config_id');
            $table->index('store_id');
            $table->index('user_id');
            $table->index('account_id');
            $table->index('active');
            $table->index(['user_id', 'is_default']);

            // Foreign keys (opcional, depende de si existen las tablas referenciadas)
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_information');
    }
};
