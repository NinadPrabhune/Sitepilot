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
        if (Schema::hasTable('supplier_transactions')) {
            if (!Schema::hasColumn('supplier_transactions', 'reference_amount')) {
                Schema::table('supplier_transactions', function (Blueprint $table) {
                    $table->decimal('reference_amount', 15, 2)->nullable()->after('reference_id');
                });
            }
            
            if (!Schema::hasColumn('supplier_transactions', 'updated_by')) {
                Schema::table('supplier_transactions', function (Blueprint $table) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('supplier_transactions')) {
            if (Schema::hasColumn('supplier_transactions', 'reference_amount')) {
                Schema::table('supplier_transactions', function (Blueprint $table) {
                    $table->dropColumn('reference_amount');
                });
            }
            
            if (Schema::hasColumn('supplier_transactions', 'updated_by')) {
                Schema::table('supplier_transactions', function (Blueprint $table) {
                    $table->dropColumn('updated_by');
                });
            }
        }
    }
};