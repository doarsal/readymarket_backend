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
        Schema::create('payment_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Tokenización y seguridad
            $table->string('card_token', 255)->unique()->comment('Token único para la tarjeta');
            $table->string('card_fingerprint', 64)->index()->comment('Huella digital única de la tarjeta');

            // Información visible de la tarjeta
            $table->char('last_four_digits', 4)->comment('Últimos 4 dígitos');
            $table->string('brand', 20)->comment('VISA, MASTERCARD, AMEX, etc.');
            $table->string('card_type', 20)->comment('credit, debit');

            // Información encriptada
            $table->text('expiry_month_encrypted')->comment('Mes de expiración encriptado');
            $table->text('expiry_year_encrypted')->comment('Año de expiración encriptado');
            $table->text('cardholder_name_encrypted')->comment('Nombre del titular encriptado');

            // Integración con procesadores de pago
            $table->string('mitec_card_id', 100)->nullable()->comment('ID de la tarjeta en MITEC');
            $table->string('mitec_merchant_used', 50)->comment('Merchant usado en MITEC');

            // Estados y configuración
            $table->boolean('is_default')->default(false)->comment('Es la tarjeta predeterminada');
            $table->boolean('is_active')->default(true)->comment('Tarjeta activa');

            // Auditoría de seguridad
            $table->string('created_ip', 45)->comment('IP donde se creó');
            $table->string('last_used_ip', 45)->nullable()->comment('Última IP de uso');
            $table->timestamp('last_used_at')->nullable()->comment('Último uso');

            $table->timestamps();

            // Índices para rendimiento y seguridad
            $table->unique(['user_id', 'is_default'], 'unique_default_per_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_cards');
    }
};
