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
        Schema::create('employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->string('name', 255);
            $table->date('dob')->nullable();
            $table->string('gender', 255);
            $table->string('phone', 255)->nullable();
            $table->longText('address');
            $table->string('email', 255);
            $table->string('password', 255)->nullable();
            $table->string('employee_id', 255);
            $table->integer('branch_id');
            $table->integer('department_id');
            $table->integer('designation_id');
            $table->string('company_doj', 255)->nullable();
            $table->string('documents', 255)->nullable();
            $table->string('account_holder_name', 255)->nullable();
            $table->string('account_number', 255)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_identifier_code', 255)->nullable();
            $table->string('branch_location', 255)->nullable();
            $table->string('tax_payer_id', 255)->nullable();
            $table->integer('salary_type')->nullable();
            $table->integer('account_type')->nullable();
            $table->string('passport_country', 255)->nullable();
            $table->string('passport', 255)->nullable();
            $table->string('location_type', 255)->nullable();
            $table->string('country', 255)->nullable();
            $table->string('state', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('zipcode', 255)->nullable();
            $table->double('hours_per_day')->nullable();
            $table->integer('annual_salary')->nullable();
            $table->integer('days_per_week')->nullable();
            $table->integer('fixed_salary')->nullable();
            $table->double('hours_per_month')->nullable();
            $table->integer('rate_per_day')->nullable();
            $table->integer('days_per_month')->nullable();
            $table->integer('rate_per_hour')->nullable();
            $table->string('payment_requires_work_advice', 255)->default('off');
            $table->integer('salary')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('workspace')->nullable();
            $table->integer('created_by');
            $table->string('avatar', 255)->nullable();
            $table->string('organisation_switch', 255)->nullable();
            $table->string('provident_fund_no', 255)->nullable();
            $table->string('emergency_contact_no', 255)->nullable();
            $table->string('emergency_address', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
