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
        // Add grn_pdf column to grns table
        if (Schema::hasTable('grns')) {
            Schema::table('grns', function (Blueprint $table) {
                if (!Schema::hasColumn('grns', 'grn_pdf')) {
                    $table->text('grn_pdf')->nullable()->after('reference_file');
                }
            });
        }

        // Add pi_pdf column to purchase_invoices table
        if (Schema::hasTable('purchase_invoices')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_invoices', 'pi_pdf')) {
                    $table->text('pi_pdf')->nullable()->after('invoice_file');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            if (Schema::hasColumn('grns', 'grn_pdf')) {
                $table->dropColumn('grn_pdf');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_invoices', 'pi_pdf')) {
                $table->dropColumn('pi_pdf');
            }
        });
    }
};
