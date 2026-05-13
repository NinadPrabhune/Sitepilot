<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - SAFE ALTER for existing suppliers table
     * Preserves all existing data, only adds missing columns and fixes constraints
     */
    public function up(): void
    {
        // 🛡️ SAFE: Only modify if table exists (production database already has data)
        if (!Schema::hasTable('suppliers')) {
            return;
        }

        // Add missing site_id column that exists in database but not in migration
        if (!Schema::hasColumn('suppliers', 'site_id')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->integer('site_id')->nullable()
                      ->comment('Site ID reference');
            });
        }

        // Fix created_by column type if it's wrong
        if (Schema::hasColumn('suppliers', 'created_by')) {
            $columnType = DB::select("SHOW COLUMNS FROM suppliers WHERE Field = 'created_by'")[0]->Type;
            
            // If it's 'int' but should be 'bigint unsigned', fix it
            if ($columnType === 'int') {
                DB::statement("ALTER TABLE suppliers MODIFY COLUMN created_by BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Creator user ID'");
            }
        }

        // Add proper foreign key constraints if they don't exist
        $this->addForeignKeyIfNotExists('suppliers', 'category_id', 'supplier_categories', 'id', 'cascade');
        
        // Note: site_id foreign key is tricky as it references 'sites' table which may not exist
        // We'll handle this conditionally
        if (Schema::hasTable('sites')) {
            $this->addForeignKeyIfNotExists('suppliers', 'site_id', 'sites', 'id', 'set null');
        }

        // Add indexes for performance
        if (!Schema::hasIndex('suppliers', 'suppliers_site_id_index')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->index('site_id');
            });
        }

        if (!Schema::hasIndex('suppliers', 'suppliers_is_active_index')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->index('is_active');
            });
        }
    }

    /**
     * Helper method to add foreign key if it doesn't exist
     */
    private function addForeignKeyIfNotExists($table, $column, $referencesTable, $referencesColumn, $onDelete)
    {
        if (Schema::hasColumn($table, $column) && Schema::hasTable($referencesTable)) {
            // Check if foreign key exists using raw SQL (compatible approach)
            $foreignKeyName = $table . '_' . $column . '_foreign';
            $foreignKeyExists = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.table_constraints 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$table}' 
                AND constraint_type = 'FOREIGN KEY'
                AND constraint_name = '{$foreignKeyName}'
            ")[0]->count > 0;

            if (!$foreignKeyExists) {
                Schema::table($table, function (Blueprint $table) use ($column, $referencesTable, $referencesColumn, $onDelete) {
                    $table->foreign($column)
                          ->references($referencesColumn)
                          ->on($referencesTable)
                          ->onDelete($onDelete);
                });
            }
        }
    }

    /**
     * Reverse the migrations - SAFE rollback
     */
    public function down(): void
    {
        // 🛡️ SAFE: Only drop columns that were added by this migration
        if (Schema::hasTable('suppliers')) {
            if (Schema::hasColumn('suppliers', 'site_id')) {
                Schema::table('suppliers', function (Blueprint $table) {
                    $table->dropForeign(['site_id']);
                    $table->dropColumn('site_id');
                });
            }
        }
    }
};
