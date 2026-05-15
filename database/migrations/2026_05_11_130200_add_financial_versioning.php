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
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            // Add financial versioning fields
            $table->string('calculation_version', 20)->default('1.0')->after('diesel_breakdown');
            $table->string('formula_version', 20)->default('1.0')->after('calculation_version');
            $table->string('diesel_rate_version', 20)->default('1.0')->after('formula_version');
            $table->json('calculation_metadata')->nullable()->after('diesel_rate_version');
            
            // Indexes for version tracking
            $table->index('calculation_version');
            $table->index('formula_version');
        });

        Schema::table('machinery_ledgers', function (Blueprint $table) {
            // Add versioning to ledger entries
            $table->string('calculation_version', 20)->default('1.0')->after('metadata');
            $table->string('formula_version', 20)->default('1.0')->after('calculation_version');
            
            // Indexes
            $table->index('calculation_version');
        });

        // Create calculation versions table for tracking
        Schema::create('calculation_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->string('type', 50); // calculation, formula, diesel_rate
            $table->text('description');
            $table->json('rules')->nullable(); // Version-specific rules
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            // Indexes
            $table->index(['type', 'is_active']);
            $table->index('effective_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('machinery_payment_requests', function (Blueprint $table) {
                $table->dropIndex(['calculation_version']);
                $table->dropIndex(['formula_version']);
                $table->dropColumn(['calculation_version', 'formula_version', 'diesel_rate_version', 'calculation_metadata']);
            });
        } catch (\Exception $e) {
            // ignore
        }
        try {
            Schema::table('machinery_ledgers', function (Blueprint $table) {
                $table->dropIndex(['calculation_version']);
                $table->dropColumn(['calculation_version', 'formula_version']);
            });
        } catch (\Exception $e) {
            // ignore
        }
        Schema::dropIfExists('calculation_versions');
    }
};
