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
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('module', 120)->nullable()->comment('Módulo donde se ejecutó la acción');
            $table->string('title', 120)->nullable()->comment('Título descriptivo de la acción');
            $table->string('reference_id', 255)->nullable()->comment('ID de referencia del objeto afectado');
            $table->json('metadata')->nullable()->comment('Datos adicionales de la acción');
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['user_id', 'created_at']);
            $table->index(['activity_id', 'created_at']);
            $table->index(['module', 'created_at']);
            $table->index('reference_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
