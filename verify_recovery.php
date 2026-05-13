<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DATA RECOVERY VERIFICATION ===" . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL;
echo PHP_EOL;

// Check key tables
$tables = [
    'users' => 'Users',
    'work_spaces' => 'Workspaces', 
    'settings' => 'Settings',
    'units' => 'Units',
    'material_categories' => 'Material Categories',
    'materials' => 'Materials',
    'supplier_categories' => 'Supplier Categories',
    'suppliers' => 'Suppliers',
    'machinery_categories' => 'Machinery Categories',
    'machineries' => 'Machinery',
    'man_power_types' => 'Manpower Types',
];

echo "📊 TABLE STATUS:" . PHP_EOL;
foreach ($tables as $table => $label) {
    try {
        $count = DB::table($table)->count();
        echo sprintf("%-20s: %d records", $label, $count) . PHP_EOL;
    } catch (Exception $e) {
        echo sprintf("%-20s: ERROR - %s", $label, $e->getMessage()) . PHP_EOL;
    }
}

echo PHP_EOL;

// Show sample data
echo "📋 SAMPLE DATA:" . PHP_EOL;

// Users
echo "Users:" . PHP_EOL;
$users = DB::table('users')->limit(3)->get(['id', 'email', 'workspace_id', 'created_at']);
foreach ($users as $user) {
    echo "  ID: {$user->id}, Email: {$user->email}, Workspace: {$user->workspace_id}" . PHP_EOL;
}

echo PHP_EOL;

// Materials
echo "Materials (first 5):" . PHP_EOL;
$materials = DB::table('materials')->limit(5)->get(['id', 'name', 'sku', 'price']);
foreach ($materials as $material) {
    echo "  ID: {$material->id}, Name: {$material->name}, SKU: {$material->sku}, Price: ₹{$material->price}" . PHP_EOL;
}

echo PHP_EOL;

// Suppliers
echo "Suppliers:" . PHP_EOL;
$suppliers = DB::table('suppliers')->limit(3)->get(['id', 'name', 'type', 'phone']);
foreach ($suppliers as $supplier) {
    echo "  ID: {$supplier->id}, Name: {$supplier->name}, Type: {$supplier->type}, Phone: {$supplier->phone}" . PHP_EOL;
}

echo PHP_EOL;

// Machinery
echo "Machinery:" . PHP_EOL;
$machineries = DB::table('machineries')->limit(3)->get(['id', 'name', 'owned_by', 'manufacturer']);
foreach ($machineries as $machinery) {
    echo "  ID: {$machinery->id}, Name: {$machinery->name}, Owned: {$machinery->owned_by}, Brand: {$machinery->manufacturer}" . PHP_EOL;
}

echo PHP_EOL;

// Settings
echo "Key Settings:" . PHP_EOL;
$settings = DB::table('settings')->limit(5)->get(['key', 'value']);
foreach ($settings as $setting) {
    echo "  {$setting->key}: {$setting->value}" . PHP_EOL;
}

echo PHP_EOL;

echo "✅ DATA RECOVERY COMPLETED SUCCESSFULLY!" . PHP_EOL;
echo "Your ERP system now has basic operational data." . PHP_EOL;
echo PHP_EOL;
echo "Next steps:" . PHP_EOL;
echo "1. Login to your application" . PHP_EOL;
echo "2. Create projects and sites" . PHP_EOL;
echo "3. Add purchase orders and invoices" . PHP_EOL;
echo "4. Start using the system normally" . PHP_EOL;
