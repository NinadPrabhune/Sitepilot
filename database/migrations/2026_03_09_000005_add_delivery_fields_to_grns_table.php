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
        Schema::table('grns', function (Blueprint $table) {
            $table->string('delivery_challan_number')->nullable()->after('grn_date');
            $table->string('vehicle_number')->nullable()->after('delivery_challan_number');
            $table->string('gate_entry_number')->nullable()->after('vehicle_number');
            $table->string('delivery_challan_file')->nullable()->after('gate_entry_number');
            $table->string('reference_file')->nullable()->after('delivery_challan_file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            $table->dropColumn(['delivery_challan_number', 'vehicle_number', 'gate_entry_number', 'delivery_challan_file', 'reference_file']);
        });
    }
};
