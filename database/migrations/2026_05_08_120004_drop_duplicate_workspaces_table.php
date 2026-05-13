<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Drop duplicate workspaces table safely
     * This removes the duplicate 'workspaces' table and keeps the correct 'work_spaces' table
     */
    public function up(): void
    {
        // Check if the duplicate workspaces table exists before dropping
        if (Schema::hasTable('workspaces')) {
            
            // First, drop any foreign key constraints that reference the workspaces table
            $this->dropForeignKeysReferencingWorkspaces();
            
            // Now safely drop the duplicate workspaces table
            Schema::dropIfExists('workspaces');
            
            // Log the action for audit purposes
            \Log::info('Duplicate workspaces table dropped successfully');
        }
    }

    /**
     * Reverse the migrations - Recreate the workspaces table if needed
     * Note: This is for rollback purposes only
     */
    public function down(): void
    {
        // Only recreate if it doesn't already exist and work_spaces exists
        if (!Schema::hasTable('workspaces') && Schema::hasTable('work_spaces')) {
            Schema::create('workspaces', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->integer('created_by');
                $table->timestamps();
            });
            
            // Log the recreation
            \Log::info('workspaces table recreated for rollback');
        }
    }

    /**
     * Helper method to drop foreign keys that reference the workspaces table
     */
    private function dropForeignKeysReferencingWorkspaces()
    {
        // List of tables that might have foreign keys referencing workspaces
        $tablesToCheck = [
            'payments_module',
            'purchase_invoices',
            'users',
            'projects'
        ];

        foreach ($tablesToCheck as $table) {
            if (Schema::hasTable($table)) {
                // Check if workspace_id column exists in the table
                if (Schema::hasColumn($table, 'workspace_id')) {
                    try {
                        // Drop the foreign key constraint if it exists
                        Schema::table($table, function (Blueprint $table) {
                            $table->dropForeign(['workspace_id']);
                        });
                        
                        \Log::info("Dropped foreign key for {$table}.workspace_id");
                    } catch (\Exception $e) {
                        // Log error but continue - foreign key might not exist
                        \Log::warning("Could not drop foreign key for {$table}.workspace_id: " . $e->getMessage());
                    }
                }
            }
        }
    }
};
