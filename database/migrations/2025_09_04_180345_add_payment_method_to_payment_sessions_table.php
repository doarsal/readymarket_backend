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
            $table->string('payment_method', 20)->nullable()->after('microsoft_account_id')->comment('Tipo de tarjeta: credit_card o debit_card');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
