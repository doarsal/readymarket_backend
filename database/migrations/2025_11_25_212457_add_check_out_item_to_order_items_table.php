<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function(Blueprint $table) {
            $table->foreignId('check_out_item_id')->after('product_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function(Blueprint $table) {
            $table->dropColumn('check_out_item_id');
        });
    }
};
