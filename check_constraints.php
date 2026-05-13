<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking existing CHECK constraints on supplier_transactions...\n";

$constraints = DB::select('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "supplier_transactions" AND CONSTRAINT_TYPE = "CHECK"');

echo "Found " . count($constraints) . " constraints:\n";
foreach ($constraints as $c) {
    echo "  - " . $c->CONSTRAINT_NAME . "\n";
}
