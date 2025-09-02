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
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();

            // Información del visitante
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('session_id', 100)->index()->comment('ID de sesión del visitante');
            $table->string('visitor_ip', 45)->comment('IP del visitante');
            $table->text('user_agent')->nullable()->comment('User agent del navegador');

            // Información de la página visitada
            $table->enum('page_type', ['index', 'category', 'product'])->comment('Tipo de página visitada');
            $table->string('page_url', 500)->comment('URL completa visitada');
            $table->json('page_params')->nullable()->comment('Parámetros de la página (filtros, etc.)');

            // Información específica del contenido
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->nullable()->comment('ID del producto');
            $table->index('product_id');
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');

            // Información de referencia
            $table->string('referrer_url', 500)->nullable()->comment('URL de referencia');
            $table->string('referrer_domain', 200)->nullable()->comment('Dominio de referencia');
            $table->string('utm_source', 100)->nullable()->comment('Fuente UTM');
            $table->string('utm_medium', 100)->nullable()->comment('Medio UTM');
            $table->string('utm_campaign', 100)->nullable()->comment('Campaña UTM');

            // Información técnica
            $table->string('device_type', 50)->nullable()->comment('mobile, desktop, tablet');
            $table->string('browser', 100)->nullable()->comment('Navegador');
            $table->string('os', 100)->nullable()->comment('Sistema operativo');
            $table->string('country', 5)->nullable()->comment('País (código ISO)');

            // Tiempo de permanencia y interacción
            $table->integer('time_on_page')->nullable()->comment('Tiempo en página (segundos)');
            $table->boolean('is_bounce')->default(false)->comment('Es rebote (una sola página)');
            $table->integer('scroll_depth')->nullable()->comment('Profundidad de scroll (%)');

            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index(['page_type', 'created_at'], 'idx_page_type_date');
            $table->index(['user_id', 'created_at'], 'idx_user_date');
            $table->index(['session_id', 'created_at'], 'idx_session_date');
            $table->index(['category_id', 'created_at'], 'idx_category_date');
            $table->index(['product_id', 'created_at'], 'idx_product_date');
            $table->index(['visitor_ip', 'created_at'], 'idx_ip_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
