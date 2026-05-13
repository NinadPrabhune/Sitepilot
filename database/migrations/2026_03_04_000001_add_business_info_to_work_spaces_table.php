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
            $table->string('contact_person')->nullable()->after('is_disable');
            $table->string('phone')->nullable()->after('contact_person');
            $table->string('email')->nullable()->after('phone');
            $table->text('address')->nullable()->after('email');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('pincode')->nullable()->after('state');
            $table->string('country')->nullable()->after('pincode');
            $table->string('gst_number')->nullable()->after('country');
            $table->string('pan_number')->nullable()->after('gst_number');
            $table->string('bank_name')->nullable()->after('pan_number');
            $table->string('account_number')->nullable()->after('bank_name');
            $table->string('ifsc_code')->nullable()->after('account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_spaces', function (Blueprint $table) {
            $table->dropColumn([
                'contact_person',
                'phone',
                'email',
                'address',
                'city',
                'state',
                'pincode',
                'country',
                'gst_number',
                'pan_number',
                'bank_name',
                'account_number',
                'ifsc_code',
            ]);
        });
    }
};
