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
        Schema::table('machinery_ledger', function (Blueprint $table) {
            // Billing protection columns
            $table->boolean('is_billed')->default(false)->after('is_reversal');
            $table->timestamp('billed_at')->nullable()->after('is_billed');
            
            // Financial snapshot versioning columns
            $table->decimal('calculation_version', 5, 2)->default(1.0)->after('billed_at');
            $table->decimal('formula_version', 5, 2)->default(1.0)->after('calculation_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            //
        });
    }
};
