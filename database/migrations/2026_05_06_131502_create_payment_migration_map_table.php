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
        Schema::create('payment_migration_map', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('payment_id')->unique();
            $table->string('payment_number')->nullable();
            $table->integer('old_po_id')->nullable()->index();
            $table->string('old_payment_type')->nullable();
            $table->integer('old_invoice_id')->nullable();
            $table->integer('old_allocation_id')->nullable();
            $table->decimal('old_allocated_amount', 15)->nullable();
            $table->integer('new_invoice_id')->nullable()->index();
            $table->string('new_payment_type')->nullable();
            $table->string('migration_phase')->default('phase3')->index();
            $table->string('migration_batch')->nullable()->index();
            $table->timestamp('migrated_at')->useCurrent();
            $table->integer('migrated_by')->nullable();
            $table->enum('transformation_type', ['direct_invoice_link', 'allocation_to_invoice', 'manual_intervention', 'no_change', 'error'])->default('no_change')->index();
            $table->boolean('validated')->default(false);
            $table->text('validation_notes')->nullable();
            $table->decimal('amount_before', 15)->nullable();
            $table->decimal('amount_after', 15)->nullable();
            $table->decimal('amount_difference', 15)->nullable();
            $table->text('notes')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_migration_map');
    }
};
