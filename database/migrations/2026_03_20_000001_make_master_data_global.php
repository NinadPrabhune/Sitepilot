<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration to make master data tables global
 * 
 * Removes workspace, site_id, and created_by columns from:
 * - document_types
 * - leave_types
 * - branches
 * - departments
 * - designations
 * 
 * Then truncates and inserts default data:
 * - Document Types (4 types)
 * - Leave Types (1 type)
 * - Branches (2 types)
 * - Departments (3 types)
 * - Designations (3 types)
 * 
 * This makes these tables GLOBAL (no workspace association)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Document Types - remove workspace, site_id, created_by
        if (Schema::hasTable('document_types')) {
            Schema::table('document_types', function (Blueprint $table) {
                if (Schema::hasColumn('document_types', 'workspace')) {
                    $table->dropColumn('workspace');
                }
                if (Schema::hasColumn('document_types', 'site_id')) {
                    $table->dropColumn('site_id');
                }
                if (Schema::hasColumn('document_types', 'created_by')) {
                    $table->dropColumn('created_by');
                }
            });
        }

        // Leave Types - remove workspace, created_by
        if (Schema::hasTable('leave_types')) {
            Schema::table('leave_types', function (Blueprint $table) {
                if (Schema::hasColumn('leave_types', 'workspace')) {
                    $table->dropColumn('workspace');
                }
                if (Schema::hasColumn('leave_types', 'site_id')) {
                    $table->dropColumn('site_id');
                }
                if (Schema::hasColumn('leave_types', 'created_by')) {
                    $table->dropColumn('created_by');
                }
            });
        }

        // Branches - remove workspace, created_by
        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table) {
                if (Schema::hasColumn('branches', 'workspace')) {
                    $table->dropColumn('workspace');
                }
                if (Schema::hasColumn('branches', 'created_by')) {
                    $table->dropColumn('created_by');
                }
                // Add type column for branch classification
                if (!Schema::hasColumn('branches', 'type')) {
                    $table->string('type')->nullable()->after('name');
                }
            });
        }

        // Departments - remove workspace, created_by
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (Schema::hasColumn('departments', 'workspace')) {
                    $table->dropColumn('workspace');
                }
                if (Schema::hasColumn('departments', 'created_by')) {
                    $table->dropColumn('created_by');
                }
                // Add branch_id column back for global use
                if (!Schema::hasColumn('departments', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->after('id');
                }
            });
        }

        // Designations - remove workspace, created_by
        if (Schema::hasTable('designations')) {
            Schema::table('designations', function (Blueprint $table) {
                if (Schema::hasColumn('designations', 'workspace')) {
                    $table->dropColumn('workspace');
                }
                if (Schema::hasColumn('designations', 'created_by')) {
                    $table->dropColumn('created_by');
                }
                // Add department_id column back for global use
                if (!Schema::hasColumn('designations', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable()->after('id');
                }
                // Add branch_id column for global use
                if (!Schema::hasColumn('designations', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->after('id');
                }
            });
        }

        // Now truncate tables in correct order (respecting foreign key dependencies)
        // and insert default global data
        
        // Truncate in reverse dependency order
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        if (Schema::hasTable('designations')) {
            \DB::table('designations')->truncate();
        }
        if (Schema::hasTable('departments')) {
            \DB::table('departments')->truncate();
        }
        if (Schema::hasTable('branches')) {
            \DB::table('branches')->truncate();
        }
        if (Schema::hasTable('leave_types')) {
            \DB::table('leave_types')->truncate();
        }
        if (Schema::hasTable('document_types')) {
            \DB::table('document_types')->truncate();
        }
        
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Insert default Document Types
        if (Schema::hasTable('document_types')) {
            $documentTypes = [
                ['name' => 'Employee Provident Fund Upload', 'is_required' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'ESIC CARD Upload', 'is_required' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Bank Details Upload', 'is_required' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Aadhar Card Upload', 'is_required' => 0, 'created_at' => now(), 'updated_at' => now()],
            ];
            \DB::table('document_types')->insert($documentTypes);
        }

        // Insert default Leave Types
        if (Schema::hasTable('leave_types')) {
            $leaveTypes = [
                ['title' => 'Casual', 'days' => 10, 'created_at' => now(), 'updated_at' => now()],
            ];
            \DB::table('leave_types')->insert($leaveTypes);
        }

        // Insert default Branches
        if (Schema::hasTable('branches')) {
            $branches = [
                ['name' => 'Head Office', 'type' => 'Corporate', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Project Site Office', 'type' => 'Site', 'created_at' => now(), 'updated_at' => now()],
            ];
            \DB::table('branches')->insert($branches);
        }

        // Insert default Departments
        if (Schema::hasTable('departments')) {
            $departments = [
                ['name' => 'Administration & HR', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Finance & Accounts', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Project Management', 'branch_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ];
            \DB::table('departments')->insert($departments);
        }

        // Insert default Designations
        if (Schema::hasTable('designations')) {
            $designations = [
                ['name' => 'HR Manager', 'department_id' => 1, 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Account Manager', 'department_id' => 2, 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Project Manager', 'department_id' => 3, 'branch_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ];
            \DB::table('designations')->insert($designations);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be reversed as we cannot restore dropped data
        // If rollback is needed, database backup is required
    }
};
