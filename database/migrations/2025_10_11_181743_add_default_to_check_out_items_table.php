<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('check_out_items', function(Blueprint $table) {
            $table->boolean('default')->default(true)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('check_out_items', function(Blueprint $table) {
            $table->dropColumn('default');
        });
    }
};
