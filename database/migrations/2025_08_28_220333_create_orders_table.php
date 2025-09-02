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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Identificación única
            $table->string('order_number', 20)->unique()->comment('Número de orden público (ORD-2025-001234)');

            // Relaciones principales
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('cart_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');

            // Estados de la orden
            $table->enum('status', [
                'pending',      // Pendiente de pago
                'processing',   // Procesando
                'shipped',      // Enviado
                'delivered',    // Entregado
                'cancelled',    // Cancelado
                'refunded'      // Reembolsado
            ])->default('pending');

            $table->enum('payment_status', [
                'pending',      // Pendiente
                'paid',         // Pagado
                'failed',       // Falló
                'cancelled',    // Cancelado
                'refunded',     // Reembolsado
                'partial_refund' // Reembolso parcial
            ])->default('pending');

            $table->enum('fulfillment_status', [
                'unfulfilled',  // Sin cumplir
                'partial',      // Parcial
                'fulfilled',    // Cumplido
                'cancelled'     // Cancelado
            ])->default('unfulfilled');

            // Información financiera
            $table->decimal('subtotal', 12, 2)->default(0)->comment('Subtotal sin impuestos');
            $table->decimal('tax_amount', 12, 2)->default(0)->comment('Cantidad de impuestos');
            $table->decimal('shipping_amount', 12, 2)->default(0)->comment('Costo de envío');
            $table->decimal('discount_amount', 12, 2)->default(0)->comment('Descuentos aplicados');
            $table->decimal('total_amount', 12, 2)->comment('Total final');
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000)->comment('Tasa de cambio al momento de la compra');

            // Información de contacto del cliente
            $table->string('customer_email')->comment('Email del cliente al momento de la compra');
            $table->string('customer_phone', 20)->nullable();

            // Direcciones (JSON para flexibilidad)
            $table->json('billing_address')->comment('Dirección de facturación');
            $table->json('shipping_address')->comment('Dirección de envío');

            // Información de envío
            $table->string('shipping_method', 100)->nullable()->comment('Método de envío seleccionado');
            $table->string('tracking_number', 100)->nullable()->comment('Número de seguimiento');
            $table->timestamp('estimated_delivery')->nullable()->comment('Fecha estimada de entrega');
            $table->timestamp('shipped_at')->nullable()->comment('Fecha de envío');
            $table->timestamp('delivered_at')->nullable()->comment('Fecha de entrega');

            // Información de pago
            $table->string('payment_method', 50)->nullable()->comment('Método de pago usado');
            $table->string('payment_gateway', 50)->nullable()->comment('Gateway de pago (stripe, paypal, etc.)');
            $table->string('transaction_id', 255)->nullable()->comment('ID de transacción del gateway');
            $table->timestamp('paid_at')->nullable()->comment('Fecha de pago');

            // Información de reembolso
            $table->decimal('refunded_amount', 12, 2)->default(0)->comment('Cantidad reembolsada');
            $table->timestamp('refunded_at')->nullable()->comment('Fecha de reembolso');
            $table->text('refund_reason')->nullable()->comment('Razón del reembolso');

            // Notas y metadatos
            $table->text('notes')->nullable()->comment('Notas internas del administrador');
            $table->text('customer_notes')->nullable()->comment('Notas del cliente');
            $table->json('tags')->nullable()->comment('Etiquetas para organización');
            $table->json('metadata')->nullable()->comment('Metadatos adicionales');

            // Auditoría y fechas importantes
            $table->timestamp('cancelled_at')->nullable()->comment('Fecha de cancelación');
            $table->text('cancellation_reason')->nullable()->comment('Razón de cancelación');
            $table->timestamp('processed_at')->nullable()->comment('Fecha de procesamiento');

            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index(['store_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['customer_email']);
            $table->index(['order_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
