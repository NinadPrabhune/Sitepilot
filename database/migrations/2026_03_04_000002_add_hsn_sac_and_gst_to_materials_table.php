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
        Schema::table('materials', function (Blueprint $table) {
            $table->string('hsn_sac', 20)->nullable()->after('sku');
            $table->unsignedBigInteger('gst_master_id')->nullable()->after('hsn_sac');
            $table->foreign('gst_master_id')->references('id')->on('gst_masters')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['gst_master_id']);
            $table->dropColumn(['hsn_sac', 'gst_master_id']);
        });
    }
};
