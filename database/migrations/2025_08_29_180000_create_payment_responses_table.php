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
        Schema::create('payment_responses', function (Blueprint $table) {
            $table->id();

            // Referencias principales
            $table->string('transaction_reference')->index();
            $table->foreignId('payment_session_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('cart_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Estado del pago
            $table->enum('payment_status', ['pending', 'approved', 'error', 'cancelled'])->default('pending');
            $table->string('gateway')->default('mitec'); // Para futuras extensiones

            // Campos principales de MITEC
            $table->string('mitec_response')->nullable(); // approved/error
            $table->string('auth_code')->nullable(); // código autorización
            $table->string('folio_cpagos')->nullable(); // folio MITEC
            $table->string('cd_response')->nullable(); // código respuesta
            $table->string('cd_error')->nullable(); // código error
            $table->text('nb_error')->nullable(); // descripción error
            $table->decimal('amount', 10, 2)->nullable();

            // Datos 3DS específicos
            $table->string('ds_trans_id')->nullable();
            $table->string('eci')->nullable();
            $table->text('cavv')->nullable();
            $table->string('trans_status')->nullable();
            $table->string('response_code')->nullable();
            $table->text('response_description')->nullable();

            // Datos de tarjeta (seguros)
            $table->string('card_type')->nullable(); // AMEX, VISA, etc
            $table->string('card_last_four')->nullable(); // últimos 4 dígitos
            $table->string('card_name')->nullable(); // nombre del tarjetahabiente

            // Vouchers para conciliación contable
            $table->longText('voucher')->nullable();
            $table->longText('voucher_comercio')->nullable();
            $table->longText('voucher_cliente')->nullable();

            // XML completo para auditoría y debugging
            $table->longText('raw_xml_response');

            // Metadatos de procesamiento
            $table->timestamp('mitec_date')->nullable(); // fecha/hora reportada por MITEC
            $table->string('mitec_time')->nullable(); // hora específica de MITEC
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable(); // datos adicionales

            $table->timestamps();

            // Índices para performance
            $table->index(['transaction_reference', 'payment_status']);
            $table->index(['payment_session_id', 'payment_status']);
            $table->index(['order_id', 'payment_status']);
            $table->index(['user_id', 'payment_status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_responses');
    }
};
