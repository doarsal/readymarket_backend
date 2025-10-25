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
        Schema::table('microsoft_accounts', function (Blueprint $table) {
            // Campo para almacenar el email del Global Admin cuando se vincula una cuenta existente
            $table->string('global_admin_email', 255)->nullable()->after('email');
            
            // Campo para identificar el tipo de cuenta (created: nueva, linked: existente vinculada)
            $table->enum('account_type', ['created', 'linked'])->default('created')->after('is_pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('microsoft_accounts', function (Blueprint $table) {
            $table->dropColumn(['global_admin_email', 'account_type']);
        });
    }
};
