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
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->foreignId('ledger_entry_id')->nullable()->after('id')->constrained('machinery_ledger')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // Drop foreign key safely
            try {
                $table->dropForeign(['ledger_entry_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
            
            // Drop column if it exists
            if (Schema::hasColumn('daily_progress_reports', 'ledger_entry_id')) {
                $table->dropColumn('ledger_entry_id');
            }
        });
    }
};
