<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== SIMPLE DATA RECOVERY ===" . PHP_EOL;

// Disable foreign key checks
DB::statement('SET FOREIGN_KEY_CHECKS=0;');

// 1. Create workspace
echo "Creating workspace..." . PHP_EOL;
$workspace = DB::table('work_spaces')->insertGetId([
    'name' => 'Default Workspace',
    'slug' => 'default-workspace',
    'created_by' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

// Update users to have workspace
DB::table('users')->update(['workspace_id' => $workspace]);

// 2. Add basic settings
echo "Creating settings..." . PHP_EOL;
$settings = [
    ['key' => 'company_name', 'value' => 'SitePilot ERP'],
    ['key' => 'company_email', 'value' => 'info@sitepilot.com'],
    ['key' => 'company_phone', 'value' => '+91-9876543210'],
    ['key' => 'default_currency', 'value' => 'INR'],
    ['key' => 'date_format', 'value' => 'd-m-Y'],
    ['key' => 'timezone', 'value' => 'Asia/Kolkata'],
];

foreach ($settings as $setting) {
    DB::table('settings')->insert(array_merge($setting, [
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

// 3. Add units
echo "Creating units..." . PHP_EOL;
$units = [
    ['name' => 'Kilograms', 'symbol' => 'KG'],
    ['name' => 'Metric Tons', 'symbol' => 'MT'],
    ['name' => 'Bags', 'symbol' => 'BAG'],
    ['name' => 'Pieces', 'symbol' => 'PCS'],
    ['name' => 'Hours', 'symbol' => 'HRS'],
    ['name' => 'Days', 'symbol' => 'DAYS'],
];

foreach ($units as $unit) {
    DB::table('units')->insert(array_merge($unit, [
        'is_active' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

// 4. Add material categories
echo "Creating material categories..." . PHP_EOL;
$categories = [
    ['name' => 'Cement'],
    ['name' => 'Steel'],
    ['name' => 'Aggregates'],
    ['name' => 'Bricks'],
    ['name' => 'Electrical'],
    ['name' => 'Plumbing'],
];

foreach ($categories as $i => $category) {
    DB::table('material_categories')->insert(array_merge($category, [
        'is_active' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

// 5. Add materials
echo "Creating materials..." . PHP_EOL;
$materials = [
    ['name' => 'Cement 43 Grade', 'sku' => 'CEM43', 'category_id' => 1, 'unit_id' => 1, 'price' => 350],
    ['name' => 'Cement 53 Grade', 'sku' => 'CEM53', 'category_id' => 1, 'unit_id' => 1, 'price' => 380],
    ['name' => 'Steel TMT 12mm', 'sku' => 'STL12', 'category_id' => 2, 'unit_id' => 1, 'price' => 65],
    ['name' => 'River Sand', 'sku' => 'SAND', 'category_id' => 3, 'unit_id' => 2, 'price' => 1200],
    ['name' => 'Red Bricks', 'sku' => 'BRICK', 'category_id' => 4, 'unit_id' => 4, 'price' => 8],
];

foreach ($materials as $material) {
    DB::table('materials')->insert(array_merge($material, [
        'status' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

// 6. Add supplier categories
echo "Creating supplier categories..." . PHP_EOL;
$supplierCategories = [
    ['name' => 'Material Suppliers'],
    ['name' => 'Machinery Suppliers'],
    ['name' => 'Labor Contractors'],
];

foreach ($supplierCategories as $category) {
    DB::table('supplier_categories')->insert(array_merge($category, [
        'is_active' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

// 7. Add suppliers
echo "Creating suppliers..." . PHP_EOL;
$suppliers = [
    [
        'name' => 'UltraTech Cement Ltd',
        'category_id' => 1,
        'type' => 'material',
        'contact_person' => 'Rajesh Kumar',
        'phone' => '9876543210',
        'email' => 'rajesh@ultratech.com',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'gst_number' => '27AAAPU1234C1ZV',
        'is_active' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
    ],
    [
        'name' => 'Tata Steel Ltd',
        'category_id' => 1,
        'type' => 'material',
        'contact_person' => 'Amit Singh',
        'phone' => '9876543211',
        'email' => 'amit@tatasteel.com',
        'city' => 'Jamshedpur',
        'state' => 'Jharkhand',
        'gst_number' => '20AAACT2912D1ZV',
        'is_active' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
    ],
];

foreach ($suppliers as $supplier) {
    DB::table('suppliers')->insert(array_merge($supplier, [
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

// 8. Add machinery categories
echo "Creating machinery categories..." . PHP_EOL;
$machineryCategories = [
    ['name' => 'Earth Moving'],
    ['name' => 'Concrete'],
    ['name' => 'Lifting'],
];

foreach ($machineryCategories as $category) {
    DB::table('machinery_categories')->insert(array_merge($category, [
        'is_active' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

// 9. Add machinery
echo "Creating machinery..." . PHP_EOL;
$machineries = [
    [
        'name' => 'JCB 3DX',
        'category_id' => 1,
        'owned_by' => 'owned',
        'model_number' => '3DX',
        'manufacturer' => 'JCB',
        'status' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
    ],
    [
        'name' => 'Concrete Pump',
        'category_id' => 2,
        'owned_by' => 'rental',
        'model_number' => 'CP-30',
        'manufacturer' => 'Sany',
        'status' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
    ],
];

foreach ($machineries as $machinery) {
    DB::table('machineries')->insert(array_merge($machinery, [
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

// 10. Add manpower types
echo "Creating manpower types..." . PHP_EOL;
$manpowerTypes = [
    ['name' => 'Mason'],
    ['name' => 'Carpenter'],
    ['name' => 'Electrician'],
    ['name' => 'Helper'],
];

foreach ($manpowerTypes as $type) {
    DB::table('man_power_types')->insert(array_merge($type, [
        'status' => 1,
        'created_by' => 1,
        'workspace_id' => $workspace,
        'created_at' => now(),
        'updated_at' => now(),
    ]));
}

// Re-enable foreign key checks
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo PHP_EOL . "✅ Data recovery completed successfully!" . PHP_EOL;

// Show summary
echo PHP_EOL . "=== RECOVERY SUMMARY ===" . PHP_EOL;
echo "Workspaces: " . DB::table('work_spaces')->count() . PHP_EOL;
echo "Settings: " . DB::table('settings')->count() . PHP_EOL;
echo "Units: " . DB::table('units')->count() . PHP_EOL;
echo "Material Categories: " . DB::table('material_categories')->count() . PHP_EOL;
echo "Materials: " . DB::table('materials')->count() . PHP_EOL;
echo "Supplier Categories: " . DB::table('supplier_categories')->count() . PHP_EOL;
echo "Suppliers: " . DB::table('suppliers')->count() . PHP_EOL;
echo "Machinery Categories: " . DB::table('machinery_categories')->count() . PHP_EOL;
echo "Machinery: " . DB::table('machineries')->count() . PHP_EOL;
echo "Manpower Types: " . DB::table('man_power_types')->count() . PHP_EOL;
