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
            $table->id();
            $table->foreignId('supplier_advance_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('purchase_invoice_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action'); // allocation, rollback, adjustment, lock, unlock, reservation, unreservation
            $table->decimal('amount', 15, 2)->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('supplier_advance_id');
            $table->index('purchase_invoice_id');
            $table->index('action');
            $table->index('created_at');
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
