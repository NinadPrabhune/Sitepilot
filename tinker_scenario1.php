<?php
/**
 * Scenario 1 - Complete Test Script for Laravel Tinker
 * Run: php artisan tinker --execute="include 'tinker_scenario1.php'"
 */

use App\Domain\Machinery\Models\MachineryLedger;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryPaymentPeriod;
use App\Domain\Machinery\Services\MachineryPaymentRequestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ============================================
// PHASE 1: MICRO-CHECKS
// ============================================
echo "\n========================================\n";
echo "PHASE 1: MICRO-CHECKS\n";
echo "========================================\n\n";

$micro1 = MachineryLedger::where('machinery_id', 1)
    ->whereBetween('date', ['2026-01-01', '2026-01-31'])
    ->count();
echo "1. Hidden Data Pollution: $micro1 (expected: 0) " . ($micro1 === 0 ? "✅ PASS" : "❌ FAIL") . "\n";

$micro2 = MachineryLedger::where('machinery_id', 1)
    ->where('is_reversal', true)
    ->whereBetween('date', ['2026-01-01', '2026-01-31'])
    ->count();
echo "2. Reversal Leakage: $micro2 (expected: 0) " . ($micro2 === 0 ? "✅ PASS" : "❌ FAIL") . "\n";

$micro3 = DB::table('machinery_ledger')
    ->where('machinery_id', 1)
    ->whereNull('deleted_at')
    ->select('date', 'amount', 'entry_direction')
    ->groupBy('date', 'amount', 'entry_direction')
    ->havingRaw('COUNT(*) > 1')
    ->count();
echo "3. Duplicate Detection: $micro3 rows (expected: 0) " . ($micro3 === 0 ? "✅ PASS" : "❌ FAIL") . "\n";

// Clean previous scenario data first (use soft delete for immutability)
echo "Cleaning previous scenario data (soft delete)...\n";
MachineryLedger::where('machinery_id', 1)->whereBetween('date', ['2026-01-01', '2026-01-31'])->delete();

$micro4 = MachineryLedger::where('machinery_id', 1)
    ->whereBetween('date', ['2026-01-01', '2026-01-31'])
    ->where('workspace_id', '!=', 1)
    ->count();
echo "4. Workspace Contamination: $micro4 (expected: 0) " . ($micro4 === 0 ? "✅ PASS" : "❌ FAIL") . "\n";

$micro5 = DB::select("SELECT id, date FROM machinery_ledger WHERE DATE(date) != date");
echo "5. Timezone Drift: " . count($micro5) . " rows (expected: 0) " . (count($micro5) === 0 ? "✅ PASS" : "❌ FAIL") . "\n";

// STOP if any micro-check fails
if ($micro1 > 0 || $micro2 > 0 || $micro3 > 0 || $micro4 > 0 || count($micro5) > 0) {
    echo "\n❌ MICRO-CHECKS FAILED - STOPPING EXECUTION\n";
    exit;
}

echo "\n✅ ALL MICRO-CHECKS PASSED - PROCEEDING\n";

// ============================================
// PHASE 2: BASELINE CAPTURE
// ============================================
echo "\n========================================\n";
echo "PHASE 2: BASELINE CAPTURE\n";
echo "========================================\n\n";

$baselinePR = MachineryPaymentRequest::count();
$baselinePP = MachineryPaymentPeriod::count();
$baselineLinked = MachineryLedger::whereNotNull('payment_request_id')->count();

echo "Baseline Counts:\n";
echo "- Payment Requests: $baselinePR\n";
echo "- Payment Periods: $baselinePP\n";
echo "- Linked Ledger: $baselineLinked\n";

// ============================================
// STEP 2: INSERT LEDGER ENTRIES
// ============================================
echo "\n========================================\n";
echo "STEP 2: INSERT 5 LEDGER ENTRIES\n";
echo "========================================\n\n";

$entries = [
    ['machinery_id' => 1, 'workspace_id' => 1, 'entry_direction' => 'credit', 'entry_type' => 'reading', 'amount' => 2000.00, 'date' => '2026-01-05', 'description' => 'Site reading - Week 1', 'running_balance' => 2000.00, 'is_reversal' => false],
    ['machinery_id' => 1, 'workspace_id' => 1, 'entry_direction' => 'credit', 'entry_type' => 'reading', 'amount' => 2500.00, 'date' => '2026-01-12', 'description' => 'Site reading - Week 2', 'running_balance' => 4500.00, 'is_reversal' => false],
    ['machinery_id' => 1, 'workspace_id' => 1, 'entry_direction' => 'debit', 'entry_type' => 'diesel', 'amount' => 800.00, 'date' => '2026-01-15', 'description' => 'Diesel advance', 'running_balance' => 3700.00, 'is_reversal' => false],
    ['machinery_id' => 1, 'workspace_id' => 1, 'entry_direction' => 'credit', 'entry_type' => 'reading', 'amount' => 2500.00, 'date' => '2026-01-19', 'description' => 'Site reading - Week 3', 'running_balance' => 6200.00, 'is_reversal' => false],
    ['machinery_id' => 1, 'workspace_id' => 1, 'entry_direction' => 'debit', 'entry_type' => 'maintenance', 'amount' => 700.00, 'date' => '2026-01-25', 'description' => 'Maintenance deduction', 'running_balance' => 5500.00, 'is_reversal' => false],
];

foreach ($entries as $entry) {
    MachineryLedger::create($entry);
}

echo "✅ Inserted 5 ledger entries\n";

// ============================================
// GROUND TRUTH CAPTURE
// ============================================
echo "\n========================================\n";
echo "GROUND TRUTH DATASET (SAVE THIS)\n";
echo "========================================\n\n";

$groundTruth = MachineryLedger::where('machinery_id', 1)
    ->whereBetween('date', ['2026-01-01', '2026-01-31'])
    ->orderBy('date')
    ->orderBy('id')
    ->get(['id', 'date', 'amount', 'entry_direction', 'entry_type']);

foreach ($groundTruth as $row) {
    echo "ID: {$row->id} | Date: {$row->date} | Amount: {$row->amount} | Direction: {$row->entry_direction} | Type: {$row->entry_type}\n";
}

$groundTruthIds = $groundTruth->pluck('id')->toArray();
echo "\nGround Truth Entry IDs (ORDERED): [" . implode(', ', $groundTruthIds) . "]\n";

// ============================================
// MANUAL CALCULATION VERIFICATION
// ============================================
echo "\n========================================\n";
echo "MANUAL CALCULATION VERIFICATION\n";
echo "========================================\n\n";

$manualCalc = DB::select("SELECT 
    SUM(CASE WHEN entry_direction = 'credit' THEN amount ELSE 0 END) as credits,
    SUM(CASE WHEN entry_direction = 'debit' THEN amount ELSE 0 END) as debits,
    SUM(CASE WHEN entry_direction = 'credit' THEN amount ELSE -amount END) as net_payable
FROM machinery_ledger
WHERE machinery_id = 1
AND date BETWEEN '2026-01-01' AND '2026-01-31'");

echo "Manual SQL Calculation:\n";
echo "- Credits: {$manualCalc[0]->credits}\n";
echo "- Debits: {$manualCalc[0]->debits}\n";
echo "- Net Payable: {$manualCalc[0]->net_payable}\n";

// ============================================
// PHASE 3: CREATE PAYMENT REQUEST (Via Service)
// ============================================
echo "\n========================================\n";
echo "PHASE 3: CREATE PAYMENT REQUEST\n";
echo "========================================\n\n";

$service = app(MachineryPaymentRequestService::class);

try {
    $request = $service->createFromLedger(
        machineryId: 1,
        supplierId: 1,
        periodStart: '2026-01-01',
        periodEnd: '2026-01-31',
        requestedByUserId: 1,
        idempotencyKey: 'phaseA-s1'
    );
    
    echo "✅ Payment Request Created:\n";
    echo "- ID: {$request->id}\n";
    echo "- Status: {$request->status}\n";
    echo "- Credits: {$request->credits}\n";
    echo "- Debits: {$request->debits}\n";
    echo "- Net Payable: {$request->net_payable}\n";
    echo "- Entries Hash: " . ($request->audit_snapshot['entries_hash'] ?? 'NULL') . "\n";
    echo "- Entry Count: " . ($request->audit_snapshot['entry_count'] ?? 'N/A') . "\n";
    echo "- Ledger Entry IDs: [" . implode(', ', $request->audit_snapshot['ledger_entry_ids'] ?? []) . "]\n";
    
    $paymentRequestId = $request->id;
    $originalHash = $request->audit_snapshot['entries_hash'] ?? null;
    
} catch (Exception $e) {
    echo "❌ FAILED TO CREATE PAYMENT REQUEST: " . $e->getMessage() . "\n";
    exit;
}

// ============================================
// EARLY STATE CHECK
// ============================================
echo "\n========================================\n";
echo "EARLY STATE CHECK (Zero Side-Effect)\n";
echo "========================================\n\n";

$earlyLinked = MachineryLedger::whereNotNull('payment_request_id')->count();
$earlyPeriods = MachineryPaymentPeriod::count();

echo "Linked Ledger Entries: $earlyLinked (expected: 0) " . ($earlyLinked === 0 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Payment Periods Created: $earlyPeriods (expected: $baselinePP) " . ($earlyPeriods === $baselinePP ? "✅ PASS" : "❌ FAIL") . "\n";

// ============================================
// PHASE 4: DEBUG ENDPOINT (Simulated)
// ============================================
echo "\n========================================\n";
echo "PHASE 4: DEBUG ENDPOINT (3 Calls)\n";
echo "========================================\n\n";

// Simulate debug endpoint logic
$debugResults = [];
for ($i = 1; $i <= 3; $i++) {
    $ledgerEntries = MachineryLedger::whereIn('id', $groundTruthIds)
        ->where('is_reversal', false)
        ->orderBy('date')
        ->orderBy('id')
        ->get();
    
    $currentCredits = $ledgerEntries->where('entry_direction', 'credit')->sum('amount');
    $currentDebits = $ledgerEntries->where('entry_direction', 'debit')->sum('amount');
    $currentNetPayable = $currentCredits - $currentDebits;
    
    $sortedEntries = $ledgerEntries->sortBy(['date', 'id']);
    $currentHash = hash('sha256', json_encode($sortedEntries->map(fn($e) => [
        'id' => $e->id,
        'date' => $e->date,
        'amount' => $e->amount,
        'entry_direction' => $e->entry_direction,
        'entry_type' => $e->entry_type,
    ])->toArray()));
    
    $debugResults[] = $currentHash;
    
    echo "Call $i - Hash: $currentHash\n";
    echo "  Credits: $currentCredits, Debits: $currentDebits, Net: $currentNetPayable\n";
    
    if ($i > 1) {
        $match = $currentHash === $debugResults[0] ? "✅ MATCH" : "❌ MISMATCH";
        echo "  vs Call 1: $match\n";
    }
    echo "\n";
    
    if ($i === 1) {
        // Verify entry IDs match ground truth
        $debugIds = $ledgerEntries->pluck('id')->toArray();
        $idsMatch = $debugIds === $groundTruthIds ? "✅ MATCH" : "❌ MISMATCH";
        echo "Entry IDs vs Ground Truth: $idsMatch\n";
        echo "  Debug IDs: [" . implode(', ', $debugIds) . "]\n";
        echo "  Ground Truth: [" . implode(', ', $groundTruthIds) . "]\n\n";
    }
}

$allHashesMatch = (count(array_unique($debugResults)) === 1);
echo "Hash Stability (all 3 calls identical): " . ($allHashesMatch ? "✅ PASS" : "❌ FAIL") . "\n";

// ============================================
// PHASE 5: RECALCULATE ENDPOINT (Simulated)
// ============================================
echo "\n========================================\n";
echo "PHASE 5: RECALCULATE ENDPOINT\n";
echo "========================================\n\n";

$request = MachineryPaymentRequest::find($paymentRequestId);

$recalcLedger = MachineryLedger::whereIn('id', $request->audit_snapshot['ledger_entry_ids'] ?? [])
    ->where('is_reversal', false)
    ->orderBy('date')
    ->orderBy('id')
    ->get();

$currentCredits = $recalcLedger->where('entry_direction', 'credit')->sum('amount');
$currentDebits = $recalcLedger->where('entry_direction', 'debit')->sum('amount');
$currentNetPayable = $currentCredits - $currentDebits;

$original = [
    'credits' => $request->credits,
    'debits' => $request->debits,
    'net_payable' => $request->net_payable,
];

$current = [
    'credits' => $currentCredits,
    'debits' => $currentDebits,
    'net_payable' => $currentNetPayable,
];

$diff = [
    'credits' => $currentCredits - $request->credits,
    'debits' => $currentDebits - $request->debits,
    'net_payable' => $currentNetPayable - $request->net_payable,
];

$hasMismatch = abs($diff['net_payable']) > 0.01;
$canApprove = !$hasMismatch;

echo "Original:\n";
echo "  Credits: {$original['credits']}, Debits: {$original['debits']}, Net: {$original['net_payable']}\n";
echo "Current:\n";
echo "  Credits: {$current['credits']}, Debits: {$current['debits']}, Net: {$current['net_payable']}\n";
echo "Diff:\n";
echo "  Credits: {$diff['credits']}, Debits: {$diff['debits']}, Net: {$diff['net_payable']}\n";
echo "Has Mismatch: " . ($hasMismatch ? "true" : "false") . " (expected: false) " . (!$hasMismatch ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Can Approve: " . ($canApprove ? "true" : "false") . " (expected: true) " . ($canApprove ? "✅ PASS" : "❌ FAIL") . "\n";

// ============================================
// PHASE 6: RESISTANCE TEST (Mutation)
// ============================================
echo "\n========================================\n";
echo "PHASE 6: RESISTANCE TEST (+1.00 Mutation)\n";
echo "========================================\n\n";

$firstEntryId = $groundTruthIds[0];
echo "Modifying entry ID $firstEntryId: amount +1.00, entry_type -> diesel\n";

MachineryLedger::where('id', $firstEntryId)->update([
    'amount' => DB::raw('amount + 1.00'),
    'entry_type' => 'diesel'
]);

// Debug after mutation
$mutatedLedger = MachineryLedger::whereIn('id', $groundTruthIds)
    ->where('is_reversal', false)
    ->orderBy('date')
    ->orderBy('id')
    ->get();

$sortedMutated = $mutatedLedger->sortBy(['date', 'id']);
$mutatedHash = hash('sha256', json_encode($sortedMutated->map(fn($e) => [
    'id' => $e->id,
    'date' => $e->date,
    'amount' => $e->amount,
    'entry_direction' => $e->entry_direction,
    'entry_type' => $e->entry_type,
])->toArray()));

$hashMismatch = $mutatedHash !== $originalHash;
echo "Original Hash: $originalHash\n";
echo "Mutated Hash: $mutatedHash\n";
echo "Hash Mismatch: " . ($hashMismatch ? "true" : "false") . " (expected: true) " . ($hashMismatch ? "✅ PASS" : "❌ FAIL") . "\n";

// Recalculate after mutation
$mutatedCredits = $mutatedLedger->where('entry_direction', 'credit')->sum('amount');
$mutatedDebits = $mutatedLedger->where('entry_direction', 'debit')->sum('amount');
$mutatedNet = $mutatedCredits - $mutatedDebits;

$mutatedDiff = $mutatedNet - $request->net_payable;
$mutatedHasMismatch = abs($mutatedDiff) > 0.01;
$mutatedCanApprove = !$mutatedHasMismatch;

echo "\nRecalculation after mutation:\n";
echo "Net Payable Diff: $mutatedDiff (expected: 1.00) " . (abs($mutatedDiff - 1.00) < 0.01 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Has Mismatch: " . ($mutatedHasMismatch ? "true" : "false") . " (expected: true) " . ($mutatedHasMismatch ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Can Approve: " . ($mutatedCanApprove ? "true" : "false") . " (expected: false) " . (!$mutatedCanApprove ? "✅ PASS" : "❌ FAIL") . "\n";

// ============================================
// PHASE 6b: RESTORE AND VERIFY
// ============================================
echo "\n========================================\n";
echo "PHASE 6b: RESTORE & REVERT STABILITY\n";
echo "========================================\n\n";

echo "Restoring entry to original values...\n";
MachineryLedger::where('id', $firstEntryId)->update([
    'amount' => DB::raw('amount - 1.00'),
    'entry_type' => 'reading'
]);

// Verify restoration
$restoredLedger = MachineryLedger::whereIn('id', $groundTruthIds)
    ->where('is_reversal', false)
    ->orderBy('date')
    ->orderBy('id')
    ->get();

$sortedRestored = $restoredLedger->sortBy(['date', 'id']);
$restoredHash = hash('sha256', json_encode($sortedRestored->map(fn($e) => [
    'id' => $e->id,
    'date' => $e->date,
    'amount' => $e->amount,
    'entry_direction' => $e->entry_direction,
    'entry_type' => $e->entry_type,
])->toArray()));

$restoredCredits = $restoredLedger->where('entry_direction', 'credit')->sum('amount');
$restoredDebits = $restoredLedger->where('entry_direction', 'debit')->sum('amount');
$restoredNet = $restoredCredits - $restoredDebits;
$restoredDiff = $restoredNet - $request->net_payable;

$hashRestored = $restoredHash === $originalHash;
$diffZero = abs($restoredDiff) < 0.01;

echo "Restored Hash: $restoredHash\n";
echo "Original Hash: $originalHash\n";
echo "Hash Restored: " . ($hashRestored ? "true" : "false") . " (expected: true) " . ($hashRestored ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Diff after restore: $restoredDiff (expected: 0) " . ($diffZero ? "✅ PASS" : "❌ FAIL") . "\n";

// ============================================
// PHASE 7: FINAL STATE INTEGRITY
// ============================================
echo "\n========================================\n";
echo "PHASE 7: FINAL STATE INTEGRITY\n";
echo "========================================\n\n";

$finalPR = MachineryPaymentRequest::count();
$finalPP = MachineryPaymentPeriod::count();
$finalLinked = MachineryLedger::whereNotNull('payment_request_id')->count();

echo "Final Counts:\n";
echo "- Payment Requests: $finalPR (baseline: $baselinePR, delta: " . ($finalPR - $baselinePR) . ") " . (($finalPR - $baselinePR) === 1 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "- Payment Periods: $finalPP (baseline: $baselinePP, delta: " . ($finalPP - $baselinePP) . ") " . (($finalPP - $baselinePP) === 0 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "- Linked Ledger: $finalLinked (expected: 0) " . ($finalLinked === 0 ? "✅ PASS" : "❌ FAIL") . "\n";

// ============================================
// FINAL SUMMARY
// ============================================
echo "\n========================================\n";
echo "SCENARIO 1 FINAL SUMMARY\n";
echo "========================================\n\n";

echo "Payment Request ID: $paymentRequestId\n";
echo "Status: draft\n";
echo "Credits: {$request->credits}\n";
echo "Debits: {$request->debits}\n";
echo "Net Payable: {$request->net_payable}\n";
echo "Entries Hash: $originalHash\n";
echo "Entry IDs: [" . implode(', ', $groundTruthIds) . "]\n\n";

echo "All tests completed. Review PASS/FAIL markers above.\n";
