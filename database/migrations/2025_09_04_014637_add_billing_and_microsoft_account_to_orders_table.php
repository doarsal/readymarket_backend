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
            // Solo agregar referencia a microsoft_account (billing_information_id ya existe)
            $table->foreignId('microsoft_account_id')->nullable()->after('billing_information_id')->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Eliminar foreign key y columna de microsoft_account
            $table->dropForeign(['microsoft_account_id']);
            $table->dropColumn('microsoft_account_id');
        });
    }
};
