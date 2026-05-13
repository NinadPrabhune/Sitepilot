<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== MATERIALS TABLE STRUCTURE ===" . PHP_EOL;

// Check materials table structure
echo "Materials table columns:" . PHP_EOL;
$columns = Schema::getColumnListing('materials');
foreach ($columns as $column) {
    echo "  - {$column}" . PHP_EOL;
}

echo PHP_EOL;

// Check machinery table structure
echo "Machinery table columns:" . PHP_EOL;
$columns = Schema::getColumnListing('machineries');
foreach ($columns as $column) {
    echo "  - {$column}" . PHP_EOL;
}
