<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== MANPOWER TYPES TABLE STRUCTURE ===" . PHP_EOL;

// Check manpower_types table structure
echo "Manpower Types table columns:" . PHP_EOL;
$columns = Schema::getColumnListing('man_power_types');
foreach ($columns as $column) {
    echo "  - {$column}" . PHP_EOL;
}
