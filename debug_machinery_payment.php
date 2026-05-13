<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;

echo "=== Machinery Payment Request Debug ===\n\n";

// Get all machineries
$machineries = Machinery::all();
echo "Available Machineries:\n";
foreach ($machineries as $machinery) {
    echo "- ID: {$machinery->id}, Name: {$machinery->name}\n";
}

echo "\n=== Recent Ledger Entries ===\n";
$recentEntries = MachineryLedger::with('machinery')
    ->orderBy('date', 'desc')
    ->orderBy('id', 'desc')
    ->take(10)
    ->get();

foreach ($recentEntries as $entry) {
    echo "ID: {$entry->id}, Machinery: {$entry->machinery->name}, Date: {$entry->date}, Amount: {$entry->amount}, Direction: {$entry->entry_direction}, Type: {$entry->entry_type}, Reversal: " . ($entry->is_reversal ? 'Yes' : 'No') . ", Payment Request ID: " . ($entry->payment_request_id ?? 'NULL') . "\n";
}

echo "\n=== Test Query Example ===\n";
// Test with the first machinery
if ($machineries->isNotEmpty()) {
    $machinery = $machineries->first();
    $periodStart = date('Y-m-01'); // First day of current month
    $periodEnd = date('Y-m-t'); // Last day of current month
    
    echo "Testing with Machinery ID: {$machinery->id}, Period: {$periodStart} to {$periodEnd}\n";
    
    // Step 1: All entries in period
    $allEntries = MachineryLedger::where('machinery_id', $machinery->id)
        ->whereBetween('date', [$periodStart, $periodEnd])
        ->get();
    
    echo "Step 1 - All entries in period: {$allEntries->count()}\n";
    
    // Step 2: Non-reversal entries
    $nonReversalEntries = $allEntries->where('is_reversal', false);
    echo "Step 2 - Non-reversal entries: {$nonReversalEntries->count()}\n";
    
    // Step 3: Unpaid entries
    $unpaidEntries = $nonReversalEntries->whereNull('payment_request_id');
    echo "Step 3 - Unpaid entries: {$unpaidEntries->count()}\n";
    
    if ($allEntries->isNotEmpty()) {
        echo "\nEntries found in period:\n";
        foreach ($allEntries as $entry) {
            $status = [];
            if ($entry->is_reversal) $status[] = 'REVERSAL';
            if ($entry->payment_request_id) $status[] = 'PAID';
            $statusStr = empty($status) ? 'ELIGIBLE' : implode(', ', $status);
            
            echo "- ID: {$entry->id}, Date: {$entry->date}, Amount: {$entry->amount}, Status: {$statusStr}\n";
        }
    }
}

echo "\n=== Instructions ===\n";
echo "To debug your specific case:\n";
echo "1. Visit: /machinery/payment-requests/debug-ledger-query?machinery_id=YOUR_MACHINERY_ID&period_start=2026-05-01&period_end=2026-05-31\n";
echo "2. Replace YOUR_MACHINERY_ID with the actual machinery ID\n";
echo "3. Adjust the period dates as needed\n";
echo "4. Check the Laravel logs at storage/logs/laravel.log for detailed debug information\n";

echo "\n=== Common Issues ===\n";
echo "- All entries are already linked to payment requests (payment_request_id is not NULL)\n";
echo "- All entries are reversal entries (is_reversal = true)\n";
echo "- No entries exist for the specified machinery and period\n";
echo "- Date format mismatch in the request\n";
