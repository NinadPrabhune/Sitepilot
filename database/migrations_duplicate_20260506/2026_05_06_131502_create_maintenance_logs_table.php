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
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ledger_entry_id')->nullable()->index('maintenance_logs_ledger_entry_id_foreign');
            $table->unsignedBigInteger('machinery_id')->nullable()->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->date('maintenance_date')->index();
            $table->decimal('cost', 10)->nullable();
            $table->enum('paid_by', ['company', 'supplier'])->default('company');
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->unsignedBigInteger('site_id')->nullable()->index('maintenance_logs_site_id_foreign');
            $table->unsignedBigInteger('workspace_id')->nullable()->index('maintenance_logs_workspace_id_foreign');
            $table->unsignedBigInteger('created_by')->nullable()->index('maintenance_logs_created_by_foreign');
            $table->tinyInteger('status')->default(0);
            $table->timestamps();

            $table->index(['machinery_id', 'maintenance_date'], 'idx_machinery_date');
            $table->index(['site_id', 'maintenance_date'], 'idx_site_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};
