<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Fixing UserSeeder role assignment...\n";
    
    // Get admin user
    $admin = \App\Models\User::where('type', 'super admin')->first();
    if (!$admin) {
        echo "No admin user found. Creating one first...\n";
        $admin = new \App\Models\User();
        $admin->name = 'Super Admin';
        $admin->email = 'admin@sitepilot.com';
        $admin->password = \Illuminate\Support\Facades\Hash::make('admin123');
        $admin->email_verified_at = now();
        $admin->type = 'super admin';
        $admin->active_status = 1;
        $admin->workspace_id = 1;
        $admin->created_by = 0;
        $admin->save();
        echo "Admin user created with ID: " . $admin->id . "\n";
    }
    
    // Check if company user exists
    $company = \App\Models\User::where('type', 'company')->first();
    
    if (!$company) {
        echo "Creating company user...\n";
        
        $company = new \App\Models\User();
        $company->name = 'WorkDo';
        $company->email = 'company@example.com';
        $company->password = \Illuminate\Support\Facades\Hash::make('1234');
        $company->email_verified_at = now();
        $company->type = 'company';
        $company->active_status = 1;
        $company->active_workspace = 1;
        $company->avatar = 'uploads/users-avatar/avatar.png';
        $company->dark_mode = 0;
        $company->lang = 'en';
        $company->referral_code = rand(100000, 999999);
        $company->workspace_id = 1;
        $company->created_by = $admin->id;
        $company->save();
        
        echo "Company user created with ID: " . $company->id . "\n";
        
        // Create workspace
        $workspace = new \App\Models\WorkSpace();
        $workspace->name = 'WorkDo';
        $workspace->slug = 'workdo';
        $workspace->created_by = $company->id;
        $workspace->save();
        
        echo "Workspace created with ID: " . $workspace->id . "\n";
        
        // Update company user with workspace
        $company->workspace_id = $workspace->id;
        $company->active_workspace = $workspace->id;
        $company->save();
        
        // Get roles and assign
        $role_r = \App\Models\Role::where('name', 'company')->first();
        if ($role_r) {
            $company->roles()->attach($role_r->id);
            echo "Company role assigned to user\n";
        }
        
        // Call company settings
        if (method_exists(\App\Models\User::class, 'CompanySetting')) {
            \App\Models\User::CompanySetting($company->id);
            echo "Company settings initialized\n";
        }
        
        // Create default warehouse
        if (class_exists('\App\Models\Warehouse')) {
            \App\Models\Warehouse::defaultdata();
            echo "Default warehouse created\n";
        }
        
    } else {
        echo "Company user already exists with ID: " . $company->id . "\n";
    }
    
    echo "✅ UserSeeder fix completed successfully!\n";
    
    // Verify results
    $userCount = \App\Models\User::count();
    $workspaceCount = \App\Models\WorkSpace::count();
    
    echo "\nFinal status:\n";
    echo "Users: $userCount\n";
    echo "Workspaces: $workspaceCount\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
