<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Permission;
use App\Models\Role;

// Get permissions
$permissions = Permission::whereIn('name', [
    'monthly-control manage', 
    'machinery-payment manage', 
    'machinery-billing manage'
])->get();

// Get admin role
$adminRole = Role::where('name', 'admin')->first();

if ($adminRole) {
    // Attach permissions to admin role
    $adminRole->permissions()->attach($permissions);
    echo "Permissions attached to admin role successfully!\n";
    
    // Display attached permissions
    foreach ($permissions as $permission) {
        echo "- {$permission->name}\n";
    }
} else {
    echo "Admin role not found!\n";
}
