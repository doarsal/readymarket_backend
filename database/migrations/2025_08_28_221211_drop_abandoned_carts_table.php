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
        Schema::dropIfExists('abandoned_carts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No restauramos la tabla, era innecesaria
    }
};
