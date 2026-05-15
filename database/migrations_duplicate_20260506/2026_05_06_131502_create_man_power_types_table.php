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
        Schema::create('man_power_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->boolean('status')->default(false);
            $table->unsignedBigInteger('site_id')->nullable()->index('man_power_types_site_id_foreign');
            $table->unsignedBigInteger('created_by')->index('man_power_types_created_by_foreign');
            $table->unsignedBigInteger('workspace_id')->nullable()->index('man_power_types_workspace_id_foreign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('man_power_types');
    }
};
