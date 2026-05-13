<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->integer('employee_id');
                $table->date('date');
                $table->string('status');
                $table->time('clock_in')->nullable();
                $table->time('clock_out')->nullable();
                $table->time('late')->nullable();
                $table->time('early_leaving')->nullable();
                $table->time('overtime')->nullable();
                $table->time('total_rest')->nullable();

                $table->string('clock_in_latitude')->nullable();
                $table->string('clock_in_longitude')->nullable();
                $table->string('clock_out_latitude')->nullable();
                $table->string('clock_out_longitude')->nullable();
                $table->string('clock_in_image')->nullable();
                $table->string('clock_out_image')->nullable();

                $table->integer('workspace')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->integer('created_by');

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
