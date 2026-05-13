<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Creating admin user...\n";
    
    // Check if admin already exists
    $existingAdmin = \App\Models\User::where('email', 'admin@sitepilot.com')->first();
    if ($existingAdmin) {
        echo "Admin user already exists with ID: " . $existingAdmin->id . "\n";
        exit(0);
    }
    
    // Create admin user
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
    
    echo "✅ Admin user created successfully!\n";
    echo "ID: " . $admin->id . "\n";
    echo "Email: admin@sitepilot.com\n";
    echo "Password: admin123\n";
    
    // Log the creation
    \Illuminate\Support\Facades\Log::info('ADMIN_USER_CREATED', [
        'user_id' => $admin->id,
        'email' => $admin->email,
        'timestamp' => now()->toISOString()
    ]);
    
} catch (Exception $e) {
    echo "❌ Error creating admin user: " . $e->getMessage() . "\n";
    exit(1);
}
