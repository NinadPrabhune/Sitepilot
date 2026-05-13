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
            $table->id();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('machinery_ledger')->nullOnDelete();
            $table->foreignId('machinery_id')->nullable()->constrained('machineries')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('maintenance_date');
            $table->decimal('cost', 10, 2)->nullable();
            $table->enum('paid_by', ['company', 'supplier'])->default('company');
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
            
            $table->index('machinery_id');
            $table->index('vendor_id');
            $table->index('maintenance_date');
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
