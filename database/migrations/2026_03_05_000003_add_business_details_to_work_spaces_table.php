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
        Schema::table('work_spaces', function (Blueprint $table) {
            $table->string('website')->nullable()->after('country');
            $table->string('cin_no')->nullable()->after('website');
            $table->string('logo')->nullable()->after('cin_no');
            $table->longText('terms_and_conditions')->nullable()->after('logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_spaces', function (Blueprint $table) {
            $table->dropColumn(['website', 'cin_no', 'logo', 'terms_and_conditions']);
        });
    }
};
