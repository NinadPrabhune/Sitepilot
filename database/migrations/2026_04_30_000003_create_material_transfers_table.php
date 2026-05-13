<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Only create table if it doesn't exist
        if (!Schema::hasTable('material_transfers')) {
            Schema::create('material_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_site_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('to_site_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('material_id')->constrained('materials')->onDelete('cascade');
            $table->foreignId('workspace_id')->constrained('work_spaces')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Transfer details
            $table->decimal('quantity', 15, 2)->default(0);
            $table->string('unit')->default('unit');
            $table->decimal('transport_cost', 15, 2)->default(0);
            $table->date('transfer_date')->nullable();
            $table->text('notes')->nullable();
            
            // Approval workflow
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Ledger integration
            $table->unsignedBigInteger('ledger_entry_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['from_site_id', 'to_site_id']);
            $table->index('status');
            $table->index('transfer_date');
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('material_transfers');
    }
};
