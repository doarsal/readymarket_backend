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
        // Primero, eliminar la tabla actual si existe
        Schema::dropIfExists('page_views');

        // Crear la nueva tabla page_views mejorada
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();

            // Usuario y sesión
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 100)->comment('ID de sesión del visitante');
            $table->string('visitor_ip', 45)->comment('IP del visitante');
            $table->text('user_agent')->nullable()->comment('User agent del navegador');

            // Información de la página
            $table->enum('page_type', [
                'home',          // Dashboard principal
                'contact',       // Página de contacto
                'products',      // Lista de todos los productos
                'category',      // Productos por categoría
                'product',       // Detalle específico de producto
                'auth',          // Páginas de autenticación (login, register, reset-password, etc.)
                'profile',       // Gestión de perfil
                'billing',       // Información fiscal
                'orders',        // Gestión de pedidos
                'payment',       // Páginas de pago
                'admin',         // Páginas administrativas
                'search',        // Resultados de búsqueda
                'other'          // Otras páginas
            ])->comment('Tipo de página visitada');

            $table->string('page_url', 500)->comment('URL completa visitada');
            $table->string('page_path', 300)->comment('Path de la ruta (sin query params)');
            $table->string('page_name', 100)->nullable()->comment('Nombre de la ruta en Vue Router');
            $table->json('page_params')->nullable()->comment('Parámetros de la página (filtros, etc.)');
            $table->json('query_params')->nullable()->comment('Query parameters de la URL');

            // IDs relacionados (según el tipo de página)
            $table->unsignedBigInteger('category_id')->nullable()->comment('ID de categoría si aplica');
            $table->unsignedBigInteger('product_id')->nullable()->comment('ID del producto si aplica');
            $table->unsignedBigInteger('store_id')->nullable()->comment('ID de tienda si aplica');

            // Información de referencia
            $table->string('referrer_url', 500)->nullable()->comment('URL de referencia');
            $table->string('referrer_domain', 200)->nullable()->comment('Dominio de referencia');

            // UTM Parameters para tracking de campañas
            $table->string('utm_source', 100)->nullable()->comment('Fuente UTM');
            $table->string('utm_medium', 100)->nullable()->comment('Medio UTM');
            $table->string('utm_campaign', 100)->nullable()->comment('Campaña UTM');
            $table->string('utm_term', 100)->nullable()->comment('Término UTM');
            $table->string('utm_content', 100)->nullable()->comment('Contenido UTM');

            // Información del dispositivo y navegador
            $table->string('device_type', 50)->nullable()->comment('mobile, desktop, tablet');
            $table->string('browser', 100)->nullable()->comment('Navegador');
            $table->string('browser_version', 50)->nullable()->comment('Versión del navegador');
            $table->string('os', 100)->nullable()->comment('Sistema operativo');
            $table->string('os_version', 50)->nullable()->comment('Versión del SO');
            $table->string('screen_resolution', 20)->nullable()->comment('Resolución de pantalla');

            // Información geográfica
            $table->string('country', 5)->nullable()->comment('País (código ISO)');
            $table->string('region', 100)->nullable()->comment('Región/Estado');
            $table->string('city', 100)->nullable()->comment('Ciudad');
            $table->string('timezone', 50)->nullable()->comment('Zona horaria');

            // Métricas de comportamiento
            $table->integer('time_on_page')->nullable()->comment('Tiempo en página (segundos)');
            $table->boolean('is_bounce')->default(false)->comment('Es rebote (una sola página)');
            $table->integer('scroll_depth')->nullable()->comment('Profundidad de scroll (%)');
            $table->boolean('is_returning_visitor')->default(false)->comment('Es visitante recurrente');

            // Información adicional
            $table->string('language', 10)->nullable()->comment('Idioma del navegador');
            $table->boolean('is_mobile')->default(false)->comment('Es dispositivo móvil');
            $table->boolean('is_bot')->default(false)->comment('Es un bot/crawler');
            $table->json('additional_data')->nullable()->comment('Datos adicionales personalizados');

            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');

            // Indices para optimizar consultas
            $table->index(['page_type', 'created_at'], 'idx_page_type_date');
            $table->index(['user_id', 'created_at'], 'idx_user_date');
            $table->index(['session_id', 'created_at'], 'idx_session_date');
            $table->index(['category_id', 'created_at'], 'idx_category_date');
            $table->index(['product_id', 'created_at'], 'idx_product_date');
            $table->index(['visitor_ip', 'created_at'], 'idx_ip_date');
            $table->index('session_id', 'idx_session_id');
            $table->index(['page_type', 'page_path'], 'idx_page_type_path');
            $table->index(['device_type', 'created_at'], 'idx_device_date');
            $table->index(['country', 'created_at'], 'idx_country_date');
            $table->index(['is_bot', 'created_at'], 'idx_bot_date');
            $table->index('created_at', 'idx_created_at');
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
