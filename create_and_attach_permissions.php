<?php

// Connect to database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'sitepilot_local';

try {
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n";
    
    // Define permissions to create
    $permissions_to_create = [
        'monthly-control manage',
        'machinery-payment manage', 
        'machinery-billing manage'
    ];
    
    // Create permissions if they don't exist
    foreach ($permissions_to_create as $permission_name) {
        $stmt = $conn->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$permission_name]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            $stmt = $conn->prepare("INSERT INTO permissions (name, guard_name, created_at, updated_at) VALUES (?, 'web', NOW(), NOW())");
            $stmt->execute([$permission_name]);
            echo "Created permission: $permission_name\n";
        } else {
            echo "Permission already exists: $permission_name\n";
        }
    }
    
    // Get all permission IDs
    $stmt = $conn->prepare("SELECT id, name FROM permissions WHERE name IN (?, ?, ?)");
    $stmt->execute($permissions_to_create);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nFound permissions:\n";
    foreach ($permissions as $perm) {
        echo "- {$perm['name']} (ID: {$perm['id']})\n";
    }
    
    // Get role IDs
    $stmt = $conn->prepare("SELECT id, name FROM roles WHERE name IN ('admin', 'company')");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nFound roles:\n";
    foreach ($roles as $role) {
        echo "- {$role['name']} (ID: {$role['id']})\n";
    }
    
    // Attach permissions to roles
    foreach ($roles as $role) {
        foreach ($permissions as $permission) {
            // Check if already exists
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM permission_role WHERE role_id = ? AND permission_id = ?");
            $stmt->execute([$role['id'], $permission['id']]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($exists == 0) {
                $stmt = $conn->prepare("INSERT INTO permission_role (role_id, permission_id) VALUES (?, ?)");
                $stmt->execute([$role['id'], $permission['id']]);
                echo "Attached permission '{$permission['name']}' to role '{$role['name']}'\n";
            } else {
                echo "Permission '{$permission['name']}' already attached to role '{$role['name']}'\n";
            }
        }
    }
    
    echo "\nPermission attachment completed successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn = null;
