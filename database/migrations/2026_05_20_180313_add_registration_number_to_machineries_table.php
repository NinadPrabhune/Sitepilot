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
        Schema::table('machineries', function (Blueprint $table) {
            if (!Schema::hasColumn('machineries', 'registration_number')) {
                $table->string('registration_number')->nullable()->after('vehicle_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machineries', function (Blueprint $table) {
            if (Schema::hasColumn('machineries', 'registration_number')) {
                $table->dropColumn('registration_number');
            }
        });
    }
};
