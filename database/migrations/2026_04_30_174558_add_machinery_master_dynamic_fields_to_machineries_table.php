<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Map existing enum data safely
        DB::table('machineries')
            ->where('owned_by', 'self_company')
            ->update(['owned_by' => 'owned']);
        DB::table('machineries')
            ->where('owned_by', 'rented_supplier')
            ->update(['owned_by' => 'rental']);

        Schema::table('machineries', function (Blueprint $table) {
            // Change owned_by enum to new values
            try {
                $table->enum('owned_by', ['owned', 'rental'])->default('owned')->change();
            } catch (\Exception $e) {
                // Column might already have the correct enum values
            }

            // Add machine_id (unique, auto-generated with format MCH-XXX)
            if (!Schema::hasColumn('machineries', 'machine_id')) {
                $table->string('machine_id')->nullable()->unique()->after('id');
            }
            // Note: Regex validation added at model level: regex:/^MCH-\d{3,4}$/

            // Add rental-specific fields
            if (!Schema::hasColumn('machineries', 'rate_type')) {
                $table->enum('rate_type', ['hourly', 'daily', 'monthly'])->nullable()->after('rate');
            }
            if (!Schema::hasColumn('machineries', 'minimum_billing_hours')) {
                $table->decimal('minimum_billing_hours', 8, 2)->nullable()->after('rate_type');
            }
            if (!Schema::hasColumn('machineries', 'diesel_by_company')) {
                $table->boolean('diesel_by_company')->default(false)->nullable()->after('minimum_billing_hours');
            }
            if (!Schema::hasColumn('machineries', 'operator_by_supplier')) {
                $table->boolean('operator_by_supplier')->default(false)->nullable()->after('diesel_by_company');
            }
            if (!Schema::hasColumn('machineries', 'number_of_operators')) {
                $table->integer('number_of_operators')->nullable()->after('operator_by_supplier');
            }
            if (!Schema::hasColumn('machineries', 'rental_agreement_file')) {
                $table->string('rental_agreement_file')->nullable()->after('number_of_operators');
            }

            // Add owned-specific fields
            if (!Schema::hasColumn('machineries', 'purchase_value')) {
                $table->decimal('purchase_value', 15, 2)->nullable()->after('purchase_date');
            }
            if (!Schema::hasColumn('machineries', 'insurance_due_date')) {
                $table->date('insurance_due_date')->nullable()->after('purchase_value');
            }
            if (!Schema::hasColumn('machineries', 'puc_due_date')) {
                $table->date('puc_due_date')->nullable()->after('insurance_due_date');
            }
            if (!Schema::hasColumn('machineries', 'fitness_due_date')) {
                $table->date('fitness_due_date')->nullable()->after('puc_due_date');
            }
            if (!Schema::hasColumn('machineries', 'last_service_date')) {
                $table->date('last_service_date')->nullable()->after('fitness_due_date');
            }
            if (!Schema::hasColumn('machineries', 'ownership_documents_file')) {
                $table->string('ownership_documents_file')->nullable()->after('last_service_date');
            }

            // Add index for performance
            if (!Schema::hasIndex('machineries', 'machineries_owned_by_index')) {
                $table->index('owned_by');
            }
            // Composite index already created in previous migration run
        });

        // Step 2: Generate machine_id for existing records
        $existingRecords = DB::table('machineries')->whereNull('machine_id')->orderBy('id')->get();
        foreach ($existingRecords as $index => $record) {
            $nextNumber = str_pad($record->id, 3, '0', STR_PAD_LEFT);
            DB::table('machineries')
                ->where('id', $record->id)
                ->update(['machine_id' => 'MCH-' . $nextNumber]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machineries', function (Blueprint $table) {
            // Drop new fields
            $table->dropIndex(['owned_by']);
            $table->dropColumn([
                'machine_id',
                'rate_type',
                'minimum_billing_hours',
                'diesel_by_company',
                'operator_by_supplier',
                'number_of_operators',
                'rental_agreement_file',
                'purchase_value',
                'insurance_due_date',
                'puc_due_date',
                'fitness_due_date',
                'last_service_date',
                'ownership_documents_file'
            ]);

            // Revert owned_by enum to old values
            $table->enum('owned_by', ['self_company', 'rented_supplier'])->default('self_company')->change();
        });

        // Revert enum data mapping
        DB::table('machineries')
            ->where('owned_by', 'owned')
            ->update(['owned_by' => 'self_company']);
        DB::table('machineries')
            ->where('owned_by', 'rental')
            ->update(['owned_by' => 'rented_supplier']);
    }
};
