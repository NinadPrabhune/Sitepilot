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
        Schema::create('man_power_masters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('work_date');
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('activity_completed_id')->nullable()->index();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('created_by');
            $table->integer('total_count')->default(0);
            $table->string('reference_file')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('man_power_masters');
    }
};
