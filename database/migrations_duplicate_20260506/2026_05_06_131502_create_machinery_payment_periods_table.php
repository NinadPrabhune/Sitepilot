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
        Schema::create('machinery_payment_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('machinery_id');
            $table->unsignedBigInteger('workspace_id')->index('machinery_payment_periods_workspace_id_foreign');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_locked')->default(false)->index('mpp_locked');
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('payment_request_id')->nullable()->index('pp_pr_id');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->index('machinery_payment_periods_created_by_foreign');
            $table->timestamps();

            $table->index(['machinery_id', 'workspace_id'], 'mpp_mach_ws');
            $table->index(['machinery_id', 'workspace_id', 'start_date', 'end_date'], 'pp_mach_ws_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machinery_payment_periods');
    }
};
