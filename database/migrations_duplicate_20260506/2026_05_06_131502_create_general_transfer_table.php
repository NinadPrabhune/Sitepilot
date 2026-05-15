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
        Schema::create('general_transfer', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('transfer_type', ['machinery', 'tools_and_equipment', 'employee']);
            $table->unsignedBigInteger('machinery_id')->nullable()->index('general_transfer_machinery_id_foreign');
            $table->unsignedBigInteger('tools_and_equipment_id')->nullable()->index('general_transfer_tools_and_equipment_id_foreign');
            $table->unsignedBigInteger('employee_id')->nullable()->index('general_transfer_employee_id_foreign');
            $table->date('transfer_date');
            $table->date('transfer_date_end')->nullable();
            $table->unsignedBigInteger('from_site_id')->nullable();
            $table->unsignedBigInteger('to_site_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->integer('transfer_qty')->default(0);
            $table->enum('operational_status', ['pending', 'active', 'completed', 'cancelled'])->default('completed');
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_transfer');
    }
};
