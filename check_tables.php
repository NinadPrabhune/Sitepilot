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
    
    // Show all tables and their counts
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Table data counts:\n";
    echo "==================\n";
    
    $totalTables = 0;
    $emptyTables = 0;
    $tablesWithData = 0;
    
    foreach ($tables as $table) {
        $totalTables++;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM `$table`");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $emptyTables++;
            echo "$table: 0 rows\n";
        } else {
            $tablesWithData++;
            echo "$table: $count rows\n";
        }
    }
    
    echo "\nSummary:\n";
    echo "========\n";
    echo "Total tables: $totalTables\n";
    echo "Tables with data: $tablesWithData\n";
    echo "Empty tables: $emptyTables\n";
    
    // Specifically check users table
    echo "\nUsers table details:\n";
    echo "====================\n";
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $userCount = $stmt->fetchColumn();
    echo "User count: $userCount\n";
    
    if ($userCount > 0) {
        $stmt = $conn->prepare("SELECT id, name, email, created_at FROM users LIMIT 5");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample users:\n";
        foreach ($users as $user) {
            echo "- ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}, Created: {$user['created_at']}\n";
        }
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn = null;
