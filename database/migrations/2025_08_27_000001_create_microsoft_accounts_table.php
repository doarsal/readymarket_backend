<?php

// Migración para crear la tabla microsoft_accounts
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('microsoft_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Información Microsoft
            $table->string('microsoft_id', 100)->nullable()->index();
            $table->string('domain', 150)->index();
            $table->string('domain_concatenated', 300); // .onmicrosoft.com

            // Información personal/contacto
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->index();
            $table->string('phone', 20)->nullable();
            $table->string('organization', 255)->index();

            // Información de dirección
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->string('state_name', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 3)->default('MX');
            $table->string('country_name', 100)->nullable();

            // Configuración regional
            $table->string('language_code', 10)->default('es-MX');
            $table->string('culture', 10)->default('es-MX');

            // Estados y configuración
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_current')->default(false);
            $table->boolean('is_pending')->default(true)->index();

            // Relaciones con sistema
            $table->unsignedBigInteger('configuration_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();

            // Timestamps y soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Índices únicos y compuestos
            $table->unique(['domain', 'user_id'], 'unique_domain_per_user');
            $table->unique(['microsoft_id'], 'unique_microsoft_id');
            $table->index(['user_id', 'is_default'], 'idx_user_default');
            $table->index(['user_id', 'is_active'], 'idx_user_active');
            $table->index(['is_pending', 'created_at'], 'idx_pending_created');
        });
    }

    public function down()
    {
        Schema::dropIfExists('microsoft_accounts');
    }
};

?>
