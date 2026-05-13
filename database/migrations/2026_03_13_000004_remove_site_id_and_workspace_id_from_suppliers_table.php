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
        // Only modify table if it exists
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                // Drop columns individually with existence checks
                $columnsToDrop = ['site_id', 'workspace_id'];
                
                foreach ($columnsToDrop as $column) {
                    if (Schema::hasColumn('suppliers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only modify table if it exists
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                // Only add columns if they don't exist
                if (!Schema::hasColumn('suppliers', 'site_id')) {
                    $table->unsignedBigInteger('site_id')->nullable();
                }
                if (!Schema::hasColumn('suppliers', 'workspace_id')) {
                    $table->unsignedBigInteger('workspace_id')->nullable();
                }
            });
        }
    }
};
