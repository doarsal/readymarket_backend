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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('effective_start_date')->nullable()->after('status');
            $table->timestamp('commitment_end_date')->nullable()->after('effective_start_date');
            $table->boolean('auto_renew_enabled')->default(false)->after('commitment_end_date');
            $table->string('billing_cycle')->nullable()->after('auto_renew_enabled'); // monthly, annual, etc.
            $table->timestamp('cancellation_allowed_until_date')->nullable()->after('billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'effective_start_date',
                'commitment_end_date',
                'auto_renew_enabled',
                'billing_cycle',
                'cancellation_allowed_until_date'
            ]);
        });
    }
};
