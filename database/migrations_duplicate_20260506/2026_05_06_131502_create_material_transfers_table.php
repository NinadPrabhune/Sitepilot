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
        Schema::create('material_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('record_number');
            $table->date('record_date');
            $table->unsignedBigInteger('from_site_id');
            $table->unsignedBigInteger('to_site_id');
            $table->decimal('total_amount', 12);
            $table->decimal('transport_cost', 15)->default(0);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('workspace_id');
            $table->string('record_file')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('approved_by')->nullable()->index('material_transfers_approved_by_foreign');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('ledger_entry_id')->nullable();

            $table->unique(['workspace_id', 'record_number'], 'unique_transfer_number_per_workspace');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_transfers');
    }
};
