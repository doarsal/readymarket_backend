<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_prod_idsperiod_index');
            $table->dropIndex('products_prod_idsubcategory_index');
            $table->dropIndex('products_prod_idconfig_index');
            $table->dropIndex('products_prod_idstore_index');
            $table->dropIndex('idx_active_store');
            $table->dropIndex('products_prod_idcurrency_index');
            $table->dropIndex('products_prod_active_prod_idcategory_index');
            $table->dropIndex('products_publisher_prod_active_index');
            $table->dropIndex('products_prod_active_index');
            $table->dropIndex('idx_publisher_active');
            $table->dropIndex('products_top_bestseller_slide_novelty_index');
            $table->dropIndex('products_top_index');
            $table->dropIndex('products_bestseller_index');
            $table->dropIndex('products_slide_index');
            $table->dropIndex('products_novelty_index');

            // 1. ELIMINAR campos redundantes/innecesarios (sin tocar índices por ahora)
            $table->dropColumn([
                'prod_idsperiod',
                'prod_idsubcategory',
                'prod_idconfig',
                'prod_idstore',
                'prod_idcurrency'
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            // 2. MEJORAR tipos de datos de campos propios
            // Cambiar created_at, updated_at, deleted_at a TIMESTAMP
            $table->dropColumn(['created_at', 'updated_at', 'deleted_at']);
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable(); // Soft delete
        });

        Schema::table('products', function (Blueprint $table) {
            // 3. MEJORAR campos booleanos
            $table->dropColumn(['prod_active', 'top', 'bestseller', 'slide', 'novelty']);

            // Agregar campos booleanos corregidos
            $table->boolean('is_active')->default(true)->after('store_id');
            $table->boolean('is_top')->default(false)->after('is_active');
            $table->boolean('is_bestseller')->default(false)->after('is_top');
            $table->boolean('is_slide')->default(false)->after('is_bestseller');
            $table->boolean('is_novelty')->default(false)->after('is_slide');
        });

        Schema::table('products', function (Blueprint $table) {
            // 4. MEJORAR campos de imágenes
            $table->dropColumn([
                'prod_slide',
                'prod_icon',
                'prod_slideimage',
                'prod_screenshot1',
                'prod_screenshot2',
                'prod_screenshot3',
                'prod_screenshot4'
            ]);

            // Agregar campos de imágenes mejorados
            $table->string('icon_url')->nullable()->after('is_novelty');
            $table->string('slide_image_url')->nullable()->after('icon_url');
            $table->json('screenshot_urls')->nullable()->after('slide_image_url')->comment('Array of screenshot URLs');
        });

        Schema::table('products', function (Blueprint $table) {
            // 5. AGREGAR relación con categorías
            $table->foreignId('category_id')->nullable()->after('screenshot_urls')->constrained()->onDelete('set null');

            // 6. AGREGAR foreign key para store_id
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Revertir todos los cambios
            $table->dropForeign(['category_id']);
            $table->dropForeign(['store_id']);

            $table->dropColumn([
                'category_id',
                'is_active',
                'is_top',
                'is_bestseller',
                'is_slide',
                'is_novelty',
                'icon_url',
                'slide_image_url',
                'screenshot_urls',
                'deleted_at'
            ]);

            $table->dropTimestamps();

            // Restaurar campos originales
            $table->string('prod_idsperiod', 80)->nullable();
            $table->unsignedInteger('prod_idcategory')->nullable();
            $table->unsignedInteger('prod_idsubcategory')->nullable();
            $table->unsignedInteger('prod_idconfig')->nullable();
            $table->unsignedInteger('prod_idstore')->nullable();
            $table->integer('prod_idcurrency')->nullable();
            $table->string('prod_slide', 180)->nullable();
            $table->unsignedInteger('prod_active')->nullable();
            $table->string('prod_icon', 180)->nullable();
            $table->string('prod_slideimage', 180)->nullable();
            $table->string('prod_screenshot1', 180)->nullable();
            $table->string('prod_screenshot2', 180)->nullable();
            $table->string('prod_screenshot3', 180)->nullable();
            $table->string('prod_screenshot4', 180)->nullable();
            $table->integer('top')->nullable();
            $table->integer('bestseller')->nullable();
            $table->integer('slide')->nullable();
            $table->integer('novelty')->nullable();
            $table->string('created_at', 45)->nullable();
            $table->string('updated_at', 45)->nullable();
            $table->string('deleted_at', 45)->nullable();
        });
    }
};
