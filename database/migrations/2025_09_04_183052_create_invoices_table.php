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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('invoice_number')->unique(); // Folio interno
            $table->string('serie')->default('FAC'); // Serie de la factura
            $table->string('folio'); // Folio del SAT
            $table->string('uuid')->nullable(); // UUID del timbrado
            $table->enum('status', ['pending', 'stamped', 'cancelled', 'error'])->default('pending');

            // Datos del emisor (empresa)
            $table->string('issuer_rfc');
            $table->string('issuer_name');
            $table->string('issuer_tax_regime');
            $table->string('issuer_postal_code');

            // Datos del receptor (cliente)
            $table->string('receiver_rfc');
            $table->string('receiver_name');
            $table->string('receiver_tax_regime');
            $table->string('receiver_postal_code');
            $table->string('receiver_cfdi_use'); // Uso de CFDI

            // Montos
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('MXN');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000);

            // Metadatos del timbrado
            $table->string('payment_method'); // Método de pago (PUE, PPD, etc.)
            $table->string('payment_form'); // Forma de pago (03, 04, etc.)
            $table->string('expedition_place'); // Lugar de expedición
            $table->datetime('issue_date'); // Fecha de emisión
            $table->datetime('stamped_at')->nullable(); // Fecha de timbrado
            $table->datetime('cancelled_at')->nullable(); // Fecha de cancelación

            // Archivos y respuestas
            $table->longText('xml_content')->nullable(); // XML timbrado
            $table->longText('pdf_content')->nullable(); // PDF generado
            $table->json('sat_response')->nullable(); // Respuesta completa del SAT
            $table->json('facturalo_response')->nullable(); // Respuesta de FacturaloPlus

            // Metadatos adicionales
            $table->string('cancellation_reason')->nullable();
            $table->string('replacement_uuid')->nullable(); // UUID de reemplazo
            $table->json('concepts')->nullable(); // Conceptos de la factura
            $table->text('notes')->nullable(); // Notas adicionales

            $table->timestamps();

            // Índices
            $table->index(['user_id', 'status']);
            $table->index(['order_id']);
            $table->index(['uuid']);
            $table->index(['status']);
            $table->index(['issue_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
