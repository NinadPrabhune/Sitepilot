<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create machinery_ledgers table
     */
    public function up(): void
    {
        if (!Schema::hasTable('machinery_ledgers')) {
            Schema::create('machinery_ledgers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('workspace_id');
                $table->enum('entry_direction', ['credit', 'debit']);
                $table->string('entry_type');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->decimal('amount', 15, 2);
                $table->decimal('running_balance', 15, 2)->default(0);
                $table->date('date');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->string('idempotency_key')->nullable();
                $table->boolean('is_reversal')->default(false);
                $table->unsignedBigInteger('reversal_of_id')->nullable();
                $table->unsignedBigInteger('payment_request_id')->nullable();
                $table->timestamps();

                $table->index(['machinery_id', 'date']);
                $table->index(['reference_type', 'reference_id']);
                $table->index(['payment_request_id']);
                $table->index(['is_reversal', 'reversal_of_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_ledgers');
    }
};
