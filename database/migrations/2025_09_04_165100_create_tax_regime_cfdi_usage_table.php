<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaxRegimeCfdiUsageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tax_regime_cfdi_usage', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tax_regime_id')->unsigned();
            $table->bigInteger('cfdi_usage_id')->unsigned();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Foreign keys
            $table->foreign('tax_regime_id')->references('id')->on('tax_regimes')->onDelete('cascade');
            $table->foreign('cfdi_usage_id')->references('id')->on('cfdi_usages')->onDelete('cascade');

            // Unique constraint to prevent duplicates
            $table->unique(['tax_regime_id', 'cfdi_usage_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tax_regime_cfdi_usage');
    }
}
