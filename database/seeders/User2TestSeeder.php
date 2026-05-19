<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * User 2 Test Data Seeder
 * Creates test data specifically for site_id=1 and created_by=2
 */
class User2TestSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('Starting User 2 Test Data Setup...');
        
        DB::beginTransaction();
        
        try {
            $this->createUser2();
            $this->createFoundationActivity();
            $this->createTestMachinery();
            $this->createActivityCompletion();
            $this->createDPRWithMinimumBilling();
            $this->createDieselConsumption();
            $this->createManPower();
            $this->createLedgerEntries();
            
            Log::info('User 2 Test Data Setup completed successfully');
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User 2 Test Data Setup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createUser2(): void
    {
        // Check if user 2 exists
        $existing = DB::table('users')->where('id', 2)->first();
        if ($existing) {
            Log::info('User 2 already exists');
            return;
        }

        DB::table('users')->insert([
            'id' => 2,
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('User 2 created');
    }

    private function createFoundationActivity(): void
    {
        // Check if activity already exists
        $existing = DB::table('activities')->where('title', 'Foundation Work - User 2')->first();
        if ($existing) {
            Log::info('Foundation activity for User 2 already exists');
            return;
        }

        $activityId = DB::table('activities')->insertGetId([
            'title' => 'Foundation Work - User 2',
            'scope' => 'Building foundation for main structure - User 2',
            'quantity' => 100,
            'unit' => 'cubic_meters',
            'priority' => 'high',
            'status' => 'pending',
            'start_date' => '2026-05-01',
            'due_date' => '2026-05-05',
            'created_by' => 2, // User 2
            'workspace_id' => 1,
            'site_id' => 1, // Site 1
            'assign_to' => '2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("Foundation activity created with ID: {$activityId}");
    }

    private function createTestMachinery(): void
    {
        // Check if machinery already exists
        $existing = DB::table('machineries')->where('vehicle_number', 'RENT-002-USER2')->first();
        if ($existing) {
            Log::info('Test machinery for User 2 already exists');
            return;
        }

        $supplier = DB::table('suppliers')->where('name', 'Test Rental Supplier')->first();

        $machineryId = DB::table('machineries')->insertGetId([
            'name' => 'RENT-002-USER2 - Test Complex Rental',
            'owned_by' => 'rental',
            'rate' => 1200,
            'rate_type' => 'hourly',
            'minimum_billing_hours' => 8,
            'diesel_by_company' => true,
            'operator_by_supplier' => true,
            'number_of_operators' => 2,
            'vehicle_number' => 'RENT-002-USER2',
            'category_id' => 1,
            'supplier_id' => $supplier->id,
            'operational_status' => 'active',
            'site_id' => 1, // Site 1
            'workspace_id' => 1,
            'created_by' => 2, // User 2
            'status' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("Test machinery created with ID: {$machineryId}");
    }

    private function createActivityCompletion(): void
    {
        // Check if completion already exists
        $existing = DB::table('activities_completed')->where('completed_quantity', 20)->first();
        if ($existing) {
            Log::info('Activity completion already exists');
            return;
        }

        $activity = DB::table('activities')->where('title', 'Foundation Work - User 2')->first();

        $completionId = DB::table('activities_completed')->insertGetId([
            'activity_id' => $activity->id,
            'completed_quantity' => 20,
            'completed_date' => '2026-05-01',
            'created_by' => 2, // User 2
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("Activity completion created with ID: {$completionId}");
    }

    private function createDPRWithMinimumBilling(): void
    {
        // Check if DPR already exists
        $existing = DB::table('daily_progress_reports')->where('date', '2026-05-01')->first();
        if ($existing) {
            Log::info('DPR already exists');
            return;
        }

        $completion = DB::table('activities_completed')->where('completed_quantity', 20)->first();
        $machinery = DB::table('machineries')->where('vehicle_number', 'RENT-002-USER2')->first();

        // Calculate with minimum billing enforcement
        $actualHours = 6; // End: 106 - Start: 100
        $minimumHours = $machinery->minimum_billing_hours; // 8
        $billableHours = max($actualHours, $minimumHours); // Should be 8
        $calculatedAmount = $billableHours * $machinery->rate; // 8 * 1200 = 9600

        $dprId = DB::table('daily_progress_reports')->insertGetId([
            'date' => '2026-05-01',
            'machinery_id' => $machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 106, // Only 6 hours usage
            'machine_idle_reading' => 0,
            'number_of_operators' => 2,
            'work_details' => 'Foundation excavation work - User 2',
            'diesel_consumption' => 40,
            'maintenance_notes' => 'Normal operation - User 2',
            'billable_hours' => $billableHours,
            'calculated_amount' => $calculatedAmount,
            'activity_completed_id' => $completion->id, // CRITICAL: Link to completion
            'status' => 'pending',
            'created_by' => 2, // User 2
            'workspace_id' => $machinery->workspace_id,
            'site_id' => 1, // Site 1
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("DPR created with ID: {$dprId} - Hours: {$billableHours}, Amount: {$calculatedAmount}");
    }

    private function createDieselConsumption(): void
    {
        // Check if consumption already exists
        $existing = DB::table('daily_consumption_masters')->where('consumption_date', '2026-05-01')->first();
        if ($existing) {
            Log::info('Diesel consumption already exists');
            return;
        }

        $completion = DB::table('activities_completed')->where('completed_quantity', 20)->first();
        $machinery = DB::table('machineries')->where('vehicle_number', 'RENT-002-USER2')->first();
        $dieselMaterial = DB::table('materials')->join('material_categories', 'materials.category_id', '=', 'material_categories.id')
            ->where('material_categories.name', 'Fuel')
            ->where('materials.name', 'Diesel')
            ->select('materials.*')
            ->first();

        // Create consumption master
        $masterId = DB::table('daily_consumption_masters')->insertGetId([
            'consumption_number' => 'DCM-0001-USER2',
            'consumption_date' => '2026-05-01',
            'site_id' => 1, // Site 1
            'consumption_type' => 'fuel',
            'machinery_type' => 'rental',
            'machinery_id' => $machinery->id,
            'activity_completed_id' => $completion->id, // CRITICAL: Link to completion
            'daily_progress_report_id' => null,
            'created_by' => 2, // User 2
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create consumption detail
        $dieselCost = 40 * $dieselMaterial->price; // 40 * 85.50 = 3420
        
        DB::table('daily_consumption_details')->insert([
            'daily_consumption_master_id' => $masterId,
            'material_id' => $dieselMaterial->id,
            'quantity' => 40,
            'unit_price' => $dieselMaterial->price,
            'total_price' => $dieselCost,
            'remarks' => 'Diesel consumption for RENT-002-USER2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("Diesel consumption created - Cost: {$dieselCost}");
    }

    private function createManPower(): void
    {
        // Check if manpower already exists
        $existing = DB::table('man_power_masters')->where('work_date', '2026-05-01')->first();
        if ($existing) {
            Log::info('ManPower already exists');
            return;
        }

        $completion = DB::table('activities_completed')->where('completed_quantity', 20)->first();
        $supplier = DB::table('suppliers')->where('name', 'Test Rental Supplier')->first();

        // Create manpower master
        $masterId = DB::table('man_power_masters')->insertGetId([
            'work_date' => '2026-05-01',
            'supplier_id' => $supplier->id,
            'site_id' => 1, // Site 1
            'activity_completed_id' => $completion->id, // CRITICAL: Link to completion
            'created_by' => 2, // User 2
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create manpower details
        DB::table('man_power_details')->insert([
            ['man_power_master_id' => $masterId, 'man_power_type_id' => 1, 'count' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['man_power_master_id' => $masterId, 'man_power_type_id' => 2, 'count' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Log::info("ManPower created with ID: {$masterId}");
    }

    private function createLedgerEntries(): void
    {
        // Check if ledger entries already exist
        $existing = DB::table('machinery_ledger')->count();
        if ($existing > 0) {
            Log::info('Ledger entries already exist');
            return;
        }

        $machinery = DB::table('machineries')->where('vehicle_number', 'RENT-002-USER2')->first();
        $dpr = DB::table('daily_progress_reports')->where('date', '2026-05-01')->first();
        $consumptionMaster = DB::table('daily_consumption_masters')->where('consumption_date', '2026-05-01')->first();

        // Create DPR credit entry
        DB::table('machinery_ledger')->insert([
            'machinery_id' => $machinery->id,
            'workspace_id' => $machinery->workspace_id,
            'entry_direction' => 'credit',
            'entry_type' => 'reading',
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'amount' => $dpr->calculated_amount, // Should be 9600
            'running_balance' => $dpr->calculated_amount,
            'date' => $dpr->date,
            'description' => "DPR Credit: {$dpr->billable_hours}h for machinery ID {$machinery->id}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create diesel debit entry
        $dieselCost = DB::table('daily_consumption_details')
            ->join('daily_consumption_masters', 'daily_consumption_details.daily_consumption_master_id', '=', 'daily_consumption_masters.id')
            ->where('daily_consumption_masters.id', $consumptionMaster->id)
            ->value('total_price');

        DB::table('machinery_ledger')->insert([
            'machinery_id' => $machinery->id,
            'workspace_id' => $machinery->workspace_id,
            'entry_direction' => 'debit',
            'entry_type' => 'diesel',
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => $consumptionMaster->id,
            'amount' => $dieselCost, // Should be 3420
            'running_balance' => $dpr->calculated_amount - $dieselCost, // 9600 - 3420 = 6180
            'date' => $consumptionMaster->consumption_date,
            'description' => "Diesel consumption: 40L for {$machinery->name}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("Ledger entries created - DPR Credit: {$dpr->calculated_amount}, Diesel Debit: {$dieselCost}");
    }

    /**
     * Get test data for validation
     */
    public function getTestData(): array
    {
        return [
            'activity' => DB::table('activities')->where('title', 'Foundation Work - User 2')->first(),
            'completion' => DB::table('activities_completed')->where('completed_quantity', 20)->first(),
            'machinery' => DB::table('machineries')->where('vehicle_number', 'RENT-002-USER2')->first(),
            'dpr' => DB::table('daily_progress_reports')->where('date', '2026-05-01')->first(),
            'consumption' => DB::table('daily_consumption_masters')->where('consumption_date', '2026-05-01')->first(),
            'manpower' => DB::table('man_power_masters')->where('work_date', '2026-05-01')->first(),
            'ledger_entries' => DB::table('machinery_ledger')->get(),
        ];
    }
}
