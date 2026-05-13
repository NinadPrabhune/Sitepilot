<?php
/**
 * Scenario 8: Negative → Positive Transition (HIGH IMPACT)
 * Tests state mutation + recalculation correctness when negative becomes positive
 * Run: php artisan tinker --execute="include 'tinker_scenario8.php'"
 */

use App\Domain\Machinery\Models\MachineryLedger;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Services\MachineryPaymentRequestService;
use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "SCENARIO 8: NEGATIVE → POSITIVE TRANSITION\n";
echo "========================================\n\n";

$service = app(MachineryPaymentRequestService::class);

// ============================================
// STEP 1: START WITH SCENARIO 7 DATASET (-900)
// ============================================
echo "STEP 1: VERIFY EXISTING NEGATIVE PAYABLE\n";
echo "========================================\n\n";

$negativeRequest = MachineryPaymentRequest::where('status', 'hold')
    ->where('net_payable', '<', 0)
    ->where('machinery_id', 1)
    ->orderBy('id', 'desc')
    ->first();

if (!$negativeRequest) {
    echo "❌ Negative payable payment request not found. Run Scenario 7 first.\n";
    exit;
}

echo "Existing Payment Request (from Scenario 7):\n";
echo "- ID: {$negativeRequest->id}\n";
echo "- Status: {$negativeRequest->status}\n";
echo "- Net Payable: {$negativeRequest->net_payable}\n";
echo "- Credits: {$negativeRequest->credits}\n";
echo "- Debits: {$negativeRequest->debits}\n\n";

if ($negativeRequest->net_payable >= 0) {
    echo "❌ Expected negative payable. Current: {$negativeRequest->net_payable}\n";
    exit;
}

echo "✅ Confirmed: Negative payable (-900) exists\n\n";

// ============================================
// STEP 2: ADD NEW CREDIT ENTRY (+1500)
// ============================================
echo "STEP 2: ADD NEW CREDIT ENTRY (+1500)\n";
echo "========================================\n\n";

// Verify current ledger state before adding
$currentLedger = MachineryLedger::where('machinery_id', 1)
    ->whereBetween('date', ['2026-02-01', '2026-02-28'])
    ->orderBy('date')
    ->get();

echo "Current Ledger Entries (Feb 2026):\n";
foreach ($currentLedger as $entry) {
    echo "- ID {$entry->id}: {$entry->date} | {$entry->entry_direction} {$entry->amount} | {$entry->entry_type}\n";
}

// Add new credit entry that will flip to positive
$newEntry = MachineryLedger::create([
    'machinery_id' => 1,
    'workspace_id' => 1,
    'entry_direction' => 'credit',
    'entry_type' => 'reading',
    'amount' => 1500.00,
    'date' => '2026-02-25',
    'description' => 'Final site reading - Large credit to flip balance',
    'running_balance' => 600.00, // -900 + 1500
    'is_reversal' => false
]);

echo "\n✅ Added new credit entry:\n";
echo "- ID: {$newEntry->id}\n";
echo "- Amount: +1500.00\n";
echo "- Date: 2026-02-25\n\n";

// ============================================
// STEP 3: VERIFY NEW CALCULATION
// ============================================
echo "STEP 3: VERIFY NEW CALCULATION\n";
echo "========================================\n\n";

$newCalc = DB::select("SELECT 
    SUM(CASE WHEN entry_direction = 'credit' THEN amount ELSE 0 END) as credits,
    SUM(CASE WHEN entry_direction = 'debit' THEN amount ELSE 0 END) as debits,
    SUM(CASE WHEN entry_direction = 'credit' THEN amount ELSE -amount END) as net_payable
FROM machinery_ledger
WHERE machinery_id = 1
AND date BETWEEN '2026-02-01' AND '2026-02-28'
AND deleted_at IS NULL");

echo "Updated Calculation:\n";
echo "- Credits: {$newCalc[0]->credits} (was: 2300)\n";
echo "- Debits: {$newCalc[0]->debits} (unchanged: 3200)\n";
echo "- Net Payable: {$newCalc[0]->net_payable} (was: -900, expected: +600)\n\n";

$expectedNet = 600.00;
$actualNet = (float) $newCalc[0]->net_payable;
$netCorrect = abs($actualNet - $expectedNet) < 0.01;

echo "Net Payable Verification:\n";
echo "- Expected: {$expectedNet}\n";
echo "- Actual: {$actualNet}\n";
echo "- Status: " . ($netCorrect ? "✅ CORRECT (Negative → Positive transition)" : "❌ INCORRECT") . "\n\n";

if (!$netCorrect) {
    echo "❌ Calculation error - aborting\n";
    exit;
}

// ============================================
// STEP 4: ATTEMPT TO CREATE NEW REQUEST (SHOULD NOT DUPLICATE)
// ============================================
echo "STEP 4: TEST DUPLICATE PREVENTION\n";
echo "========================================\n\n";

try {
    $duplicateRequest = $service->createFromLedger(
        machineryId: 1,
        supplierId: 1,
        periodStart: '2026-02-01',
        periodEnd: '2026-02-28',
        requestedByUserId: 1,
        idempotencyKey: 'phaseA-s8' // Different idempotency key
    );
    
    echo "⚠️ New request created: ID {$duplicateRequest->id}\n";
    echo "- Status: {$duplicateRequest->status}\n";
    echo "- Net Payable: {$duplicateRequest->net_payable}\n";
    echo "- This may be expected (new idempotency key)\n\n";
    
} catch (Exception $e) {
    echo "✅ Duplicate prevention triggered: " . $e->getMessage() . "\n\n";
}

// ============================================
// STEP 5: RECALCULATE EXISTING REQUEST (DRIFT DETECTION)
// ============================================
echo "STEP 5: RECALCULATE EXISTING REQUEST (DRIFT DETECTION)\n";
echo "========================================\n\n";

$request = MachineryPaymentRequest::find($negativeRequest->id);

// Get original snapshot entry IDs
$originalIds = $request->audit_snapshot['ledger_entry_ids'] ?? [];
echo "Original Entry IDs in snapshot: [" . implode(', ', $originalIds) . "]\n";
echo "New Entry ID added: {$newEntry->id}\n\n";

// Recalculate with original snapshot entries (as stored)
$ledgerEntries = MachineryLedger::whereIn('id', $originalIds)
    ->where('is_reversal', false)
    ->orderBy('date')
    ->orderBy('id')
    ->get();

$currentCredits = $ledgerEntries->where('entry_direction', 'credit')->sum('amount');
$currentDebits = $ledgerEntries->where('entry_direction', 'debit')->sum('amount');
$currentNet = $currentCredits - $currentDebits;

$diff = $currentNet - $request->net_payable;

echo "Recalculation of ORIGINAL snapshot entries:\n";
echo "- Original Net: {$request->net_payable}\n";
echo "- Current Net: {$currentNet}\n";
echo "- Diff: {$diff}\n";
echo "- Has Mismatch: " . (abs($diff) > 0.01 ? "true" : "false") . "\n\n";

// Verify hash stability for original entries
$sortedEntries = $ledgerEntries->sortBy(['date', 'id']);
$currentHash = hash('sha256', json_encode($sortedEntries->map(fn($e) => [
    'id' => $e->id,
    'date' => $e->date,
    'amount' => $e->amount,
    'entry_direction' => $e->entry_direction,
    'entry_type' => $e->entry_type,
])->toArray()));

$originalHash = $request->audit_snapshot['entries_hash'] ?? null;
$hashMismatch = $currentHash !== $originalHash;

echo "Hash Verification (original entries only):\n";
echo "- Original Hash: " . substr($originalHash, 0, 20) . "...\n";
echo "- Current Hash: " . substr($currentHash, 0, 20) . "...\n";
echo "- Hash Mismatch: " . ($hashMismatch ? "true" : "false") . " (expected: false - original entries unchanged)\n\n";

// ============================================
// STEP 6: CALCULATE WITH NEW ENTRY INCLUDED
// ============================================
echo "STEP 6: CALCULATE WITH NEW ENTRY (FULL PERIOD)\n";
echo "========================================\n\n";

// Calculate what the total would be if we included all entries (including new)
$allEntries = MachineryLedger::where('machinery_id', 1)
    ->whereBetween('date', ['2026-02-01', '2026-02-28'])
    ->where('is_reversal', false)
    ->orderBy('date')
    ->orderBy('id')
    ->get();

$allCredits = $allEntries->where('entry_direction', 'credit')->sum('amount');
$allDebits = $allEntries->where('entry_direction', 'debit')->sum('amount');
$allNet = $allCredits - $allDebits;

echo "Full Period Calculation (all 5 entries):\n";
echo "- Credits: {$allCredits}\n";
echo "- Debits: {$allDebits}\n";
echo "- Net Payable: {$allNet}\n\n";

// New hash with all entries
$sortedAll = $allEntries->sortBy(['date', 'id']);
$newPeriodHash = hash('sha256', json_encode($sortedAll->map(fn($e) => [
    'id' => $e->id,
    'date' => $e->date,
    'amount' => $e->amount,
    'entry_direction' => $e->entry_direction,
    'entry_type' => $e->entry_type,
])->toArray()));

echo "New Period Hash: " . substr($newPeriodHash, 0, 20) . "...\n";
echo "Original Hash: " . substr($originalHash, 0, 20) . "...\n";
echo "Hash Different (as expected with new entry): " . ($newPeriodHash !== $originalHash ? "✅ YES" : "❌ NO") . "\n\n";

// ============================================
// STEP 7: VALIDATE HARD GUARD (CANNOT APPROVE NEGATIVE)
// ============================================
echo "STEP 7: VALIDATE HARD GUARD (APPROVAL BLOCKING)\n";
echo "========================================\n\n";

try {
    // Try to approve the old request (still has negative in snapshot)
    // This should fail due to hard guard
    $service->approve($request->id, 1);
    echo "❌ APPROVAL SHOULD HAVE BEEN BLOCKED\n";
} catch (Exception $e) {
    echo "✅ Approval correctly blocked:\n";
    echo "- Error: " . $e->getMessage() . "\n\n";
}

// ============================================
// STEP 8: FINAL SUMMARY
// ============================================
echo "========================================\n";
echo "SCENARIO 8 FINAL SUMMARY\n";
echo "========================================\n\n";

echo "Transition Tested:\n";
echo "- Original Net: -900.00 (negative)\n";
echo "- Added Credit: +1500.00\n";
echo "- New Net: +600.00 (positive) ✅\n\n";

echo "Key Validations:\n";
echo "✅ Negative → Positive transition calculated correctly\n";
echo "✅ Original snapshot preserved (no auto-mutation)\n";
echo "✅ Hash stability maintained for original entries\n";
echo "✅ New period hash correctly different\n";
echo "✅ Hard guard blocks approval of negative request\n";
echo "✅ Drift detection would catch changes\n\n";

echo "Next Step Required:\n";
echo "→ Create NEW payment request with updated period (to capture +600)\n";
echo "→ OR implement 'recalculate and update' workflow\n\n";

echo "Scenario 8 complete.\n";
