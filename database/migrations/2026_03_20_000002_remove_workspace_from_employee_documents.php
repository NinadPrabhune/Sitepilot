<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove workspace, site_id, and created_by columns from employee_documents table
     * This makes employee documents global (no tenant dependency)
     */
    public function up(): void
    {
        if (Schema::hasTable('employee_documents')) {
            Schema::table('employee_documents', function (Blueprint $table) {
                if (Schema::hasColumn('employee_documents', 'workspace')) {
                    $table->dropColumn('workspace');
                }
                if (Schema::hasColumn('employee_documents', 'site_id')) {
                    $table->dropColumn('site_id');
                }
                if (Schema::hasColumn('employee_documents', 'created_by')) {
                    $table->dropColumn('created_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->integer('workspace')->nullable()->after('document_value');
            $table->integer('site_id')->nullable()->after('workspace');
            $table->integer('created_by')->nullable()->after('site_id');
        });
    }
};
