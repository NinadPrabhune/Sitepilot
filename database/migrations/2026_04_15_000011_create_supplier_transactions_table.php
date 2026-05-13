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
        // SAFETY CHECK: Only create if table doesn't exist
        if (!Schema::hasTable('supplier_transactions')) {
            Schema::create('supplier_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reference_type', 50); // po, invoice, payment, advance, grn, adjustment
                $table->unsignedBigInteger('reference_id');
                $table->decimal('debit', 15, 2)->default(0.00);
                $table->decimal('credit', 15, 2)->default(0.00);
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->text('description')->nullable();
                $table->json('meta')->nullable();
                $table->foreignId('workspace_id')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();

                $table->index(['supplier_id', 'site_id']);
                $table->index(['reference_type', 'reference_id']);
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_transactions');
    }
};
