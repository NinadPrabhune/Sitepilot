<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Automated Database Backup Command
 * Daily automated backup with retention policy
 */
class AutomatedBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:automated {--retention=30 : Number of days to retain backups}';

    /**
     * The console command description.
     */
    protected $description = 'Automated daily database backup with retention policy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $retentionDays = $this->option('retention');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupFile = "backup_{$timestamp}.sql";
        $backupPath = storage_path("app/backups/{$backupFile}");
        
        $this->info("Starting automated backup process...");
        $this->info("Backup file: {$backupFile}");
        $this->info("Retention period: {$retentionDays} days");
        
        try {
            // ✅ CREATE BACKUP
            $this->createBackup($backupPath);
            
            // ✅ COMPRESS BACKUP
            $compressedFile = $this->compressBackup($backupPath);
            
            // ✅ VERIFY BACKUP INTEGRITY
            $this->verifyBackupIntegrity($compressedFile);
            
            // ✅ CLEANUP OLD BACKUPS
            $this->cleanupOldBackups($retentionDays);
            
            // ✅ LOG SUCCESS
            $this->logBackupSuccess($backupFile, $compressedFile, $retentionDays);
            
            $this->info("Backup completed successfully: {$compressedFile}");
            
        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());
            $this->logBackupFailure($e);
            
            return 1; // Return error code
        }
        
        return 0; // Success
    }
    
    /**
     * Create database backup
     */
    private function createBackup(string $backupPath): void
    {
        $this->info("Creating database backup...");
        
        // Get database configuration
        $config = config('database.connections.mysql');
        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];
        
        // Build mysqldump command
        $command = sprintf(
            'mysqldump --single-transaction --routines --triggers --add-drop-table --skip-lock-tables -h%s -P%s -u%s -p%s %s > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($backupPath)
        );
        
        // Execute backup
        $exitCode = 0;
        $output = [];
        exec($command, $output, $exitCode);
        
        if ($exitCode !== 0) {
            throw new \Exception("mysqldump failed with exit code: {$exitCode}");
        }
        
        if (!file_exists($backupPath) || filesize($backupPath) === 0) {
            throw new \Exception("Backup file is empty or was not created");
        }
        
        $this->info("Backup created: " . number_format(filesize($backupPath) / 1024 / 1024, 2) . " MB");
    }
    
    /**
     * Compress backup file
     */
    private function compressBackup(string $backupPath): string
    {
        $this->info("Compressing backup file...");
        
        $compressedPath = $backupPath . '.gz';
        
        // Compress using gzip
        $exitCode = 0;
        $output = [];
        exec("gzip -c {$backupPath} > {$compressedPath}", $output, $exitCode);
        
        if ($exitCode !== 0) {
            throw new \Exception("gzip compression failed with exit code: {$exitCode}");
        }
        
        if (!file_exists($compressedPath)) {
            throw new \Exception("Compressed backup file was not created");
        }
        
        // Remove uncompressed file
        unlink($backupPath);
        
        $originalSize = filesize($backupPath . '.tmp'); // Approximate
        $compressedSize = filesize($compressedPath);
        $compressionRatio = ($originalSize - $compressedSize) / $originalSize * 100;
        
        $this->info("Backup compressed: " . number_format($compressedSize / 1024 / 1024, 2) . " MB ({$compressionRatio}% reduction)");
        
        return $compressedPath;
    }
    
    /**
     * Verify backup integrity
     */
    private function verifyBackupIntegrity(string $backupPath): void
    {
        $this->info("Verifying backup integrity...");
        
        // Check if file is readable
        if (!is_readable($backupPath)) {
            throw new \Exception("Backup file is not readable");
        }
        
        // Test gzip integrity
        $exitCode = 0;
        $output = [];
        exec("gzip -t {$backupPath}", $output, $exitCode);
        
        if ($exitCode !== 0) {
            throw new \Exception("Backup file integrity check failed");
        }
        
        // Check file size (should not be zero)
        $fileSize = filesize($backupPath);
        if ($fileSize === 0) {
            throw new \Exception("Backup file is empty");
        }
        
        $this->info("Backup integrity verified");
    }
    
    /**
     * Cleanup old backups
     */
    private function cleanupOldBackups(int $retentionDays): void
    {
        $this->info("Cleaning up backups older than {$retentionDays} days...");
        
        $backupDir = storage_path('app/backups');
        $cutoffDate = now()->subDays($retentionDays);
        $deletedCount = 0;
        
        // Get all backup files
        $files = glob("{$backupDir}/backup_*.sql.gz");
        
        foreach ($files as $file) {
            // Extract date from filename
            if (preg_match('/backup_(\d{4}-\d{2}-\d{2})_/', $file, $matches)) {
                $fileDate = Carbon::createFromFormat('Y-m-d', $matches[1]);
                
                if ($fileDate->lt($cutoffDate)) {
                    if (unlink($file)) {
                        $deletedCount++;
                        $this->line("Deleted old backup: " . basename($file));
                    } else {
                        $this->warn("Failed to delete old backup: " . basename($file));
                    }
                }
            }
        }
        
        $this->info("Cleanup completed. Deleted {$deletedCount} old backup files.");
    }
    
    /**
     * Log backup success
     */
    private function logBackupSuccess(string $backupFile, string $compressedFile, int $retentionDays): void
    {
        Log::info('Automated backup completed successfully', [
            'backup_file' => $backupFile,
            'compressed_file' => basename($compressedFile),
            'file_size' => number_format(filesize($compressedFile) / 1024 / 1024, 2) . ' MB',
            'retention_days' => $retentionDays,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Log backup failure
     */
    private function logBackupFailure(\Exception $e): void
    {
        Log::error('Automated backup failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
