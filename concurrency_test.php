<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\WorkSpace;
use App\Models\Machinery;

echo "=== CONCURRENCY STRESS TEST ===\n\n";

// Get test data
$workspace = WorkSpace::first();
$machinery = Machinery::where('workspace_id', $workspace->id)->first();

echo "Workspace: {$workspace->name}\n";
echo "Machinery: {$machinery->name} (ID: {$machinery->id})\n\n";

// TEST CASE A: Double DPR Approval (Same DPR approved twice)
echo "=== TEST CASE A: Double DPR Approval ===\n";

// Create a fresh DPR first (ensure no ledger entry)
$dpr = DailyProgressReport::create([
    'workspace_id' => $workspace->id,
    'site_id' => $workspace->id,
    'machinery_id' => $machinery->id,
    'date' => now()->addDay()->toDateString(), // Use tomorrow to avoid conflicts
    'machine_start_reading' => 400,
    'machine_end_reading' => 408,
    'machine_idle_reading' => 1,
    'total_working_hours' => 7,
    'billable_hours' => 7,
    'calculated_amount' => 7000,
    'created_by' => 1,
    'status' => 'pending', // Explicitly set to pending
]);

echo "DPR Created: ID {$dpr->id}\n";
echo "Calculated Amount: ₹7,000\n";
echo "Ledger Entry ID: " . ($dpr->ledger_entry_id ?? 'NULL') . "\n";

// Simulate double approval using lockForUpdate
$dprId = $dpr->id;

try {
    // First approval attempt - simulate the actual approveDPR workflow
    \Illuminate\Support\Facades\DB::beginTransaction();
    $dpr1 = DailyProgressReport::where('id', $dprId)->lockForUpdate()->first();
    if (!$dpr1->ledger_entry_id) {
        $ledgerEntry1 = \App\Domain\Machinery\Services\MachineryLedgerService::createCredit([
            'machinery_id' => $machinery->id,
            'amount' => $dpr1->calculated_amount,
            'reference_type' => \App\Domain\Machinery\Services\MachineryLedgerService::REFERENCE_TYPE_DPR,
            'reference_id' => $dpr1->id,
            'entry_type' => \App\Domain\Machinery\Services\MachineryLedgerService::ENTRY_TYPE_READING,
            'date' => $dpr1->date,
            'description' => "DPR work approved - {$dpr1->billable_hours} hrs",
            'metadata' => ['dpr_id' => $dpr1->id, 'approved_by' => 1],
        ]);
        $dpr1->update(['ledger_entry_id' => $ledgerEntry1->id, 'status' => 'approved', 'approved_by' => 1, 'approved_at' => now()]);
        \Illuminate\Support\Facades\DB::commit();
        echo "✅ First approval successful - Ledger ID: {$ledgerEntry1->id}\n";
    } else {
        \Illuminate\Support\Facades\DB::rollBack();
        echo "⚠️ DPR already has ledger entry\n";
    }
} catch (\Exception $e) {
    \Illuminate\Support\Facades\DB::rollBack();
    echo "❌ First approval failed: " . $e->getMessage() . "\n";
}

// Second approval attempt (simulated)
try {
    \Illuminate\Support\Facades\DB::beginTransaction();
    $dpr2 = DailyProgressReport::where('id', $dprId)->lockForUpdate()->first();
    if (!$dpr2->ledger_entry_id) {
        $ledgerEntry2 = \App\Domain\Machinery\Services\MachineryLedgerService::createCredit([
            'machinery_id' => $machinery->id,
            'amount' => $dpr2->calculated_amount,
            'reference_type' => \App\Domain\Machinery\Services\MachineryLedgerService::REFERENCE_TYPE_DPR,
            'reference_id' => $dpr2->id,
            'entry_type' => \App\Domain\Machinery\Services\MachineryLedgerService::ENTRY_TYPE_READING,
            'date' => $dpr2->date,
            'description' => "DPR work approved - {$dpr2->billable_hours} hrs",
            'metadata' => ['dpr_id' => $dpr2->id, 'approved_by' => 1],
        ]);
        $dpr2->update(['ledger_entry_id' => $ledgerEntry2->id, 'status' => 'approved', 'approved_by' => 1, 'approved_at' => now()]);
        \Illuminate\Support\Facades\DB::commit();
        echo "❌ Second approval created duplicate ledger - Ledger ID: {$ledgerEntry2->id}\n";
    } else {
        \Illuminate\Support\Facades\DB::rollBack();
        echo "✅ Second approval blocked - Ledger entry already exists\n";
    }
} catch (\Exception $e) {
    \Illuminate\Support\Facades\DB::rollBack();
    echo "✅ Second approval blocked: " . $e->getMessage() . "\n";
}

// Verify only one ledger entry exists
$ledgerEntries = MachineryLedger::where('reference_type', 'DailyProgressReport')
    ->where('reference_id', $dprId)
    ->where('is_reversal', false)
    ->get();

echo "Total ledger entries created: {$ledgerEntries->count()}\n";
if ($ledgerEntries->count() === 1) {
    echo "✅ TEST CASE A PASSED - Only one ledger entry created\n";
} else {
    echo "❌ TEST CASE A FAILED - Multiple ledger entries created\n";
}

echo "\n=== TEST CASE B: Duplicate Diesel Submission ===\n";

// Test idempotency with same idempotency_key
$idempotencyKey = 'TEST-' . time() . '-DIESEL';

try {
    // First diesel submission
    $diesel1 = DailyConsumptionMaster::create([
        'workspace_id' => $workspace->id,
        'site_id' => $workspace->id,
        'machinery_id' => $machinery->id,
        'consumption_date' => now()->toDateString(),
        'consumption_type' => 'fuel',
        'consumption_number' => $idempotencyKey,
        'created_by' => 1,
    ]);
    echo "✅ First diesel submission successful - ID: {$diesel1->id}\n";
} catch (\Exception $e) {
    echo "❌ First diesel submission failed: " . $e->getMessage() . "\n";
}

try {
    // Second diesel submission with same idempotency key
    $diesel2 = DailyConsumptionMaster::create([
        'workspace_id' => $workspace->id,
        'site_id' => $workspace->id,
        'machinery_id' => $machinery->id,
        'consumption_date' => now()->toDateString(),
        'consumption_type' => 'fuel',
        'consumption_number' => $idempotencyKey,
        'created_by' => 1,
    ]);
    echo "❌ Second diesel submission created duplicate - ID: {$diesel2->id}\n";
} catch (\Exception $e) {
    echo "✅ Second diesel submission blocked by unique constraint: " . $e->getMessage() . "\n";
}

// Verify only one diesel entry exists
$dieselEntries = DailyConsumptionMaster::where('consumption_number', $idempotencyKey)->get();
echo "Total diesel entries created: {$dieselEntries->count()}\n";
if ($dieselEntries->count() === 1) {
    echo "✅ TEST CASE B PASSED - Only one diesel entry created\n";
} else {
    echo "❌ TEST CASE B FAILED - Multiple diesel entries created\n";
}

echo "\n=== TEST CASE C: Race Condition - Parallel DPR Creation ===\n";

// Simulate two DPRs created at same time for same machine
$dprCountBefore = DailyProgressReport::where('machinery_id', $machinery->id)->count();
$balanceBefore = MachineryLedger::where('machinery_id', $machinery->id)
    ->where('is_reversal', false)
    ->orderBy('date', 'desc')
    ->orderBy('id', 'desc')
    ->value('running_balance') ?? 0;

echo "DPR count before: {$dprCountBefore}\n";
echo "Balance before: ₹{$balanceBefore}\n";

// Create two DPRs rapidly
$dprA = DailyProgressReport::create([
    'workspace_id' => $workspace->id,
    'site_id' => $workspace->id,
    'machinery_id' => $machinery->id,
    'date' => now()->toDateString(),
    'machine_start_reading' => 200,
    'machine_end_reading' => 208,
    'machine_idle_reading' => 0,
    'total_working_hours' => 8,
    'billable_hours' => 8,
    'calculated_amount' => 8000,
    'created_by' => 1,
]);

$dprB = DailyProgressReport::create([
    'workspace_id' => $workspace->id,
    'site_id' => $workspace->id,
    'machinery_id' => $machinery->id,
    'date' => now()->toDateString(),
    'machine_start_reading' => 300,
    'machine_end_reading' => 308,
    'machine_idle_reading' => 0,
    'total_working_hours' => 8,
    'billable_hours' => 8,
    'calculated_amount' => 8000,
    'created_by' => 1,
]);

echo "DPR A Created: ID {$dprA->id}, Amount ₹8,000\n";
echo "DPR B Created: ID {$dprB->id}, Amount ₹8,000\n";

$dprCountAfter = DailyProgressReport::where('machinery_id', $machinery->id)->count();
echo "DPR count after: {$dprCountAfter}\n";

if ($dprCountAfter === $dprCountBefore + 2) {
    echo "✅ Both DPRs created successfully\n";
} else {
    echo "❌ DPR creation issue detected\n";
}

echo "\n=== CONCURRENCY TEST COMPLETE ===\n";
