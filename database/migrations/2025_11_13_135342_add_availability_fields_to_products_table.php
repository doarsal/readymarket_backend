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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_available')->default(true)->after('is_active')->comment('Indica si el producto está disponible en Microsoft Partner Center');
            $table->timestamp('availability_checked_at')->nullable()->after('is_available')->comment('Última vez que se verificó la disponibilidad');
            $table->text('availability_error')->nullable()->after('availability_checked_at')->comment('Error de disponibilidad si existe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_available', 'availability_checked_at', 'availability_error']);
        });
    }
};
