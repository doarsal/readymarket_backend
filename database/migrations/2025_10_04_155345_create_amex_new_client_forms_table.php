<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('amex_new_client_forms', function(Blueprint $table) {
            $table->id();
            $table->string('contacto_nombre');
            $table->string('contacto_apellidos');
            $table->string('contacto_telefono');
            $table->string('contacto_email');
            $table->string('empresa_nombre');
            $table->string('empresa_rfc');
            $table->string('empresa_ciudad');
            $table->string('empresa_estado');
            $table->string('empresa_codigo_postal');
            $table->string('empresa_ingresos_anuales');
            $table->string('empresa_info_adicional')->nullable();
            $table->boolean('status_envio')->default(false)->nullable();
            $table->dateTime('fecha_envio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amex_new_client_forms');
    }
};
