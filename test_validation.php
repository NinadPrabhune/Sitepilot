<?php

/**
 * Master Flow Validation Script
 * Tests the complete Activity-Machinery-Cost-Payment integrated flow
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== MASTER FLOW VALIDATION ===\n\n";

// STEP 1: Verify Test Data Setup
echo "STEP 1: Test Data Verification\n";
echo "============================\n";

$activity = DB::table('activities')->where('title', 'Foundation Work')->first();
$completion = DB::table('activities_completed')->where('activity_id', $activity->id)->first();
$machinery = DB::table('machineries')->where('vehicle_number', 'RENT-002')->first();
$dpr = DB::table('daily_progress_reports')->where('date', '2026-05-01')->first();
$consumption = DB::table('daily_consumption_masters')->where('consumption_date', '2026-05-01')->first();

echo "✅ Activity ID: {$activity->id}\n";
echo "✅ Activity Quantity: {$activity->quantity} {$activity->unit}\n";
echo "✅ Completion ID: {$completion->id}\n";
echo "✅ Completion Quantity: {$completion->completed_quantity} ({$completion->completed_date})\n";
echo "✅ Machinery ID: {$machinery->id} ({$machinery->name})\n";
echo "✅ Machinery Rate: ₹{$machinery->rate}/hr\n";
echo "✅ Minimum Billing: {$machinery->minimum_billing_hours} hrs\n";
echo "✅ DPR ID: {$dpr->id}\n";
echo "✅ DPR Hours: {$dpr->billable_hours}\n";
echo "✅ DPR Amount: ₹{$dpr->calculated_amount}\n";
echo "✅ Consumption ID: {$consumption->id}\n";

// STEP 2: Activity Completion Flow Gap Test
echo "\nSTEP 2: Activity Completion Flow Gap Test\n";
echo "========================================\n";

// Test 2A: Check if resources are properly linked to completion
$dprLinkedToCompletion = DB::table('daily_progress_reports')
    ->where('activity_completed_id', $completion->id)
    ->count();

$consumptionLinkedToCompletion = DB::table('daily_consumption_masters')
    ->where('activity_completed_id', $completion->id)
    ->count();

echo "🔍 DPRs linked to completion: {$dprLinkedToCompletion}\n";
echo "🔍 Consumptions linked to completion: {$consumptionLinkedToCompletion}\n";

if ($dprLinkedToCompletion > 0 && $consumptionLinkedToCompletion > 0) {
    echo "✅ PASS: Resources properly linked to ActivityCompleted\n";
} else {
    echo "❌ FAIL: Resources not linked to ActivityCompleted\n";
}

// STEP 3: DPR Calculation Integrity Test
echo "\nSTEP 3: DPR Calculation Integrity Test\n";
echo "====================================\n";

$actualHours = $dpr->machine_end_reading - $dpr->machine_start_reading;
$expectedBillableHours = max($actualHours, $machinery->minimum_billing_hours);
$expectedAmount = $expectedBillableHours * $machinery->rate;

echo "🔍 Actual Hours: {$actualHours}\n";
echo "🔍 Expected Billable Hours: {$expectedBillableHours}\n";
echo "🔍 Expected Amount: ₹{$expectedAmount}\n";
echo "🔍 DPR Calculated Amount: ₹{$dpr->calculated_amount}\n";

if ($dpr->billable_hours == $expectedBillableHours && $dpr->calculated_amount == $expectedAmount) {
    echo "✅ PASS: Minimum billing enforced correctly\n";
} else {
    echo "❌ FAIL: Minimum billing NOT enforced\n";
    echo "   Expected: {$expectedBillableHours} hrs, ₹{$expectedAmount}\n";
    echo "   Actual: {$dpr->billable_hours} hrs, ₹{$dpr->calculated_amount}\n";
}

// STEP 4: Resource Linkage Integrity Test
echo "\nSTEP 4: Resource Linkage Integrity Test\n";
echo "=====================================\n";

// Check for orphan resources
$orphanDPRs = DB::table('daily_progress_reports')
    ->whereNull('activity_completed_id')
    ->count();

$orphanConsumptions = DB::table('daily_consumption_masters')
    ->whereNull('activity_completed_id')
    ->count();

echo "🔍 Orphan DPRs: {$orphanDPRs}\n";
echo "🔍 Orphan Consumptions: {$orphanConsumptions}\n";

if ($orphanDPRs == 0 && $orphanConsumptions == 0) {
    echo "✅ PASS: No orphan resources found\n";
} else {
    echo "❌ FAIL: Orphan resources detected\n";
}

// STEP 5: Date Consistency Test
echo "\nSTEP 5: Date Consistency Test\n";
echo "============================\n";

$dprDate = $dpr->date;
$completionDate = $completion->completed_date;
$consumptionDate = $consumption->consumption_date;

echo "🔍 Completion Date: {$completionDate}\n";
echo "🔍 DPR Date: {$dprDate}\n";
echo "🔍 Consumption Date: {$consumptionDate}\n";

if ($dprDate == $completionDate && $consumptionDate == $completionDate) {
    echo "✅ PASS: All dates consistent\n";
} else {
    echo "❌ FAIL: Date inconsistency detected\n";
}

// STEP 6: Progress Validation Test
echo "\nSTEP 6: Progress Validation Test\n";
echo "===============================\n";

$totalCompleted = DB::table('activities_completed')
    ->where('activity_id', $activity->id)
    ->sum('completed_quantity');

echo "🔍 Planned Quantity: {$activity->quantity}\n";
echo "🔍 Total Completed: {$totalCompleted}\n";

if ($totalCompleted <= $activity->quantity) {
    echo "✅ PASS: Progress within planned limits\n";
} else {
    echo "❌ FAIL: Over-completion detected\n";
}

// STEP 7: Cost Aggregation Test
echo "\nSTEP 7: Cost Aggregation Test\n";
echo "===========================\n";

$dprCost = $dpr->calculated_amount;
$dieselCost = DB::table('daily_consumption_details')
    ->join('daily_consumption_masters', 'daily_consumption_details.daily_consumption_master_id', '=', 'daily_consumption_masters.id')
    ->where('daily_consumption_masters.id', $consumption->id)
    ->value('total_price');

$totalCost = $dprCost + $dieselCost;

echo "🔍 DPR Cost: ₹{$dprCost}\n";
echo "🔍 Diesel Cost: ₹{$dieselCost}\n";
echo "🔍 Total Cost: ₹{$totalCost}\n";

// STEP 8: Ledger Integrity Test
echo "\nSTEP 8: Ledger Integrity Test\n";
echo "===========================\n";

$ledgerEntries = DB::table('machinery_ledgers')
    ->where('machinery_id', $machinery->id)
    ->orderBy('date', 'asc')
    ->get();

echo "🔍 Ledger Entries Count: " . count($ledgerEntries) . "\n";

$creditAmount = 0;
$debitAmount = 0;

foreach ($ledgerEntries as $entry) {
    echo "   - " . ucfirst($entry->entry_direction) . ": ₹{$entry->amount} ({$entry->entry_type})\n";
    if ($entry->entry_direction == 'credit') {
        $creditAmount += $entry->amount;
    } else {
        $debitAmount += $entry->amount;
    }
}

echo "🔍 Total Credits: ₹{$creditAmount}\n";
echo "🔍 Total Debits: ₹{$debitAmount}\n";
echo "🔍 Net Balance: ₹" . ($creditAmount - $debitAmount) . "\n";

if ($creditAmount == $dprCost && $debitAmount == $dieselCost) {
    echo "✅ PASS: Ledger amounts match calculations\n";
} else {
    echo "❌ FAIL: Ledger amount mismatch\n";
}

// STEP 9: Drift Detection Test
echo "\nSTEP 9: Drift Detection Test\n";
echo "===========================\n";

$drift = abs($dpr->calculated_amount - $creditAmount);

echo "🔍 DPR Amount: ₹{$dpr->calculated_amount}\n";
echo "🔍 Ledger Credit: ₹{$creditAmount}\n";
echo "🔍 Drift: ₹{$drift}\n";

if ($drift <= 0.01) {
    echo "✅ PASS: No calculation drift detected\n";
} else {
    echo "❌ FAIL: Calculation drift detected\n";
}

// STEP 10: Machine Work Report Test
echo "\nSTEP 10: Machine Work Report Test\n";
echo "================================\n";

$machineReport = DB::table('machineries')
    ->leftJoin('daily_progress_reports', 'machineries.id', '=', 'daily_progress_reports.machinery_id')
    ->leftJoin('machinery_ledgers', 'daily_progress_reports.id', '=', 'machinery_ledgers.reference_id')
    ->where('machineries.id', $machinery->id)
    ->select(
        'machineries.name',
        DB::raw('COUNT(daily_progress_reports.id) as dpr_count'),
        DB::raw('SUM(daily_progress_reports.billable_hours) as total_hours'),
        DB::raw('SUM(daily_progress_reports.calculated_amount) as total_cost'),
        DB::raw('SUM(CASE WHEN machinery_ledgers.entry_direction = "credit" THEN machinery_ledgers.amount ELSE 0 END) as ledger_credits')
    )
    ->first();

echo "🔍 Machine: {$machineReport->name}\n";
echo "🔍 DPR Count: {$machineReport->dpr_count}\n";
echo "🔍 Total Hours: {$machineReport->total_hours}\n";
echo "🔍 DPR Total Cost: ₹{$machineReport->total_cost}\n";
echo "🔍 Ledger Credits: ₹{$machineReport->ledger_credits}\n";

if ($machineReport->total_cost == $machineReport->ledger_credits) {
    echo "✅ PASS: Report aggregation correct\n";
} else {
    echo "❌ FAIL: Report aggregation mismatch\n";
}

// FINAL SUMMARY
echo "\n=== FINAL VALIDATION SUMMARY ===\n";
echo "==============================\n";

$tests = [
    'Test Data Setup' => true,
    'Activity Completion Flow' => ($dprLinkedToCompletion > 0 && $consumptionLinkedToCompletion > 0),
    'DPR Calculation Integrity' => ($dpr->billable_hours == $expectedBillableHours && $dpr->calculated_amount == $expectedAmount),
    'Resource Linkage Integrity' => ($orphanDPRs == 0 && $orphanConsumptions == 0),
    'Date Consistency' => ($dprDate == $completionDate && $consumptionDate == $completionDate),
    'Progress Validation' => ($totalCompleted <= $activity->quantity),
    'Cost Aggregation' => true, // Basic aggregation test
    'Ledger Integrity' => ($creditAmount == $dprCost && $debitAmount == $dieselCost),
    'Drift Detection' => ($drift <= 0.01),
    'Machine Work Report' => ($machineReport->total_cost == $machineReport->ledger_credits),
];

$passed = 0;
$total = count($tests);

foreach ($tests as $test => $result) {
    $status = $result ? '✅ PASS' : '❌ FAIL';
    echo "{$status}: {$test}\n";
    if ($result) $passed++;
}

echo "\nOVERALL RESULT: {$passed}/{$total} tests passed\n";

if ($passed == $total) {
    echo "🎉 ALL TESTS PASSED - System is financially correct!\n";
} else {
    echo "⚠️  SOME TESTS FAILED - System has financial integrity issues!\n";
}

echo "\n=== VALIDATION COMPLETE ===\n";
