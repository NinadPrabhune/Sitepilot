<?php

namespace Tests\Feature;

use App\Models\DailyProgressReport;
use App\Models\Machinery;
use App\Models\Supplier;
use App\Models\DailyConsumptionMaster;
use App\Models\User;
use App\Models\Activity;
use App\Models\ActivityCompleted;
use App\Services\DprLifecycleService;
use App\Services\ReportSnapshotService;
use App\Services\MachineryFinancialFlowService;
use App\Services\MasterDataValidationService;
use App\Services\DprInputValidationService;
use App\Services\DieselManagementValidationService;
use App\Services\CostAccountingValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Live Site Simulation Test
 * 7-day real-world scenario testing with multiple roles
 */
class LiveSiteSimulationTest extends TestCase
{
    use RefreshDatabase;

    private $operator;
    private $supervisor;
    private $accounts;
    private $ownedMachinery;
    private $rentalMachinery;
    private $supplier;
    private $activities;
    private $simulationResults;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users with different roles
        $this->operator = User::factory()->create();
        $this->operator->assignRole('site engineer');

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('admin');

        $this->accounts = User::factory()->create();
        $this->accounts->assignRole('accounts');

        // Create supplier
        $this->supplier = Supplier::create([
            'name' => 'Test Rental Supplier',
            'contact_person' => 'John Doe',
            'phone' => '1234567890',
            'workspace_id' => 1,
        ]);

        // Create machinery
        $this->ownedMachinery = Machinery::create([
            'name' => 'OWN-001',
            'rate' => 1500.00,
            'minimum_billing_hours' => 0,
            'owned_by' => 'owned',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        $this->rentalMachinery = Machinery::create([
            'name' => 'RENT-001',
            'rate' => 1800.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'supplier_id' => $this->supplier->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Create activities
        $this->activities = [
            Activity::create([
                'name' => 'Excavation Work',
                'quantity' => 100,
                'unit' => 'cubic meters',
                'workspace_id' => 1,
            ]),
            Activity::create([
                'name' => 'Loading Work',
                'quantity' => 50,
                'unit' => 'tons',
                'workspace_id' => 1,
            ]),
        ];

        // Create diesel material
        DB::table('materials')->insert([
            'name' => 'diesel',
            'category' => 'fuel',
            'rate' => 85.00,
            'workspace_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->simulationResults = [
            'dpr_creations' => [],
            'dpr_edits' => [],
            'diesel_entries' => [],
            'activity_completions' => [],
            'payment_requests' => [],
            'report_snapshots' => [],
            'anomalies' => [],
            'user_behaviors' => [],
        ];
    }

    /**
     * 🔥 7-Day Live Site Simulation
     */
    public function test_7_day_live_site_simulation()
    {
        $simulationStartDate = now()->subDays(6);
        
        for ($day = 0; $day < 7; $day++) {
            $currentDate = $simulationStartDate->copy()->addDays($day);
            
            $this->simulateDay($currentDate, $day + 1);
            
            // Daily report snapshot
            $this->createDailySnapshot($currentDate);
        }
        
        // Analyze simulation results
        $this->analyzeSimulationResults();
    }

    /**
     * Simulate a single day of operations
     */
    private function simulateDay($date, $dayNumber): void
    {
        echo "\n📅 Simulating Day {$dayNumber}: {$date->toDateString()}\n";
        
        // Morning: Operator creates DPRs
        $this->simulateOperatorDprCreation($date);
        
        // Mid-day: Supervisor reviews and edits
        $this->simulateSupervisorReview($date);
        
        // Afternoon: Add diesel entries
        $this->simulateDieselEntries($date);
        
        // Evening: Activity completion
        $this->simulateActivityCompletion($date);
        
        // End of day: Accounts review
        $this->simulateAccountsReview($date);
    }

    /**
     * Operator creates DPRs (with realistic mistakes)
     */
    private function simulateOperatorDprCreation($date): void
    {
        $this->actingAs($this->operator);
        
        // Create owned machinery DPR
        try {
            $ownedDprData = [
                'date' => $date->toDateString(),
                'machinery_id' => $this->ownedMachinery->id,
                'machine_start_reading' => 100 + ($date->day * 10),
                'machine_end_reading' => 110 + ($date->day * 10),
                'machine_idle_reading' => rand(0, 2),
                'number_of_operators' => 2,
                'operator_names' => 'John Doe, Jane Smith',
                'work_details' => 'Excavation work for foundation',
            ];
            
            $validation = DprInputValidationService::validateDprInput($ownedDprData);
            
            if ($validation['valid']) {
                $dpr = DprLifecycleService::createDpr($ownedDprData, $this->operator->id);
                
                // Process financial flow
                MachineryFinancialFlowService::processDprFinancials($dpr);
                
                $this->simulationResults['dpr_creations'][] = [
                    'type' => 'owned',
                    'dpr_id' => $dpr->id,
                    'date' => $date->toDateString(),
                    'created_by' => $this->operator->id,
                    'status' => 'success',
                ];
                
                // Simulate occasional mistake (wrong reading)
                if (rand(1, 10) === 3) {
                    $this->simulateOperatorMistake($dpr, $date);
                }
            }
        } catch (\Exception $e) {
            $this->simulationResults['dpr_creations'][] = [
                'type' => 'owned',
                'date' => $date->toDateString(),
                'created_by' => $this->operator->id,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
        
        // Create rental machinery DPR (70% chance)
        if (rand(1, 10) <= 7) {
            try {
                $rentalDprData = [
                    'date' => $date->toDateString(),
                    'machinery_id' => $this->rentalMachinery->id,
                    'machine_start_reading' => 200 + ($date->day * 15),
                    'machine_end_reading' => 205 + ($date->day * 15), // Short working hours
                    'machine_idle_reading' => 0,
                    'number_of_operators' => 1,
                    'operator_names' => 'Bob Wilson',
                    'work_details' => 'Loading operations',
                ];
                
                $validation = DprInputValidationService::validateDprInput($rentalDprData);
                
                if ($validation['valid']) {
                    $dpr = DprLifecycleService::createDpr($rentalDprData, $this->operator->id);
                    MachineryFinancialFlowService::processDprFinancials($dpr);
                    
                    $this->simulationResults['dpr_creations'][] = [
                        'type' => 'rental',
                        'dpr_id' => $dpr->id,
                        'date' => $date->toDateString(),
                        'created_by' => $this->operator->id,
                        'status' => 'success',
                    ];
                }
            } catch (\Exception $e) {
                $this->simulationResults['dpr_creations'][] = [
                    'type' => 'rental',
                    'date' => $date->toDateString(),
                    'created_by' => $this->operator->id,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Simulate operator mistakes
     */
    private function simulateOperatorMistake($dpr, $date): void
    {
        $mistakeType = rand(1, 3);
        
        switch ($mistakeType) {
            case 1:
                // Wrong end reading
                $wrongData = ['machine_end_reading' => $dpr->machine_start_reading - 5];
                break;
            case 2:
                // Excessive idle hours
                $wrongData = ['machine_idle_reading' => 15];
                break;
            case 3:
                // Operator name mismatch
                $wrongData = ['operator_names' => 'John Doe']; // Only 1 name for 2 operators
                break;
        }
        
        try {
            DprLifecycleService::updateDpr($dpr, $wrongData, $this->operator->id, 'Operator mistake');
            
            $this->simulationResults['dpr_edits'][] = [
                'dpr_id' => $dpr->id,
                'date' => $date->toDateString(),
                'edited_by' => $this->operator->id,
                'reason' => 'Operator mistake',
                'status' => 'failed_validation',
            ];
        } catch (\Exception $e) {
            $this->simulationResults['dpr_edits'][] = [
                'dpr_id' => $dpr->id,
                'date' => $date->toDateString(),
                'edited_by' => $this->operator->id,
                'reason' => 'Operator mistake',
                'status' => 'validation_blocked',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Supervisor reviews and edits DPRs
     */
    private function simulateSupervisorReview($date): void
    {
        $this->actingAs($this->supervisor);
        
        // Get draft DPRs for the date
        $draftDprs = DailyProgressReport::where('date', $date->toDateString())
                                      ->where('lifecycle_state', 'draft')
                                      ->get();
        
        foreach ($draftDprs as $dpr) {
            // Supervisor verifies 80% of DPRs
            if (rand(1, 10) <= 8) {
                try {
                    // Occasionally make corrections
                    if (rand(1, 10) <= 3) {
                        $corrections = [];
                        
                        // Fix common issues
                        if ($dpr->machine_idle_reading > ($dpr->machine_end_reading - $dpr->machine_start_reading)) {
                            $corrections['machine_idle_reading'] = rand(0, 2);
                        }
                        
                        if (!empty($corrections)) {
                            DprLifecycleService::updateDpr($dpr, $corrections, $this->supervisor->id, 'Supervisor correction');
                            
                            $this->simulationResults['dpr_edits'][] = [
                                'dpr_id' => $dpr->id,
                                'date' => $date->toDateString(),
                                'edited_by' => $this->supervisor->id,
                                'reason' => 'Supervisor correction',
                                'status' => 'success',
                                'corrections' => $corrections,
                            ];
                        }
                    }
                    
                    // Verify DPR
                    DprLifecycleService::verifyDpr($dpr, $this->supervisor->id, 'Supervisor verification');
                    
                } catch (\Exception $e) {
                    $this->simulationResults['dpr_edits'][] = [
                        'dpr_id' => $dpr->id,
                        'date' => $date->toDateString(),
                        'edited_by' => $this->supervisor->id,
                        'reason' => 'Supervisor verification',
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
    }

    /**
     * Add diesel entries
     */
    private function simulateDieselEntries($date): void
    {
        $this->actingAs($this->operator);
        
        // Get DPRs for the date
        $dprs = DailyProgressReport::where('date', $date->toDateString())->get();
        
        foreach ($dprs as $dpr) {
            // 60% chance of diesel entry
            if (rand(1, 10) <= 6) {
                try {
                    $dieselData = [
                        'machinery_id' => $dpr->machinery_id,
                        'date' => $date->toDateString(),
                        'material_id' => DB::table('materials')->where('name', 'diesel')->value('id'),
                        'quantity' => rand(20, 80), // Realistic range
                        'unit' => 'liters',
                        'site_id' => 1,
                    ];
                    
                    $validation = DieselManagementValidationService::validateDieselEntry($dieselData);
                    
                    if ($validation['valid']) {
                        $diesel = DailyConsumptionMaster::create(array_merge($dieselData, [
                            'created_by' => $this->operator->id,
                            'workspace_id' => 1,
                        ]));
                        
                        $this->simulationResults['diesel_entries'][] = [
                            'diesel_id' => $diesel->id,
                            'dpr_id' => $dpr->id,
                            'date' => $date->toDateString(),
                            'quantity' => $dieselData['quantity'],
                            'status' => 'success',
                        ];
                    } else {
                        $this->simulationResults['diesel_entries'][] = [
                            'date' => $date->toDateString(),
                            'machinery_id' => $dpr->machinery_id,
                            'status' => 'validation_failed',
                            'errors' => $validation['errors'] ?? [],
                        ];
                    }
                } catch (\Exception $e) {
                    $this->simulationResults['diesel_entries'][] = [
                        'date' => $date->toDateString(),
                        'machinery_id' => $dpr->machinery_id,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
    }

    /**
     * Activity completion
     */
    private function simulateActivityCompletion($date): void
    {
        $this->actingAs($this->supervisor);
        
        foreach ($this->activities as $activity) {
            // 50% chance of activity completion
            if (rand(1, 10) <= 5) {
                try {
                    $completionQuantity = rand(5, 25); // Partial completion
                    
                    $completion = ActivityCompleted::create([
                        'activity_id' => $activity->id,
                        'date' => $date->toDateString(),
                        'quantity' => $completionQuantity,
                        'created_by' => $this->supervisor->id,
                        'workspace_id' => 1,
                    ]);
                    
                    $this->simulationResults['activity_completions'][] = [
                        'completion_id' => $completion->id,
                        'activity_id' => $activity->id,
                        'date' => $date->toDateString(),
                        'quantity' => $completionQuantity,
                        'status' => 'success',
                    ];
                } catch (\Exception $e) {
                    $this->simulationResults['activity_completions'][] = [
                        'activity_id' => $activity->id,
                        'date' => $date->toDateString(),
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
    }

    /**
     * Accounts review
     */
    private function simulateAccountsReview($date): void
    {
        $this->actingAs($this->accounts);
        
        // Get verified DPRs
        $verifiedDprs = DailyProgressReport::where('date', $date->toDateString())
                                          ->where('lifecycle_state', 'verified')
                                          ->get();
        
        foreach ($verifiedDprs as $dpr) {
            // Lock 70% of verified DPRs
            if (rand(1, 10) <= 7) {
                try {
                    DprLifecycleService::lockDpr($dpr, $this->accounts->id, 'Accounts lock for payment');
                    
                    // For rental machinery, simulate payment request
                    if ($dpr->machinery->owned_by === 'rental') {
                        // This would integrate with payment request system
                        $this->simulationResults['payment_requests'][] = [
                            'dpr_id' => $dpr->id,
                            'date' => $date->toDateString(),
                            'machinery_type' => 'rental',
                            'amount' => $dpr->calculated_amount,
                            'status' => 'created',
                        ];
                    }
                } catch (\Exception $e) {
                    $this->simulationResults['payment_requests'][] = [
                        'dpr_id' => $dpr->id,
                        'date' => $date->toDateString(),
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
    }

    /**
     * Create daily report snapshot
     */
    private function createDailySnapshot($date): void
    {
        try {
            $snapshot = ReportSnapshotService::createMachineryCostSnapshot($date->toDateString(), $this->accounts->id);
            
            $this->simulationResults['report_snapshots'][] = [
                'snapshot_id' => $snapshot->id,
                'date' => $date->toDateString(),
                'total_amount' => $snapshot->total_amount,
                'status' => 'success',
            ];
        } catch (\Exception $e) {
            $this->simulationResults['report_snapshots'][] = [
                'date' => $date->toDateString(),
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze simulation results
     */
    private function analyzeSimulationResults(): void
    {
        echo "\n📊 SIMULATION RESULTS ANALYSIS\n";
        
        // DPR creation analysis
        $totalDprCreations = count($this->simulationResults['dpr_creations']);
        $successfulDprCreations = count(array_filter($this->simulationResults['dpr_creations'], fn($r) => $r['status'] === 'success'));
        
        echo "📈 DPR Creations: {$successfulDprCreations}/{$totalDprCreations} successful\n";
        
        // DPR edit analysis
        $totalDprEdits = count($this->simulationResults['dpr_edits']);
        $validationBlockedEdits = count(array_filter($this->simulationResults['dpr_edits'], fn($e) => $e['status'] === 'validation_blocked'));
        
        echo "✏️ DPR Edits: {$totalDprEdits} total, {$validationBlockedEdits} blocked by validation\n";
        
        // Diesel entry analysis
        $totalDieselEntries = count($this->simulationResults['diesel_entries']);
        $successfulDieselEntries = count(array_filter($this->simulationResults['diesel_entries'], fn($e) => $e['status'] === 'success'));
        
        echo "⛽ Diesel Entries: {$successfulDieselEntries}/{$totalDieselEntries} successful\n";
        
        // Activity completion analysis
        $totalActivityCompletions = count($this->simulationResults['activity_completions']);
        $successfulActivityCompletions = count(array_filter($this->simulationResults['activity_completions'], fn($c) => $c['status'] === 'success'));
        
        echo "📋 Activity Completions: {$successfulActivityCompletions}/{$totalActivityCompletions} successful\n";
        
        // Payment request analysis
        $totalPaymentRequests = count($this->simulationResults['payment_requests']);
        $successfulPaymentRequests = count(array_filter($this->simulationResults['payment_requests'], fn($p) => $p['status'] === 'created'));
        
        echo "💰 Payment Requests: {$successfulPaymentRequests}/{$totalPaymentRequests} created\n";
        
        // Report snapshot analysis
        $totalSnapshots = count($this->simulationResults['report_snapshots']);
        $successfulSnapshots = count(array_filter($this->simulationResults['report_snapshots'], fn($s) => $s['status'] === 'success'));
        
        echo "📸 Report Snapshots: {$successfulSnapshots}/{$totalSnapshots} successful\n";
        
        // Behavioral analysis
        $this->analyzeBehavioralPatterns();
        
        // Financial integrity check
        $this->checkFinancialIntegrity();
        
        // Report drift analysis
        $this->analyzeReportDrift();
    }

    /**
     * Analyze behavioral patterns
     */
    private function analyzeBehavioralPatterns(): void
    {
        echo "\n🧠 BEHAVIORAL ANALYSIS\n";
        
        // Get behavioral statistics
        $stats = DprLifecycleService::getBehavioralStats();
        
        echo "📊 DPR Edit Frequency: {$stats['edit_frequency']['average_edits']} avg, {$stats['edit_frequency']['max_edits']} max\n";
        echo "🏥 Behavioral Health: {$stats['behavioral_health']['grade']} ({$stats['behavioral_health']['score']}%)\n";
        
        if (!empty($stats['behavioral_health']['issues'])) {
            echo "⚠️ Health Issues:\n";
            foreach ($stats['behavioral_health']['issues'] as $issue) {
                echo "   - {$issue}\n";
            }
        }
        
        // Check anomalies
        $totalAnomalies = array_sum(array_map(fn($group) => array_sum(array_column($group, 'count')), $stats['anomaly_stats']));
        
        if ($totalAnomalies > 0) {
            echo "🚨 Anomalies Detected: {$totalAnomalies}\n";
        } else {
            echo "✅ No anomalies detected\n";
        }
    }

    /**
     * Check financial integrity
     */
    private function checkFinancialIntegrity(): void
    {
        echo "\n💰 FINANCIAL INTEGRITY CHECK\n";
        
        $validation = CostAccountingValidationService::validateCostPayableSeparation();
        
        if ($validation['valid']) {
            echo "✅ Cost/Payable separation: Valid\n";
        } else {
            echo "❌ Cost/Payable separation: Issues detected\n";
            foreach ($validation['issues'] as $issue) {
                echo "   - {$issue['message']}\n";
            }
        }
        
        echo "💵 Summary:\n";
        echo "   Internal Cost: ₹{$validation['summary']['internal_cost_total']}\n";
        echo "   Expense Cost: ₹{$validation['summary']['expense_total']}\n";
        echo "   Payable Cost: ₹{$validation['summary']['payable_total']}\n";
        echo "   Total Project Cost: ₹{$validation['summary']['total_project_cost']}\n";
    }

    /**
     * Analyze report drift
     */
    private function analyzeReportDrift(): void
    {
        echo "\n📈 REPORT DRIFT ANALYSIS\n";
        
        // Check drift for a recent date
        $recentDate = now()->subDays(2)->toDateString();
        $comparison = ReportSnapshotService::getSnapshotComparison('machinery_cost', 'daily_all_' . $recentDate, $recentDate);
        
        if ($comparison['exists'] && $comparison['comparison_available']) {
            $drift = $comparison['drift'];
            
            echo "📊 Drift Analysis for {$recentDate}:\n";
            echo "   Amount Drift: ₹{$drift['total_amount_drift']} (" . round($drift['percentage_drift'], 2) . "%)\n";
            
            if (!empty($drift['significant_changes'])) {
                echo "⚠️ Significant Changes:\n";
                foreach ($drift['significant_changes'] as $change) {
                    echo "   - {$change['description']} ({$change['severity']})\n";
                }
            } else {
                echo "✅ No significant changes detected\n";
            }
        } else {
            echo "ℹ️ No snapshot available for drift analysis\n";
        }
    }

    /**
     * Test system resilience under stress
     */
    public function test_system_resilience_under_stress(): void
    {
        echo "\n🔥 STRESS TESTING\n";
        
        // Simulate concurrent DPR creation
        $this->simulateConcurrentOperations();
        
        // Simulate rapid edits
        $this->simulateRapidEdits();
        
        // Simulate boundary conditions
        $this->simulateBoundaryConditions();
    }

    /**
     * Simulate concurrent operations
     */
    private function simulateConcurrentOperations(): void
    {
        echo "⚡ Concurrent Operations Test\n";
        
        $date = now()->toDateString();
        $machineryId = $this->ownedMachinery->id;
        
        // Try to create multiple DPRs for same machinery/date
        $concurrentAttempts = 5;
        $successfulCreations = 0;
        
        for ($i = 0; $i < $concurrentAttempts; $i++) {
            try {
                $dprData = [
                    'date' => $date,
                    'machinery_id' => $machineryId,
                    'machine_start_reading' => 100 + $i,
                    'machine_end_reading' => 110 + $i,
                    'number_of_operators' => 1,
                    'operator_names' => 'Test Operator',
                ];
                
                $validation = DprInputValidationService::validateDprInput($dprData);
                
                if ($validation['valid']) {
                    $dpr = DprLifecycleService::createDpr($dprData, $this->operator->id);
                    $successfulCreations++;
                }
            } catch (\Exception $e) {
                // Expected to fail after first creation
            }
        }
        
        echo "   Concurrent DPR attempts: {$concurrentAttempts}, Successful: {$successfulCreations}\n";
        $this->assertEquals(1, $successfulCreations, 'Only one DPR should be created for same machinery/date');
    }

    /**
     * Simulate rapid edits
     */
    private function simulateRapidEdits(): void
    {
        echo "🔄 Rapid Edits Test\n";
        
        // Create a DPR
        $dpr = DprLifecycleService::createDpr([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'number_of_operators' => 1,
            'operator_names' => 'Test Operator',
        ], $this->operator->id);
        
        // Make rapid edits
        $editCount = 0;
        $anomalyDetected = false;
        
        for ($i = 0; $i < 8; $i++) { // More than the threshold for anomaly detection
            try {
                DprLifecycleService::updateDpr($dpr, [
                    'machine_idle_reading' => $i,
                ], $this->operator->id, "Rapid edit {$i}");
                
                $editCount++;
            } catch (\Exception $e) {
                break;
            }
        }
        
        // Check if anomaly was detected
        $anomalies = DB::table('dpr_anomalies')
                      ->where('dpr_id', $dpr->id)
                      ->where('anomaly_type', 'excessive_edits')
                      ->count();
        
        if ($anomalies > 0) {
            $anomalyDetected = true;
        }
        
        echo "   Rapid edits made: {$editCount}, Anomaly detected: " . ($anomalyDetected ? 'Yes' : 'No') . "\n";
        $this->assertTrue($anomalyDetected, 'Anomaly should be detected for excessive edits');
    }

    /**
     * Simulate boundary conditions
     */
    private function simulateBoundaryConditions(): void
    {
        echo "🎯 Boundary Conditions Test\n";
        
        // Test extreme values
        $extremeTests = [
            'negative_readings' => [
                'data' => [
                    'date' => now()->toDateString(),
                    'machinery_id' => $this->ownedMachinery->id,
                    'machine_start_reading' => -100,
                    'machine_end_reading' => 100,
                ],
                'should_fail' => true,
            ],
            'excessive_idle' => [
                'data' => [
                    'date' => now()->toDateString(),
                    'machinery_id' => $this->ownedMachinery->id,
                    'machine_start_reading' => 100,
                    'machine_end_reading' => 105,
                    'machine_idle_reading' => 20, // More than working hours
                ],
                'should_fail' => true,
            ],
            'operator_mismatch' => [
                'data' => [
                    'date' => now()->addDay()->toDateString(),
                    'machinery_id' => $this->ownedMachinery->id,
                    'machine_start_reading' => 100,
                    'machine_end_reading' => 110,
                    'number_of_operators' => 3,
                    'operator_names' => 'John Doe, Jane Smith', // Only 2 names
                ],
                'should_fail' => true,
            ],
        ];
        
        foreach ($extremeTests as $testName => $test) {
            try {
                $validation = DprInputValidationService::validateDprInput($test['data']);
                
                if ($test['should_fail']) {
                    $this->assertFalse($validation['valid'], "{$testName} should fail validation");
                    echo "   {$testName}: ❌ Correctly rejected\n";
                } else {
                    $this->assertTrue($validation['valid'], "{$testName} should pass validation");
                    echo "   {$testName}: ✅ Correctly accepted\n";
                }
            } catch (\Exception $e) {
                if ($test['should_fail']) {
                    echo "   {$testName}: ❌ Correctly rejected (exception)\n";
                } else {
                    echo "   {$testName}: ⚠️ Unexpectedly failed\n";
                }
            }
        }
    }
}
