<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::dropIfExists('general_transfer');

        Schema::create('general_transfer', function (Blueprint $table) {
            $table->id();
            $table->enum('transfer_type', ['machinery', 'tools_and_equipment', 'employee']);
            $table->unsignedBigInteger('machinery_id')->nullable();
            $table->unsignedBigInteger('tools_and_equipment_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();

            $table->date('transfer_date');
            $table->date('transfer_date_end')->nullable();

            $table->unsignedBigInteger('from_site_id')->nullable();
            $table->unsignedBigInteger('to_site_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();

            // 👇 New column
            $table->unsignedInteger('transfer_qty')->default(0);

            $table->enum('operational_status', ['pending', 'active', 'completed', 'cancelled'])->default('completed');
            $table->tinyInteger('status')->default(0);

            $table->timestamps();

            // Foreign keys
            $table->foreign('machinery_id')->references('id')->on('machineries')->onDelete('cascade');
            $table->foreign('tools_and_equipment_id')->references('id')->on('assets_tools_and_equipment')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('general_transfer');
    }
};
