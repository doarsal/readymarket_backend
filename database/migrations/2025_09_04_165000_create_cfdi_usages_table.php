<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCfdiUsagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_usages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique()->comment('SAT code for CFDI usage');
            $table->string('description', 255)->comment('Description of CFDI usage');
            $table->boolean('applies_to_physical')->default(false)->comment('Applies to physical persons');
            $table->boolean('applies_to_moral')->default(false)->comment('Applies to moral persons');
            $table->json('applicable_tax_regimes')->nullable()->comment('JSON array of applicable tax regime codes');
            $table->boolean('active')->default(true);
            $table->bigInteger('store_id')->unsigned()->nullable();
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('active');
            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cfdi_usages');
    }
}
