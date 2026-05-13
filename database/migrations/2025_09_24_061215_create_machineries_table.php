<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('machineries');
        Schema::create('machineries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained('machinery_categories')->onDelete('cascade');
            $table->string('model_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->date('purchase_date')->nullable();
            
            $table->string('capacity')->nullable();
            $table->date('maintenance_schedule')->nullable();
            $table->text('remarks')->nullable();
            $table->text('description')->nullable();
            $table->string('vehicle_number')->nullable(); // e.g., MH12AB1234

            $table->enum('owned_by', ['self_company', 'rented_supplier'])->default('self_company');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');

            $table->enum('operational_status', ['active', 'breakdown', 'scrap'])->default('active');

            $table->integer('site_id')->nullable()->default(null);
            $table->integer('created_by')->default(0);
            $table->integer('workspace_id')->default(0);
            $table->string('status')->default('0'); // optional workflow status
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('machineries');
    }
};
