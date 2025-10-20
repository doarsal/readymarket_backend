<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_check_out_item', function(Blueprint $table) {
            $table->boolean('status')->default(true)->after('check_out_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('cart_check_out_item', function(Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
