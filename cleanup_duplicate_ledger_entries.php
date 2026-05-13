<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Finding duplicate supplier_transactions...\n";

$duplicates = DB::select("
    SELECT reference_type, reference_id, supplier_id, site_id, COUNT(*) as count, GROUP_CONCAT(id) as ids
    FROM supplier_transactions
    GROUP BY reference_type, reference_id, supplier_id, site_id
    HAVING count > 1
");

if (empty($duplicates)) {
    echo "No duplicates found.\n";
    exit(0);
}

echo "Found " . count($duplicates) . " duplicate groups:\n";
foreach ($duplicates as $dup) {
    echo "  - Type: {$dup->reference_type}, RefID: {$dup->reference_id}, Supplier: {$dup->supplier_id}, Site: {$dup->site_id}, Count: {$dup->count}, IDs: {$dup->ids}\n";
}

echo "\nRemoving duplicates (keeping the oldest entry by ID)...\n";

foreach ($duplicates as $dup) {
    $ids = explode(',', $dup->ids);
    sort($ids); // Oldest first
    $keepId = array_shift($ids); // Keep the oldest
    $deleteIds = $ids;
    
    if (!empty($deleteIds)) {
        DB::table('supplier_transactions')
            ->whereIn('id', $deleteIds)
            ->delete();
        echo "  - Kept ID {$keepId}, deleted IDs: " . implode(', ', $deleteIds) . "\n";
    }
}

echo "\nCleanup complete.\n";
