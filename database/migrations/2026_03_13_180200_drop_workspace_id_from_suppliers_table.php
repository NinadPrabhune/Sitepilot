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
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'workspace_id')) {
                $table->dropColumn('workspace_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only modify table if it exists
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                // Only add column if it doesn't exist
                if (!Schema::hasColumn('suppliers', 'workspace_id')) {
                    $table->integer('workspace_id')->default(0);
                }
            });
        }
    }
};
