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
            // Add site_id column if it doesn't exist
            if (!Schema::hasColumn('suppliers', 'site_id')) {
                $table->integer('site_id')->nullable()->default(null);
            }
            // Add workspace_id column if it doesn't exist
            if (!Schema::hasColumn('suppliers', 'workspace_id')) {
                $table->integer('workspace_id')->default(0);
            }
            // Add created_by column if it doesn't exist
            if (!Schema::hasColumn('suppliers', 'created_by')) {
                $table->integer('created_by')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'workspace_id')) {
                $table->dropColumn('workspace_id');
            }
        });
    }
};
