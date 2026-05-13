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
        Schema::create('supplier_ledger_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->date('snapshot_date');
            $table->unsignedBigInteger('last_transaction_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['supplier_id', 'site_id', 'snapshot_date'], 'unique_supplier_site_date');
            $table->index(['supplier_id', 'site_id'], 'idx_supplier_site');
            $table->index('snapshot_date', 'idx_snapshot_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ledger_snapshots');
    }
};
