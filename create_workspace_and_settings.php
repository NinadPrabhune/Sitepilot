<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Creating workspace and settings...\n";
    
    // Get company user
    $company = \App\Models\User::where('type', 'company')->first();
    if (!$company) {
        throw new Exception("Company user not found");
    }
    
    // Create workspace
    $workspace = \App\Models\WorkSpace::first();
    if (!$workspace) {
        $workspace = new \App\Models\WorkSpace();
        $workspace->name = 'WorkDo';
        $workspace->slug = 'workdo';
        $workspace->email = $company->email;
        $workspace->phone = '';
        $workspace->address = '';
        $workspace->created_by = $company->id;
        $workspace->save();
        
        echo "✅ Workspace created with ID: " . $workspace->id . "\n";
        
        // Update company user with workspace
        $company->workspace_id = $workspace->id;
        $company->active_workspace = $workspace->id;
        $company->save();
        
        echo "✅ Company user updated with workspace ID: " . $workspace->id . "\n";
    } else {
        echo "Workspace already exists with ID: " . $workspace->id . "\n";
    }
    
    // Create basic settings
    $settingsData = [
        ['key' => 'company_name', 'value' => 'WorkDo'],
        ['key' => 'company_email', 'value' => $company->email],
        ['key' => 'default_timezone', 'value' => 'Asia/Kolkata'],
        ['key' => 'default_currency', 'value' => 'INR'],
        ['key' => 'default_date_format', 'value' => 'Y-m-d'],
        ['key' => 'default_time_format', 'value' => 'H:i:s'],
        ['key' => 'company_logo', 'value' => ''],
        ['key' => 'company_favicon', 'value' => ''],
        ['key' => 'footer_text', 'value' => '© ' . date('Y') . ' WorkDo'],
    ];
    
    foreach ($settingsData as $setting) {
        $existingSetting = \App\Models\Setting::where('key', $setting['key'])->first();
        if (!$existingSetting) {
            \App\Models\Setting::create([
                'key' => $setting['key'],
                'value' => $setting['value'],
                'workspace' => $workspace->id,
                'created_by' => $company->id
            ]);
        }
    }
    
    echo "✅ Basic settings created\n";
    
    // Create default warehouse if class exists
    if (class_exists('\App\Models\Warehouse')) {
        $warehouse = \App\Models\Warehouse::first();
        if (!$warehouse) {
            \App\Models\Warehouse::defaultdata();
            echo "✅ Default warehouse created\n";
        }
    }
    
    echo "\nFinal status:\n";
    echo "Users: " . \App\Models\User::count() . "\n";
    echo "Workspaces: " . \App\Models\WorkSpace::count() . "\n";
    echo "Settings: " . \App\Models\Setting::count() . "\n";
    
    // Log completion
    \Illuminate\Support\Facades\Log::info('SYSTEM_SETUP_COMPLETED', [
        'users_count' => \App\Models\User::count(),
        'workspaces_count' => \App\Models\WorkSpace::count(),
        'settings_count' => \App\Models\Setting::count(),
        'timestamp' => now()->toISOString()
    ]);
    
    echo "\n✅ System setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
