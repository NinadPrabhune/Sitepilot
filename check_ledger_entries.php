<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check payment request 1
$paymentRequest = \App\Domain\Machinery\Models\MachineryPaymentRequest::with(['ledgerEntries', 'machinery', 'supplier'])->find(1);

if ($paymentRequest) {
    echo "Payment Request ID: " . $paymentRequest->id . PHP_EOL;
    echo "Status: " . $paymentRequest->status . PHP_EOL;
    echo "Machinery: " . ($paymentRequest->machinery->name ?? 'N/A') . PHP_EOL;
    echo "Supplier: " . ($paymentRequest->supplier->name ?? 'N/A') . PHP_EOL;
    echo "Period: " . $paymentRequest->period_start . " to " . $paymentRequest->period_end . PHP_EOL;
    echo "Credits: " . $paymentRequest->credits . PHP_EOL;
    echo "Debits: " . $paymentRequest->debits . PHP_EOL;
    echo "Net Payable: " . $paymentRequest->net_payable . PHP_EOL;
    
    echo PHP_EOL . "=== LEDGER ENTRIES RELATIONSHIP ===" . PHP_EOL;
    $ledgerEntries = $paymentRequest->ledgerEntries;
    echo "Ledger Entries Count: " . $ledgerEntries->count() . PHP_EOL;
    
    foreach ($ledgerEntries as $entry) {
        echo "Entry ID: " . $entry->id . ", Date: " . $entry->date . ", Direction: " . $entry->entry_direction . ", Amount: " . $entry->amount . PHP_EOL;
    }
    
    echo PHP_EOL . "=== AUDIT SNAPSHOT ===" . PHP_EOL;
    $auditSnapshot = $paymentRequest->audit_snapshot;
    if (is_string($auditSnapshot)) {
        $auditSnapshot = json_decode($auditSnapshot, true);
    }
    
    if (isset($auditSnapshot['ledger_entry_ids'])) {
        echo "Ledger Entry IDs from audit snapshot: " . implode(', ', $auditSnapshot['ledger_entry_ids']) . PHP_EOL;
        echo "Entry count from audit snapshot: " . count($auditSnapshot['ledger_entry_ids']) . PHP_EOL;
        
        // Now check the actual ledger entries using these IDs
        $entryIds = $auditSnapshot['ledger_entry_ids'];
        $actualEntries = \App\Domain\Machinery\Models\MachineryLedger::whereIn('id', $entryIds)->get();
        echo "Actual ledger entries found: " . $actualEntries->count() . PHP_EOL;
        
        foreach ($actualEntries as $entry) {
            echo "Actual Entry ID: " . $entry->id . ", Date: " . $entry->date . ", Direction: " . $entry->entry_direction . ", Amount: " . $entry->amount . ", Payment Request ID: " . $entry->payment_request_id . PHP_EOL;
        }
    }
    
    if (isset($auditSnapshot['entry_details'])) {
        echo "Entry details count: " . count($auditSnapshot['entry_details']) . PHP_EOL;
        foreach ($auditSnapshot['entry_details'] as $entry) {
            echo "Detail Entry ID: " . $entry['id'] . ", Date: " . $entry['date'] . ", Direction: " . $entry['direction'] . ", Amount: " . $entry['amount'] . PHP_EOL;
        }
    }
    
    // Also check all ledger entries for this machinery in the period
    echo PHP_EOL . "=== ALL LEDGER ENTRIES FOR MACHINERY IN PERIOD ===" . PHP_EOL;
    $allEntries = \App\Domain\Machinery\Models\MachineryLedger::where('machinery_id', $paymentRequest->machinery_id)
        ->whereBetween('date', [$paymentRequest->period_start, $paymentRequest->period_end])
        ->orderBy('date')
        ->orderBy('id')
        ->get();
    
    echo "All entries for machinery in period: " . $allEntries->count() . PHP_EOL;
    foreach ($allEntries as $entry) {
        echo "All Entry ID: " . $entry->id . ", Date: " . $entry->date . ", Direction: " . $entry->entry_direction . ", Amount: " . $entry->amount . ", Is Reversal: " . ($entry->is_reversal ? 'Yes' : 'No') . ", Payment Request ID: " . ($entry->payment_request_id ?? 'NULL') . PHP_EOL;
    }
    
} else {
    echo "Payment Request with ID 1 not found." . PHP_EOL;
}
