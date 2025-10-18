<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('check_out_items', function(Blueprint $table) {
            $table->id();
            $table->string('item');
            $table->string('description')->nullable();
            $table->string('price')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->string('min_cart_amount')->nullable();
            $table->string('max_cart_amount')->nullable();
            $table->integer('percentage_of_amount')->nullable();
            $table->string('help_cta')->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_out_items');
    }
};
