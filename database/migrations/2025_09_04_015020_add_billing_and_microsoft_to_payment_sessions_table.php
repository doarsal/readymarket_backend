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
        Schema::table('payment_sessions', function (Blueprint $table) {
            // Solo agregar microsoft_account_id (billing_information_id ya existe)
            $table->foreignId('microsoft_account_id')->nullable()->after('billing_information_id')->constrained('microsoft_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            // Eliminar solo microsoft_account_id
            $table->dropForeign(['microsoft_account_id']);
            $table->dropColumn('microsoft_account_id');
        });
    }
};
