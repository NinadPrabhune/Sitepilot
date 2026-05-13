<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TABLE STRUCTURE CHECK ===" . PHP_EOL;

// Check units table structure
echo "Units table columns:" . PHP_EOL;
$columns = Schema::getColumnListing('units');
foreach ($columns as $column) {
    echo "  - {$column}" . PHP_EOL;
}

echo PHP_EOL;

// Check material_categories table structure
echo "Material Categories table columns:" . PHP_EOL;
$columns = Schema::getColumnListing('material_categories');
foreach ($columns as $column) {
    echo "  - {$column}" . PHP_EOL;
}

echo PHP_EOL;

// Check suppliers table structure
echo "Suppliers table columns:" . PHP_EOL;
$columns = Schema::getColumnListing('suppliers');
foreach ($columns as $column) {
    echo "  - {$column}" . PHP_EOL;
}
