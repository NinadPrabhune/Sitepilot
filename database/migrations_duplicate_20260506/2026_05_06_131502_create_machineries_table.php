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
        Schema::create('machineries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('machine_id')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('category_id')->index('machineries_category_id_foreign');
            $table->string('model_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_value', 15)->nullable();
            $table->date('insurance_due_date')->nullable();
            $table->date('puc_due_date')->nullable();
            $table->date('fitness_due_date')->nullable();
            $table->date('last_service_date')->nullable();
            $table->string('ownership_documents_file')->nullable();
            $table->string('capacity')->nullable();
            $table->date('maintenance_schedule')->nullable();
            $table->text('remarks')->nullable();
            $table->text('description')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->enum('owned_by', ['owned', 'rental'])->default('owned')->index();
            $table->boolean('ownership_locked')->default(false)->index('idx_ownership_locked');
            $table->timestamp('ownership_locked_at')->nullable();
            $table->unsignedBigInteger('ownership_locked_by')->nullable()->index('machineries_ownership_locked_by_foreign');
            $table->decimal('rate', 15)->nullable()->comment('Hourly rate for machinery work');
            $table->enum('rate_type', ['hourly', 'daily', 'monthly'])->nullable();
            $table->decimal('minimum_billing_hours')->nullable();
            $table->boolean('diesel_by_company')->nullable()->default(false);
            $table->boolean('operator_by_supplier')->nullable()->default(false);
            $table->integer('number_of_operators')->nullable();
            $table->string('rental_agreement_file')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable()->index('machineries_supplier_id_foreign');
            $table->enum('operational_status', ['active', 'breakdown', 'scrap'])->default('active');
            $table->integer('site_id')->nullable();
            $table->integer('created_by')->default(0);
            $table->integer('workspace_id')->default(0);
            $table->string('status')->default('0');
            $table->timestamps();

            $table->index(['workspace_id', 'site_id', 'owned_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machineries');
    }
};
