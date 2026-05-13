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
        Schema::create('announcements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 255);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('branch_id');
            $table->string('department_id', 255);
            $table->string('employee_id', 255);
            $table->longText('description');
            $table->integer('workspace')->nullable();
            $table->bigInteger('site_id');
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
