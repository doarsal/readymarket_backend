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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // Relación con la orden
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            // Información del producto al momento de la compra (SNAPSHOT)
            $table->unsignedBigInteger('product_id')->nullable()->comment('ID del producto (puede ser null si se elimina)');
            $table->string('product_name')->comment('Nombre del producto al momento de la compra');
            $table->string('product_sku', 100)->nullable()->comment('SKU del producto');
            $table->text('product_description')->nullable()->comment('Descripción al momento de la compra');
            $table->string('product_image')->nullable()->comment('Imagen principal del producto');

            // Información de precios (INMUTABLE)
            $table->decimal('unit_price', 12, 2)->comment('Precio unitario al momento de la compra');
            $table->integer('quantity')->comment('Cantidad comprada');
            $table->decimal('line_total', 12, 2)->comment('Total de la línea (unit_price * quantity)');

            // Información de descuentos por ítem
            $table->decimal('discount_amount', 12, 2)->default(0)->comment('Descuento aplicado a este ítem');
            $table->string('discount_type', 50)->nullable()->comment('Tipo de descuento (percentage, fixed, coupon)');
            $table->string('discount_code', 100)->nullable()->comment('Código de descuento usado');

            // Información de impuestos por ítem
            $table->decimal('tax_rate', 5, 4)->default(0)->comment('Tasa de impuesto aplicada (0.1600 = 16%)');
            $table->decimal('tax_amount', 12, 2)->default(0)->comment('Cantidad de impuesto para este ítem');

            // Metadatos del producto al momento de la compra
            $table->json('product_metadata')->nullable()->comment('Metadatos adicionales del producto');
            $table->json('customizations')->nullable()->comment('Personalizaciones del cliente (color, talla, etc.)');

            // Estados de cumplimiento por ítem
            $table->enum('fulfillment_status', [
                'unfulfilled',  // Sin cumplir
                'fulfilled',    // Cumplido
                'cancelled',    // Cancelado
                'returned'      // Devuelto
            ])->default('unfulfilled');

            // Información de envío por ítem (para productos que se envían por separado)
            $table->string('tracking_number', 100)->nullable()->comment('Número de seguimiento específico del ítem');
            $table->timestamp('shipped_at')->nullable()->comment('Fecha de envío del ítem');
            $table->timestamp('delivered_at')->nullable()->comment('Fecha de entrega del ítem');

            // Información de devolución
            $table->integer('returned_quantity')->default(0)->comment('Cantidad devuelta');
            $table->decimal('returned_amount', 12, 2)->default(0)->comment('Monto devuelto');
            $table->timestamp('returned_at')->nullable()->comment('Fecha de devolución');
            $table->text('return_reason')->nullable()->comment('Razón de la devolución');

            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['order_id', 'product_id']);
            $table->index(['product_id']);
            $table->index(['fulfillment_status']);
            $table->index(['order_id', 'fulfillment_status']);

            // Índice para reportes financieros
            $table->index(['order_id', 'line_total']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
