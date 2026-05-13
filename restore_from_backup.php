<?php

// Database restore script
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'sitepilot_local';
$backupFile = 'backup_before_po_locked_advance_20260412_235814.sql';

try {
    echo "Starting database restore from backup...\n";
    echo "Backup file: $backupFile\n";
    echo "Target database: $database\n\n";
    
    // Check if backup file exists
    if (!file_exists($backupFile)) {
        throw new Exception("Backup file not found: $backupFile");
    }
    
    $fileSize = filesize($backupFile);
    echo "Backup file size: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
    
    // Create connection
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL server\n";
    
    // Drop and recreate database
    echo "Dropping existing database...\n";
    $conn->exec("DROP DATABASE IF EXISTS `$database`");
    
    echo "Creating new database...\n";
    $conn->exec("CREATE DATABASE `$database`");
    $conn->exec("USE `$database`");
    
    echo "Database reset complete\n";
    
    // Read and execute backup file
    echo "Restoring from backup file...\n";
    
    $sql = file_get_contents($backupFile);
    if ($sql === false) {
        throw new Exception("Failed to read backup file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $conn->exec($statement);
            $executed++;
            
            if ($executed % 100 == 0) {
                echo "Executed $executed statements...\n";
            }
        } catch (PDOException $e) {
            $errors++;
            echo "Error executing statement: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }
    
    echo "\nRestore completed!\n";
    echo "Statements executed: $executed\n";
    echo "Errors encountered: $errors\n";
    
    // Verify restore
    echo "\nVerifying restore...\n";
    $conn->exec("USE `$database`");
    
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables restored: " . count($tables) . "\n";
    
    $userCount = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Users restored: $userCount\n";
    
    if ($userCount > 0) {
        echo "✅ SUCCESS: Database restored successfully!\n";
        
        // Log the restore
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => 'database_restore',
            'backup_file' => $backupFile,
            'tables_restored' => count($tables),
            'users_restored' => $userCount,
            'statements_executed' => $executed,
            'errors' => $errors
        ];
        
        file_put_contents('database_restore_log.json', json_encode($logEntry, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        
    } else {
        echo "❌ WARNING: Users table still empty after restore\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

$conn = null;
echo "\nRestore process completed.\n";
