<?php

use App\Models\Cart;
use App\Models\CheckOutItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cart_check_out_item', function(Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Cart::class)->constrained('carts');
            $table->foreignIdFor(CheckOutItem::class)->constrained('check_out_items');
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_check_out_item');
    }
};
