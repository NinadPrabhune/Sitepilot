<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== MACHINERY TABLE TRUNCATE AND UNIQUE COMBINATION INSERT ===\n";

// Step 1: Show current combinations before truncation
echo "\n--- BEFORE TRUNCATION ---\n";
$combinations = DB::table('machineries')
    ->select('owned_by', 'rate_type', 'status', DB::raw('COUNT(*) as count'))
    ->groupBy('owned_by', 'rate_type', 'status')
    ->orderBy('owned_by')
    ->orderBy('rate_type')
    ->orderBy('status')
    ->get();

foreach($combinations as $comb) {
    echo "Ownership: " . ($comb->owned_by ?? 'NULL') . 
         ", Rate Type: " . ($comb->rate_type ?? 'NULL') . 
         ", Status: " . ($comb->status ?? 'NULL') . 
         " (Count: " . $comb->count . ")" . PHP_EOL;
}

$totalBefore = DB::table('machineries')->count();
echo "Total records before truncation: " . $totalBefore . PHP_EOL;

// Step 2: Get unique combinations before truncation
$uniqueCombinations = DB::table('machineries')
    ->select('owned_by', 'rate_type', 'status')
    ->distinct()
    ->get();

echo "\n--- UNIQUE COMBINATIONS TO INSERT ---\n";
foreach($uniqueCombinations as $comb) {
    echo "Ownership: " . ($comb->owned_by ?? 'NULL') . 
         ", Rate Type: " . ($comb->rate_type ?? 'NULL') . 
         ", Status: " . ($comb->status ?? 'NULL') . PHP_EOL;
}

// Step 3: Truncate the table
echo "\n--- TRUNCATING TABLE ---\n";
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    DB::table('machineries')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "Table truncated successfully." . PHP_EOL;
} catch (Exception $e) {
    echo "Error truncating table: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Step 4: Insert unique combinations with sample data
echo "\n--- INSERTING UNIQUE COMBINATIONS ---\n";
$insertCount = 0;

foreach($uniqueCombinations as $index => $comb) {
    try {
        $machineryData = [
            'name' => 'Sample Machine - ' . ($comb->owned_by ?? 'NULL') . '-' . ($comb->rate_type ?? 'NULL') . '-' . ($comb->status ?? 'NULL'),
            'category_id' => 1, // Assuming category 1 exists
            'owned_by' => $comb->owned_by,
            'rate_type' => $comb->rate_type,
            'status' => $comb->status,
            'operational_status' => 'active',
            'created_by' => 1, // Assuming user 1 exists
            'workspace_id' => 1, // Assuming workspace 1 exists
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('machineries')->insertGetId($machineryData);
        echo "Inserted combination #" . ($index + 1) . " with ID: " . $id . PHP_EOL;
        $insertCount++;
    } catch (Exception $e) {
        echo "Error inserting combination: " . $e->getMessage() . PHP_EOL;
    }
}

// Step 5: Show final result
echo "\n--- AFTER INSERTION ---\n";
$totalAfter = DB::table('machineries')->count();
echo "Total records after insertion: " . $totalAfter . PHP_EOL;

$finalCombinations = DB::table('machineries')
    ->select('owned_by', 'rate_type', 'status', DB::raw('COUNT(*) as count'))
    ->groupBy('owned_by', 'rate_type', 'status')
    ->orderBy('owned_by')
    ->orderBy('rate_type')
    ->orderBy('status')
    ->get();

foreach($finalCombinations as $comb) {
    echo "Ownership: " . ($comb->owned_by ?? 'NULL') . 
         ", Rate Type: " . ($comb->rate_type ?? 'NULL') . 
         ", Status: " . ($comb->status ?? 'NULL') . 
         " (Count: " . $comb->count . ")" . PHP_EOL;
}

echo "\n=== SUMMARY ===\n";
echo "Records before: " . $totalBefore . PHP_EOL;
echo "Unique combinations found: " . $uniqueCombinations->count() . PHP_EOL;
echo "Records inserted: " . $insertCount . PHP_EOL;
echo "Records after: " . $totalAfter . PHP_EOL;

if ($totalAfter === $uniqueCombinations->count()) {
    echo "✅ SUCCESS: Only unique combinations inserted!" . PHP_EOL;
} else {
    echo "❌ WARNING: Record count mismatch!" . PHP_EOL;
}
