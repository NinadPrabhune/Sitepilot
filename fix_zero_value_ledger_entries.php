<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Finding zero-value ledger entries...\n";

$zeroEntries = DB::select('SELECT id, reference_type, reference_id, debit, credit FROM supplier_transactions WHERE debit = 0 AND credit = 0');

echo "Found " . count($zeroEntries) . " zero-value entries:\n";
foreach ($zeroEntries as $e) {
    echo "ID: {$e->id}, Type: {$e->reference_type}, RefID: {$e->reference_id}, Debit: {$e->debit}, Credit: {$e->credit}\n";
}

if (empty($zeroEntries)) {
    echo "No zero-value entries found.\n";
    exit(0);
}

echo "\nDeleting zero-value entries...\n";
$deleted = DB::delete('DELETE FROM supplier_transactions WHERE debit = 0 AND credit = 0');
echo "Deleted {$deleted} zero-value entries.\n";
echo "Done.\n";
