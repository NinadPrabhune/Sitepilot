<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            // Add audit_snapshot if missing (original column that wasn't created)
            if (!Schema::hasColumn('machinery_payment_requests', 'audit_snapshot')) {
                $table->json('audit_snapshot')->nullable()->after('status');
            }
            
            // Add billing_month and billing_year if missing
            if (!Schema::hasColumn('machinery_payment_requests', 'billing_month')) {
                $table->unsignedTinyInteger('billing_month')->nullable()->after('period_end');
            }
            if (!Schema::hasColumn('machinery_payment_requests', 'billing_year')) {
                $table->unsignedInteger('billing_year')->nullable()->after('billing_month');
            }
            
            // Add missing columns from original schema
            if (!Schema::hasColumn('machinery_payment_requests', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('audit_snapshot');
            }
            
            // Add remarks if missing
            if (!Schema::hasColumn('machinery_payment_requests', 'remarks')) {
                $table->text('remarks')->nullable()->after('idempotency_key');
            }
            
            // Add rejected_by tracking if missing
            if (!Schema::hasColumn('machinery_payment_requests', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
            }
        });

        // Add unique index for idempotency_key scoped to workspace
        if (Schema::hasColumn('machinery_payment_requests', 'idempotency_key')) {
            try {
                Schema::table('machinery_payment_requests', function (Blueprint $table) {
                    $table->unique(['workspace_id', 'idempotency_key'], 'mp_ws_idempotency');
                });
            } catch (\Exception $e) {
                // Index may already exist
            }
        }
    }

    public function down(): void
    {
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            try {
                $table->dropUnique('mp_ws_idempotency');
            } catch (\Exception $e) {}
            
            if (Schema::hasColumn('machinery_payment_requests', 'rejected_by')) {
                $table->dropColumn('rejected_by');
            }
            if (Schema::hasColumn('machinery_payment_requests', 'remarks')) {
                $table->dropColumn('remarks');
            }
            if (Schema::hasColumn('machinery_payment_requests', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
            if (Schema::hasColumn('machinery_payment_requests', 'audit_snapshot')) {
                $table->dropColumn('audit_snapshot');
            }
            if (Schema::hasColumn('machinery_payment_requests', 'billing_month')) {
                $table->dropColumn('billing_month');
            }
            if (Schema::hasColumn('machinery_payment_requests', 'billing_year')) {
                $table->dropColumn('billing_year');
            }
        });
    }
};