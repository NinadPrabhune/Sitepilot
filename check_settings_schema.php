<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Checking settings table schema...\n";
    
    // Check if settings table exists
    $tables = DB::select('SHOW TABLES');
    $settingsTableExists = false;
    
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if ($tableName === 'settings') {
            $settingsTableExists = true;
            break;
        }
    }
    
    if (!$settingsTableExists) {
        echo "❌ Settings table does not exist\n";
        exit(1);
    }
    
    // Get settings table structure
    $columns = DB::select('DESCRIBE settings');
    
    echo "Settings table columns:\n";
    foreach ($columns as $column) {
        echo "- {$column->Field} ({$column->Type})\n";
    }
    
    // Check existing settings
    $existingSettings = DB::table('settings')->get();
    echo "\nExisting settings (" . $existingSettings->count() . "):\n";
    foreach ($existingSettings as $setting) {
        echo "- {$setting->key}: {$setting->value}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
