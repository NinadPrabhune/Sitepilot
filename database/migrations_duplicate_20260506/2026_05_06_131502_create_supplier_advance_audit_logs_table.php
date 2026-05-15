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
        Schema::create('supplier_advance_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_advance_id');
            $table->unsignedBigInteger('purchase_invoice_id')->nullable()->index('supplier_advance_audit_logs_purchase_invoice_id_foreign');
            $table->enum('action', ['allocation', 'rollback', 'adjustment', 'lock', 'unlock', 'reservation', 'unreservation']);
            $table->decimal('amount', 15)->nullable();
            $table->longText('before_state')->nullable();
            $table->longText('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();

            $table->index(['supplier_advance_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_advance_audit_logs');
    }
};
