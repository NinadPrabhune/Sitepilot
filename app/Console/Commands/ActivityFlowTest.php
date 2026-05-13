<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Activity;
use App\Models\ActivityCompleted;
use App\Models\ManPowerMaster;
use App\Models\ManPowerDetail;
use App\Models\ManPowerType;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Machinery;
use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Models\User;
use App\Models\WorkSpace;
use App\Models\Supplier;
use Workdo\Taskly\Entities\Project;

/**
 * 🎯 ACTIVITY FLOW TEST
 * Comprehensive flow testing for Activity, ActivityCompleted, ManPower, DPR, and Material Consumption
 * with proper workspace_id, site_id, activity_completed_id relationships
 */
class ActivityFlowTest extends Command
{
    protected $signature = 'activity:flow-test
                            {--phase= : Run specific phase (1-6 or all)}
                            {--cleanup : Clean up test data after completion}
                            {--detailed : Show detailed output}';

    protected $description = '🎯 Activity Flow Test - Comprehensive flow testing with proper relationships';

    private $testData = [];
    private $phaseResults = [];

    public function handle()
    {
        $this->info('🎯 ACTIVITY FLOW TEST');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('Testing: Activity → ActivityCompleted → ManPower/DPR/Material');
        $this->info('Relationships: workspace_id, site_id, activity_completed_id');
        $this->newLine();

        $phase = $this->option('phase');
        $cleanup = $this->option('cleanup');
        $detailed = $this->option('detailed');

        DB::beginTransaction();

        try {
            if ($phase === null) {
                $this->runAllPhases();
            } else {
                $this->runSpecificPhase((int) $phase);
            }

            $this->displayResults();

            DB::rollBack();
            $this->info('🔄 Test transaction rolled back - database unchanged');

            if ($cleanup) {
                $this->cleanupTestData();
            }

            $this->newLine();
            $overallScore = $this->calculateOverallScore();

            if ($overallScore >= 80) {
                $this->info("✅ ACTIVITY FLOW VALIDATED ({$overallScore}%)");
                return 0;
            } else {
                $this->error("❌ VALIDATION FAILED ({$overallScore}%)");
                return 1;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Test execution failed: ' . $e->getMessage());
            Log::error('Activity flow test failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }
    }

    private function runAllPhases()
    {
        $phases = [
            1 => '📋 PHASE 1: Activity Creation (workspace_id, site_id)',
            2 => '✅ PHASE 2: ActivityCompleted Flow (activity_id)',
            3 => '👷 PHASE 3: ManPower Flow (activity_completed_id)',
            4 => '⚙️  PHASE 4: DPR Flow (activity_completed_id, machinery)',
            5 => '⛽ PHASE 5: Diesel/Consumption Flow (activity_completed_id)',
            6 => '📦 PHASE 6: Material Issue Flow (site_id, workspace_id)',
        ];

        foreach ($phases as $phaseNumber => $phaseName) {
            $this->runSpecificPhase($phaseNumber);
        }
    }

    private function runSpecificPhase(int $phaseNumber)
    {
        $phaseNames = [
            1 => '📋 PHASE 1: Activity Creation',
            2 => '✅ PHASE 2: ActivityCompleted Flow',
            3 => '👷 PHASE 3: ManPower Flow',
            4 => '⚙️  PHASE 4: DPR Flow',
            5 => '⛽ PHASE 5: Diesel/Consumption Flow',
            6 => '📦 PHASE 6: Material Issue Flow',
        ];

        if (!isset($phaseNames[$phaseNumber])) {
            $this->error("Invalid phase: {$phaseNumber}. Valid phases: 1-6");
            return;
        }

        $phaseName = $phaseNames[$phaseNumber];
        $this->info("Running {$phaseName}");
        $this->line(str_repeat('-', 60));

        $startTime = microtime(true);

        try {
            $result = match ($phaseNumber) {
                1 => $this->phase1ActivityCreation(),
                2 => $this->phase2ActivityCompleted(),
                3 => $this->phase3ManPowerFlow(),
                4 => $this->phase4DPRFlow(),
                5 => $this->phase5ConsumptionFlow(),
                6 => $this->phase6MaterialIssueFlow(),
                default => throw new \InvalidArgumentException("Invalid phase: {$phaseNumber}"),
            };

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            $this->phaseResults[$phaseNumber] = [
                'name' => $phaseName,
                'status' => $result ? 'PASS' : 'FAIL',
                'duration' => $duration,
            ];

            if ($result) {
                $this->info("✅ Phase {$phaseNumber} PASSED ({$duration}ms)");
            } else {
                $this->error("❌ Phase {$phaseNumber} FAILED ({$duration}ms)");
            }
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            $this->phaseResults[$phaseNumber] = [
                'name' => $phaseName,
                'status' => 'ERROR',
                'duration' => $duration,
                'error' => $e->getMessage(),
            ];

            $this->error("❌ Phase {$phaseNumber} ERROR: " . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Phase 1: Activity Creation with workspace_id and site_id
     */
    private function phase1ActivityCreation(): bool
    {
        $this->comment('Creating Activity with proper relationships...');

        $workspace = WorkSpace::first();
        $site = Project::first();
        $user = User::first();

        if (!$workspace || !$site || !$user) {
            $this->error('❌ Missing master data: workspace, site, or user');
            return false;
        }

        $this->testData['workspace_id'] = $workspace->id;
        $this->testData['site_id'] = $site->id;
        $this->testData['user_id'] = $user->id;

        $activity = Activity::create([
            'title' => 'FLOW TEST - Foundation Work',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'scope' => 'Complete foundation excavation and preparation',
            'quantity' => 100,
            'unit' => 'sqm',
            'priority' => 'high',
            'status' => 'in_progress',
            'created_by' => $user->id,
            'workspace_id' => $workspace->id,
            'site_id' => $site->id,
            'assign_to' => $user->id,
        ]);

        $this->testData['activity_id'] = $activity->id;

        $this->line("✅ Activity created: {$activity->title}");
        $this->line("   workspace_id: {$activity->workspace_id}");
        $this->line("   site_id: {$activity->site_id}");
        $this->line("   created_by: {$activity->created_by}");

        // Verify relationships
        if ($activity->workspace_id !== $workspace->id || $activity->site_id !== $site->id) {
            $this->error('❌ Activity relationships not set correctly');
            return false;
        }

        return true;
    }

    /**
     * Phase 2: ActivityCompleted Flow with activity_id relationship
     */
    private function phase2ActivityCompleted(): bool
    {
        $this->comment('Creating ActivityCompleted entry...');

        if (empty($this->testData['activity_id'])) {
            $this->error('❌ No activity found. Run Phase 1 first.');
            return false;
        }

        $activityCompleted = ActivityCompleted::create([
            'activity_id' => $this->testData['activity_id'],
            'completed_quantity' => 50,
            'completed_date' => now()->toDateString(),
            'created_by' => $this->testData['user_id'],
        ]);

        $this->testData['activity_completed_id'] = $activityCompleted->id;

        $this->line("✅ ActivityCompleted created");
        $this->line("   activity_id: {$activityCompleted->activity_id}");
        $this->line("   completed_quantity: {$activityCompleted->completed_quantity}");

        // Verify relationship
        $activity = $activityCompleted->activity;
        if (!$activity || $activity->id !== $this->testData['activity_id']) {
            $this->error('❌ ActivityCompleted → Activity relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: ActivityCompleted → Activity ({$activity->title})");

        return true;
    }

    /**
     * Phase 3: ManPower Flow with activity_completed_id, site_id, workspace_id
     */
    private function phase3ManPowerFlow(): bool
    {
        $this->comment('Creating ManPower records...');

        if (empty($this->testData['activity_completed_id'])) {
            $this->error('❌ No activity_completed found. Run Phase 2 first.');
            return false;
        }

        // Create ManPowerType if not exists
        $manPowerType = ManPowerType::first();
        if (!$manPowerType) {
            $manPowerType = ManPowerType::create([
                'name' => 'Skilled Labor',
                'status' => 'active',
                'site_id' => $this->testData['site_id'],
                'workspace_id' => $this->testData['workspace_id'],
                'created_by' => $this->testData['user_id'],
            ]);
        }

        // Get or create supplier for ManPower
        $supplier = Supplier::first();
        if (!$supplier) {
            $supplier = Supplier::create([
                'name' => 'Test Labor Supplier',
                'workspace_id' => $this->testData['workspace_id'],
                'created_by' => $this->testData['user_id'],
            ]);
        }

        // Create ManPowerMaster with proper relationships
        $manPowerMaster = ManPowerMaster::create([
            'work_date' => now()->toDateString(),
            'site_id' => $this->testData['site_id'],
            'activity_completed_id' => $this->testData['activity_completed_id'],
            'workspace_id' => $this->testData['workspace_id'],
            'supplier_id' => $supplier->id,
            'created_by' => $this->testData['user_id'],
            'total_count' => 10,
        ]);

        $this->testData['man_power_master_id'] = $manPowerMaster->id;

        $this->line("✅ ManPowerMaster created");
        $this->line("   activity_completed_id: {$manPowerMaster->activity_completed_id}");
        $this->line("   site_id: {$manPowerMaster->site_id}");
        $this->line("   workspace_id: {$manPowerMaster->workspace_id}");

        // Create ManPowerDetail
        $manPowerDetail = ManPowerDetail::create([
            'man_power_master_id' => $manPowerMaster->id,
            'man_power_type_id' => $manPowerType->id,
            'count' => 10,
        ]);

        $this->line("✅ ManPowerDetail created");

        // Verify relationships
        $activityCompleted = $manPowerMaster->activityCompleted;
        if (!$activityCompleted || $activityCompleted->id !== $this->testData['activity_completed_id']) {
            $this->error('❌ ManPowerMaster → ActivityCompleted relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: ManPowerMaster → ActivityCompleted");

        // Verify ActivityCompleted → ManPower relationship
        $manpowers = $activityCompleted->manpowers;
        if ($manpowers->isEmpty() || !$manpowers->contains('id', $manPowerMaster->id)) {
            $this->error('❌ ActivityCompleted → ManPower relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: ActivityCompleted → ManPower");

        return true;
    }

    /**
     * Phase 4: DPR Flow with activity_completed_id and machinery
     */
    private function phase4DPRFlow(): bool
    {
        $this->comment('Creating DPR linked to ActivityCompleted...');

        if (empty($this->testData['activity_completed_id'])) {
            $this->error('❌ No activity_completed found. Run Phase 2 first.');
            return false;
        }

        // Get or create machinery
        $machinery = Machinery::first();
        if (!$machinery) {
            $machinery = Machinery::create([
                'name' => 'Test Excavator',
                'owned_by' => 'owned',
                'rate' => 1500,
                'category_id' => 1,
                'workspace_id' => $this->testData['workspace_id'],
                'site_id' => $this->testData['site_id'],
                'created_by' => $this->testData['user_id'],
            ]);
        }

        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'operator_names' => 'Test Operator',
            'work_details' => 'Excavation work for activity',
            'status' => 'pending',
            'created_by' => $this->testData['user_id'],
            'workspace_id' => $this->testData['workspace_id'],
            'site_id' => $this->testData['site_id'],
            'activity_completed_id' => $this->testData['activity_completed_id'],
        ]);

        $this->testData['dpr_id'] = $dpr->id;
        $this->testData['machinery_id'] = $machinery->id;

        $this->line("✅ DPR created");
        $this->line("   activity_completed_id: {$dpr->activity_completed_id}");
        $this->line("   machinery_id: {$dpr->machinery_id}");
        $this->line("   site_id: {$dpr->site_id}");
        $this->line("   workspace_id: {$dpr->workspace_id}");

        // Verify DPR → ActivityCompleted relationship
        $activityCompleted = $dpr->activityCompleted;
        if (!$activityCompleted || $activityCompleted->id !== $this->testData['activity_completed_id']) {
            $this->error('❌ DPR → ActivityCompleted relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: DPR → ActivityCompleted");

        // Verify ActivityCompleted → DPR relationship
        $dprs = $activityCompleted->dailyProgressReports;
        if ($dprs->isEmpty() || !$dprs->contains('id', $dpr->id)) {
            $this->error('❌ ActivityCompleted → DPR relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: ActivityCompleted → DPR");

        return true;
    }

    /**
     * Phase 5: Diesel/Consumption Flow with activity_completed_id
     */
    private function phase5ConsumptionFlow(): bool
    {
        $this->comment('Creating Diesel Consumption linked to ActivityCompleted...');

        if (empty($this->testData['activity_completed_id']) || empty($this->testData['machinery_id'])) {
            $this->error('❌ Missing required data. Run Phases 2 and 4 first.');
            return false;
        }

        // Get or create material category and material
        $category = MaterialCategory::first();
        if (!$category) {
            $category = MaterialCategory::create([
                'name' => 'Fuel',
                'workspace_id' => $this->testData['workspace_id'],
                'created_by' => $this->testData['user_id'],
            ]);
        }

        $diesel = Material::where('name', 'like', '%diesel%')->first();
        if (!$diesel) {
            $diesel = Material::create([
                'name' => 'Diesel Fuel',
                'category_id' => $category->id,
                'unit' => 'liters',
                'workspace_id' => $this->testData['workspace_id'],
                'created_by' => $this->testData['user_id'],
            ]);
        }

        // Create DailyConsumptionMaster with activity_completed_id
        $consumption = DailyConsumptionMaster::create([
            'consumption_number' => 'CONS-' . rand(1000, 9999),
            'consumption_date' => now()->toDateString(),
            'consumption_type' => 'fuel',
            'machinery_id' => $this->testData['machinery_id'],
            'site_id' => $this->testData['site_id'],
            'activity_completed_id' => $this->testData['activity_completed_id'],
            'workspace_id' => $this->testData['workspace_id'],
            'created_by' => $this->testData['user_id'],
        ]);

        $this->testData['consumption_id'] = $consumption->id;

        $this->line("✅ DailyConsumptionMaster created");
        $this->line("   machinery_id: {$consumption->machinery_id}");
        $this->line("   site_id: {$consumption->site_id}");
        $this->line("   workspace_id: {$consumption->workspace_id}");
        $this->line("   activity_completed_id: {$consumption->activity_completed_id}");

        // Verify Consumption → ActivityCompleted relationship
        $activityCompleted = $consumption->activityCompleted;
        if (!$activityCompleted || $activityCompleted->id !== $this->testData['activity_completed_id']) {
            $this->error('❌ Consumption → ActivityCompleted relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: Consumption → ActivityCompleted");

        // Verify ActivityCompleted → Consumption relationship
        $consumptions = $activityCompleted->dailyConsumptions;
        if ($consumptions->isEmpty() || !$consumptions->contains('id', $consumption->id)) {
            $this->error('❌ ActivityCompleted → Consumption relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: ActivityCompleted → Consumption");

        // Create DailyConsumptionDetails
        $consumptionDetail = \App\Models\DailyConsumptionDetails::create([
            'daily_consumption_master_id' => $consumption->id,
            'material_id' => $diesel->id,
            'quantity' => 50,
            'unit' => 'liters',
            'remarks' => 'Diesel for excavator operation',
        ]);

        $this->line("✅ DailyConsumptionDetails created");
        $this->line("   material: {$diesel->name}");
        $this->line("   quantity: {$consumptionDetail->quantity} {$consumptionDetail->unit}");

        // Verify Consumption → Details relationship
        $details = $consumption->details;
        if ($details->isEmpty() || !$details->contains('id', $consumptionDetail->id)) {
            $this->error('❌ Consumption → Details relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: Consumption → Details");

        // Verify dailyConsumptionDetails alias works
        $dailyDetails = $consumption->dailyConsumptionDetails;
        if ($dailyDetails->isEmpty() || !$dailyDetails->contains('id', $consumptionDetail->id)) {
            $this->error('❌ Consumption → dailyConsumptionDetails relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: Consumption → dailyConsumptionDetails (alias)");

        // Verify Detail → Master relationship
        $master = $consumptionDetail->master;
        if (!$master || $master->id !== $consumption->id) {
            $this->error('❌ Detail → Master relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: Detail → Master");

        return true;
    }

    /**
     * Phase 6: Material Issue Flow with site_id and workspace_id
     */
    private function phase6MaterialIssueFlow(): bool
    {
        $this->comment('Creating Material Issue with proper relationships...');

        // Get material
        $material = Material::first();
        if (!$material) {
            $category = MaterialCategory::first();
            if (!$category) {
                $category = MaterialCategory::create([
                    'name' => 'General Materials',
                    'workspace_id' => $this->testData['workspace_id'],
                    'created_by' => $this->testData['user_id'],
                ]);
            }
            $material = Material::create([
                'name' => 'Cement',
                'category_id' => $category->id,
                'unit' => 'bags',
                'workspace_id' => $this->testData['workspace_id'],
                'created_by' => $this->testData['user_id'],
            ]);
        }

        // Create Material Issue
        $issue = MaterialIssue::create([
            'issue_number' => MaterialIssue::generateIssueNumber(),
            'site_id' => $this->testData['site_id'],
            'issue_to_type' => MaterialIssue::ISSUE_TO_USER,
            'issue_to_id' => $this->testData['user_id'],
            'issue_date' => now()->toDateString(),
            'status' => 'pending',
            'remarks' => 'Material issue for activity flow test',
            'created_by' => $this->testData['user_id'],
            'workspace_id' => $this->testData['workspace_id'],
        ]);

        $this->testData['material_issue_id'] = $issue->id;

        $this->line("✅ MaterialIssue created");
        $this->line("   issue_number: {$issue->issue_number}");
        $this->line("   site_id: {$issue->site_id}");
        $this->line("   workspace_id: {$issue->workspace_id}");
        $this->line("   issue_to_type: {$issue->issue_to_type}");

        // Create Material Issue Item
        $item = MaterialIssueItem::create([
            'issue_id' => $issue->id,
            'material_id' => $material->id,
            'quantity' => 50,
            'rate' => 350,
            'amount' => 17500,
            'remarks' => 'Cement bags for construction',
        ]);

        $this->line("✅ MaterialIssueItem created");
        $this->line("   material: {$material->name}");
        $this->line("   quantity: {$item->quantity}");
        $this->line("   amount: {$item->amount}");

        // Verify MaterialIssue → MaterialIssueItem relationship
        $items = $issue->items;
        if ($items->isEmpty() || !$items->contains('id', $item->id)) {
            $this->error('❌ MaterialIssue → MaterialIssueItem relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: MaterialIssue → MaterialIssueItem");

        // Verify MaterialIssueItem → Material relationship
        $itemMaterial = $item->material;
        if (!$itemMaterial || $itemMaterial->id !== $material->id) {
            $this->error('❌ MaterialIssueItem → Material relationship failed');
            return false;
        }

        $this->line("✅ Relationship verified: MaterialIssueItem → Material");

        return true;
    }

    private function displayResults()
    {
        $this->newLine();
        $this->info('📊 COMPREHENSIVE TEST RESULTS');
        $this->line(str_repeat('=', 60));

        $totalPhases = count($this->phaseResults);
        $passedPhases = 0;

        foreach ($this->phaseResults as $phaseNumber => $result) {
            $status = $result['status'];
            $name = $result['name'];
            $duration = $result['duration'] ?? 'N/A';

            if ($status === 'PASS') {
                $passedPhases++;
                $this->line("✅ Phase {$phaseNumber}: {$name} ({$duration}ms)");
            } elseif ($status === 'FAIL') {
                $this->line("❌ Phase {$phaseNumber}: {$name} ({$duration}ms)");
            } else {
                $this->line("🔥 Phase {$phaseNumber}: {$name} ({$duration}ms) - ERROR: " . ($result['error'] ?? 'Unknown'));
            }
        }

        $overallScore = $totalPhases > 0 ? round(($passedPhases / $totalPhases) * 100, 2) : 0;

        $this->newLine();
        $this->info("🎯 OVERALL SCORE: {$overallScore}% ({$passedPhases}/{$totalPhases})");

        if ($overallScore >= 90) {
            $this->info('🏆 EXCELLENT - All relationships verified!');
        } elseif ($overallScore >= 80) {
            $this->info('✅ GOOD - Core relationships working');
        } elseif ($overallScore >= 60) {
            $this->line('⚠️  FAIR - Some relationships need attention');
        } else {
            $this->error('❌ POOR - Significant relationship issues');
        }
    }

    private function calculateOverallScore(): float
    {
        $total = count($this->phaseResults);
        if ($total === 0) return 0;

        $passed = collect($this->phaseResults)->where('status', 'PASS')->count();
        return round(($passed / $total) * 100, 2);
    }

    private function cleanupTestData()
    {
        $this->newLine();
        $this->info('🧹 Cleaning up test data...');

        try {
            // Clean up in reverse order to respect foreign keys
            if (!empty($this->testData['material_issue_id'])) {
                MaterialIssueItem::where('issue_id', $this->testData['material_issue_id'])->delete();
                MaterialIssue::where('id', $this->testData['material_issue_id'])->delete();
            }

            if (!empty($this->testData['consumption_id'])) {
                DailyConsumptionMaster::where('id', $this->testData['consumption_id'])->delete();
            }

            if (!empty($this->testData['dpr_id'])) {
                DailyProgressReport::where('id', $this->testData['dpr_id'])->delete();
            }

            if (!empty($this->testData['man_power_master_id'])) {
                ManPowerDetail::where('man_power_master_id', $this->testData['man_power_master_id'])->delete();
                ManPowerMaster::where('id', $this->testData['man_power_master_id'])->delete();
            }

            if (!empty($this->testData['activity_completed_id'])) {
                ActivityCompleted::where('id', $this->testData['activity_completed_id'])->delete();
            }

            if (!empty($this->testData['activity_id'])) {
                Activity::where('id', $this->testData['activity_id'])->delete();
            }

            $this->line('✅ Cleanup completed');
        } catch (\Exception $e) {
            $this->warn('⚠️ Cleanup issues: ' . $e->getMessage());
        }
    }
}
