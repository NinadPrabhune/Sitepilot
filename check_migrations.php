<?php

// Connect to database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'sitepilot_local';

try {
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database: $database\n\n";
    
    // Check recent migrations
    echo "Recent migrations (last 10):\n";
    echo "=============================\n";
    $stmt = $conn->prepare("SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 10");
    $stmt->execute();
    $migrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($migrations as $migration) {
        echo "- {$migration['migration']} (Batch: {$migration['batch']})\n";
    }
    
    // Check for seeder-related migrations
    echo "\nSeeder-related migrations:\n";
    echo "==========================\n";
    $stmt = $conn->prepare("SELECT migration, batch FROM migrations WHERE migration LIKE '%seeder%' ORDER BY id DESC");
    $stmt->execute();
    $seeders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($seeders) > 0) {
        foreach ($seeders as $seeder) {
            echo "- {$seeder['migration']} (Batch: {$seeder['batch']})\n";
        }
    } else {
        echo "No seeder migrations found.\n";
    }
    
    // Check total migration count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM migrations");
    $stmt->execute();
    $total = $stmt->fetchColumn();
    echo "\nTotal migrations: $total\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn = null;
