<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SAFE: Create machinery_ledger (singular) only if it does not exist.
     * machinery_ledger is the canonical table — never create a second ledger table.
     */
    public function up(): void
    {
        if (!Schema::hasTable('machinery_ledger')) {
            Schema::create('machinery_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('workspace_id');
                $table->enum('entry_direction', ['credit', 'debit']);
                $table->string('entry_type');
                $table->string('reference_type', 50)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->decimal('amount', 15, 2);
                $table->decimal('running_balance', 15, 2)->default(0);
                $table->date('date');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->string('idempotency_key', 100)->nullable();
                $table->boolean('is_reversal')->default(false);
                $table->boolean('is_locked')->default(false);
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamps();

                $table->index(['machinery_id', 'date']);
                $table->index(['reference_type', 'reference_id']);
                $table->index(['machinery_id', 'workspace_id', 'date', 'id']);
                $table->index('reversed_entry_id');
                $table->index('is_locked');

                // Self-referential FK for reversal chain
                $table->unsignedBigInteger('reversed_entry_id')->nullable()
                    ->constrained('machinery_ledger')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Safe no-op: this migration is for bootstrap-only purposes.
        // dropping machinery_ledger in a down() on a live database would
        // destroy financial history — never do that automatically.
    }
};
