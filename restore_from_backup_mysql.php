<?php

// Database restore script using MySQL command line
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'sitepilot_local';
$backupFile = 'backup_before_po_locked_advance_20260412_235814.sql';

try {
    echo "Starting database restore using MySQL command line...\n";
    echo "Backup file: $backupFile\n";
    echo "Target database: $database\n\n";
    
    // Check if backup file exists
    if (!file_exists($backupFile)) {
        throw new Exception("Backup file not found: $backupFile");
    }
    
    $fileSize = filesize($backupFile);
    echo "Backup file size: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
    
    // Build MySQL command
    $mysqlCmd = "mysql -h $host -u $username";
    if (!empty($password)) {
        $mysqlCmd .= " -p$password";
    }
    $mysqlCmd .= " $database < $backupFile";
    
    echo "Executing MySQL restore command...\n";
    echo "Command: mysql -h $host -u $username -p[password] $database < $backupFile\n\n";
    
    // Execute restore
    $output = [];
    $returnCode = 0;
    
    // Use exec to run the command
    exec($mysqlCmd . ' 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✅ SUCCESS: Database restore completed!\n";
        
        // Verify restore
        echo "\nVerifying restore...\n";
        
        // Create connection to verify
        $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables restored: " . count($tables) . "\n";
        
        $userCount = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "Users restored: $userCount\n";
        
        $workspaceCount = $conn->query("SELECT COUNT(*) FROM work_spaces")->fetchColumn();
        echo "Workspaces restored: $workspaceCount\n";
        
        if ($userCount > 0) {
            echo "✅ SUCCESS: Users data restored successfully!\n";
            
            // Log the restore
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => 'database_restore_success',
                'backup_file' => $backupFile,
                'tables_restored' => count($tables),
                'users_restored' => $userCount,
                'workspaces_restored' => $workspaceCount,
                'return_code' => $returnCode
            ];
            
            file_put_contents('database_restore_log.json', json_encode($logEntry, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            
        } else {
            echo "❌ WARNING: Users table still empty after restore\n";
        }
        
        $conn = null;
        
    } else {
        echo "❌ ERROR: Restore failed with return code: $returnCode\n";
        echo "Error output:\n";
        echo implode("\n", $output) . "\n";
        
        // Log the failure
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => 'database_restore_failed',
            'backup_file' => $backupFile,
            'return_code' => $returnCode,
            'error_output' => $output
        ];
        
        file_put_contents('database_restore_log.json', json_encode($logEntry, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nRestore process completed.\n";
