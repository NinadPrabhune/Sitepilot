<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_payment_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machinery_id')->constrained()->onDelete('restrict');
            $table->foreignId('workspace_id')->constrained()->onDelete('restrict');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('payment_request_id')->nullable()->constrained('machinery_payment_requests')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            // Indexes
            $table->index(['machinery_id', 'workspace_id'], 'mpp_mach_ws');
            $table->index(['machinery_id', 'workspace_id', 'start_date', 'end_date'], 'pp_mach_ws_dates');
            $table->index('is_locked', 'mpp_locked');
            $table->index('payment_request_id', 'pp_pr_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_payment_periods');
    }
};
