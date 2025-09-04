<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCfdiUsageIdToBillingInformationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('billing_information', function (Blueprint $table) {
            $table->bigInteger('cfdi_usage_id')->unsigned()->nullable()->after('tax_regime_id');
            $table->foreign('cfdi_usage_id')->references('id')->on('cfdi_usages')->onDelete('set null');
            $table->index('cfdi_usage_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('billing_information', function (Blueprint $table) {
            $table->dropForeign(['cfdi_usage_id']);
            $table->dropIndex(['cfdi_usage_id']);
            $table->dropColumn('cfdi_usage_id');
        });
    }
}
