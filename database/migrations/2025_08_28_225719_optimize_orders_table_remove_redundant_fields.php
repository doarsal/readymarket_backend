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
        Schema::table('orders', function (Blueprint $table) {
            // Agregar referencia a billing_information
            $table->foreignId('billing_information_id')->nullable()->after('store_id')
                  ->constrained('billing_information')->onDelete('set null')
                  ->comment('Referencia a los datos de facturación');

            // Quitar campos redundantes que ya están en users o billing_information
            $table->dropIndex('orders_customer_email_index');
            $table->dropColumn([
                'customer_email',     // Ya está en users.email
                'customer_phone',     // Ya está en billing_information.phone
                'billing_address',    // Ya está en billing_information
                'shipping_address'    // Por ahora usar billing como shipping
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Restaurar campos eliminados
            $table->string('customer_email')->comment('Email del cliente al momento de la compra');
            $table->string('customer_phone', 20)->nullable();
            $table->json('billing_address')->comment('Dirección de facturación');
            $table->json('shipping_address')->comment('Dirección de envío');

            // Quitar referencia a billing_information
            $table->dropForeign(['billing_information_id']);
            $table->dropColumn('billing_information_id');
        });
    }
};
