<?php

/**
 * Database Conversion Script Generator
 * 
 * This script generates SQL statements to convert all tables in the MySQL database
 * to use InnoDB engine, utf8mb4 character set, and utf8mb4_unicode_ci collation.
 * 
 * Usage: php generate_database_conversion.php
 * Output: database_conversion.sql
 */

// Load Laravel environment
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get database connection details
$dbHost = env('DB_HOST', '127.0.0.1');
$dbPort = env('DB_PORT', '3306');
$dbName = env('DB_DATABASE', 'laravel');
$dbUser = env('DB_USERNAME', 'root');
$dbPass = env('DB_PASSWORD', '');

echo "Connecting to database: {$dbName} at {$dbHost}:{$dbPort}\n";

try {
    // Connect to MySQL
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Connected successfully!\n";
    
    // Get all tables in the database
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "No tables found in database '{$dbName}'.\n";
        exit(1);
    }
    
    echo "Found " . count($tables) . " tables.\n";
    
    // Generate SQL script
    $sql = "-- ============================================\n";
    $sql .= "-- Database Conversion Script\n";
    $sql .= "-- Database: {$dbName}\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- ============================================\n\n";
    
    $sql .= "-- Disable foreign key checks\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    $sql .= "-- ============================================\n";
    $sql .= "-- Convert all tables to InnoDB with utf8mb4\n";
    $sql .= "-- ============================================\n\n";
    
    foreach ($tables as $table) {
        $sql .= "-- Convert table: `{$table}`\n";
        $sql .= "ALTER TABLE `{$table}` \n";
        $sql .= "    ENGINE=InnoDB,\n";
        $sql .= "    DEFAULT CHARACTER SET utf8mb4,\n";
        $sql .= "    COLLATE utf8mb4_unicode_ci;\n\n";
    }
    
    $sql .= "-- ============================================\n";
    $sql .= "-- Re-enable foreign key checks\n";
    $sql .= "-- ============================================\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";
    
    $sql .= "-- Conversion complete!\n";
    
    // Write to file
    $outputFile = __DIR__ . '/database_conversion.sql';
    file_put_contents($outputFile, $sql);
    
    echo "SQL script generated successfully: {$outputFile}\n";
    echo "Total tables to convert: " . count($tables) . "\n";
    
    // Execute the SQL script
    echo "\nExecuting conversion script...\n";
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "Foreign key checks disabled.\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($tables as $table) {
        $alterSql = "ALTER TABLE `{$table}` ENGINE=InnoDB, DEFAULT CHARACTER SET utf8mb4, COLLATE utf8mb4_unicode_ci";
        
        try {
            $pdo->exec($alterSql);
            echo "✓ Converted: `{$table}`\n";
            $successCount++;
        } catch (PDOException $e) {
            echo "✗ Failed: `{$table}` - " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Foreign key checks re-enabled.\n";
    
    echo "\nConversion complete!\n";
    echo "Successful conversions: {$successCount}\n";
    echo "Failed conversions: {$errorCount}\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
