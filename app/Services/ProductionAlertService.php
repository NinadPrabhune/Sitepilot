<?php

namespace App\Services;

use App\Domain\Machinery\Services\FinancialIntegrityWatchdog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\SystemAlertEmail;

/**
 * Production Alert Service
 * Sends alerts for critical system issues
 */
class ProductionAlertService
{
    private $watchdog;
    private $alertRecipients;
    
    public function __construct()
    {
        $this->watchdog = new FinancialIntegrityWatchdog();
        $this->alertRecipients = config('alerts.recipients', [
            'admin@company.com',
            'accounts@company.com',
        ]);
    }
    
    /**
     * Run all alert checks
     */
    public function runAlertChecks(): array
    {
        $alerts = [];
        
        // Financial integrity alerts
        $alerts = array_merge($alerts, $this->checkFinancialIntegrityAlerts());
        
        // System performance alerts
        $alerts = array_merge($alerts, $this->checkPerformanceAlerts());
        
        // Security alerts
        $alerts = array_merge($alerts, $this->checkSecurityAlerts());
        
        // Backup alerts
        $alerts = array_merge($alerts, $this->checkBackupAlerts());
        
        // Send alerts if any
        if (!empty($alerts)) {
            $this->sendAlerts($alerts);
        }
        
        return $alerts;
    }
    
    /**
     * Check for financial integrity alerts
     */
    private function checkFinancialIntegrityAlerts(): array
    {
        $alerts = [];
        $results = $this->watchdog->runAllChecks();
        
        // DPR vs Ledger mismatch alert
        if ($results['dpr_vs_ledger_mismatch']['count'] > 0) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'DPR vs Ledger Amount Mismatch Detected',
                'message' => "Found {$results['dpr_vs_ledger_mismatch']['count']} DPR records with mismatched ledger amounts.",
                'details' => $results['dpr_vs_ledger_mismatch']['issues'],
                'action_required' => 'immediate',
            ];
        }
        
        // Duplicate ledger entries alert
        if ($results['duplicate_ledger_entries']['count'] > 0) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Duplicate Ledger Entries Detected',
                'message' => "Found {$results['duplicate_ledger_entries']['count']} sets of duplicate ledger entries.",
                'details' => $results['duplicate_ledger_entries']['issues'],
                'action_required' => 'immediate',
            ];
        }
        
        // Negative balances alert
        if ($results['negative_balances']['count'] > 0) {
            $alerts[] = [
                'type' => 'high',
                'title' => 'Negative Machinery Balances Detected',
                'message' => "Found {$results['negative_balances']['count']} machinery accounts with negative balances.",
                'details' => $results['negative_balances']['issues'],
                'action_required' => 'urgent',
            ];
        }
        
        // Calculation hash integrity alert
        if ($results['calculation_hash_integrity']['count'] > 0) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Calculation Hash Integrity Issues',
                'message' => "Found {$results['calculation_hash_integrity']['count']} DPR records with invalid calculation hashes.",
                'details' => $results['calculation_hash_integrity']['issues'],
                'action_required' => 'immediate',
            ];
        }
        
        // Period lock violations alert
        if ($results['period_lock_violations']['count'] > 0) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Financial Period Lock Violations',
                'message' => "Found {$results['period_lock_violations']['count']} DPR records in locked periods.",
                'details' => $results['period_lock_violations']['issues'],
                'action_required' => 'immediate',
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Check for performance alerts
     */
    private function checkPerformanceAlerts(): array
    {
        $alerts = [];
        
        // Check for slow queries (if logging is enabled)
        $slowQueries = $this->getSlowQueryCount();
        if ($slowQueries > 10) {
            $alerts[] = [
                'type' => 'medium',
                'title' => 'High Number of Slow Queries',
                'message' => "Detected {$slowQueries} slow queries in the last hour.",
                'details' => ['slow_query_count' => $slowQueries],
                'action_required' => 'review',
            ];
        }
        
        // Check for high memory usage
        $memoryUsage = $this->getMemoryUsage();
        if ($memoryUsage > 85) {
            $alerts[] = [
                'type' => 'high',
                'title' => 'High Memory Usage',
                'message' => "System memory usage is at {$memoryUsage}%.",
                'details' => ['memory_usage_percent' => $memoryUsage],
                'action_required' => 'urgent',
            ];
        }
        
        // Check for disk space
        $diskUsage = $this->getDiskUsage();
        if ($diskUsage > 90) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Low Disk Space',
                'message' => "Disk usage is at {$diskUsage}%.",
                'details' => ['disk_usage_percent' => $diskUsage],
                'action_required' => 'immediate',
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Check for security alerts
     */
    private function checkSecurityAlerts(): array
    {
        $alerts = [];
        
        // Check for failed login attempts
        $failedLogins = $this->getFailedLoginCount();
        if ($failedLogins > 50) {
            $alerts[] = [
                'type' => 'high',
                'title' => 'High Number of Failed Login Attempts',
                'message' => "Detected {$failedLogins} failed login attempts in the last hour.",
                'details' => ['failed_login_count' => $failedLogins],
                'action_required' => 'urgent',
            ];
        }
        
        // Check for unauthorized access attempts
        $unauthorizedAttempts = $this->getUnauthorizedAccessCount();
        if ($unauthorizedAttempts > 10) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Unauthorized Access Attempts',
                'message' => "Detected {$unauthorizedAttempts} unauthorized access attempts.",
                'details' => ['unauthorized_attempts' => $unauthorizedAttempts],
                'action_required' => 'immediate',
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Check for backup alerts
     */
    private function checkBackupAlerts(): array
    {
        $alerts = [];
        
        // Check if recent backup exists
        $lastBackup = $this->getLastBackupTime();
        if (!$lastBackup || $lastBackup->diffInHours(now()) > 26) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Missing or Outdated Backup',
                'message' => "No successful backup found in the last 26 hours.",
                'details' => [
                    'last_backup' => $lastBackup?->toISOString(),
                    'hours_since_backup' => $lastBackup ? $lastBackup->diffInHours(now()) : null,
                ],
                'action_required' => 'immediate',
            ];
        }
        
        // Check backup file integrity
        $backupIntegrity = $this->checkBackupIntegrity();
        if (!$backupIntegrity) {
            $alerts[] = [
                'type' => 'high',
                'title' => 'Backup Integrity Issues',
                'message' => "Recent backup files show integrity issues.",
                'details' => ['backup_integrity_failed' => true],
                'action_required' => 'urgent',
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Send alerts via email and/or Slack
     */
    private function sendAlerts(array $alerts): void
    {
        foreach ($alerts as $alert) {
            // Log alert
            $this->logAlert($alert);
            
            // Send email alert
            $this->sendEmailAlert($alert);
            
            // Send Slack alert (if configured)
            if (config('alerts.slack.enabled')) {
                $this->sendSlackAlert($alert);
            }
        }
    }
    
    /**
     * Log alert to system log
     */
    private function logAlert(array $alert): void
    {
        Log::warning('Production alert triggered', [
            'type' => $alert['type'],
            'title' => $alert['title'],
            'message' => $alert['message'],
            'details' => $alert['details'] ?? [],
            'action_required' => $alert['action_required'] ?? 'none',
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Send email alert
     */
    private function sendEmailAlert(array $alert): void
    {
        try {
            Mail::to($this->alertRecipients)->send(new SystemAlertEmail($alert));
        } catch (\Exception $e) {
            Log::error('Failed to send alert email', [
                'error' => $e->getMessage(),
                'alert' => $alert,
            ]);
        }
    }
    
    /**
     * Send Slack alert
     */
    private function sendSlackAlert(array $alert): void
    {
        // Implementation depends on Slack integration
        // This is a placeholder for Slack webhook integration
        Log::info('Slack alert would be sent', [
            'alert' => $alert,
        ]);
    }
    
    // Helper methods for system metrics
    private function getSlowQueryCount(): int
    {
        // Implementation depends on slow query logging
        return 0; // Placeholder
    }
    
    private function getMemoryUsage(): float
    {
        $memoryUsage = memory_get_usage(true);
        $totalMemory = 1024 * 1024 * 1024; // 1GB assumption
        
        return ($memoryUsage / $totalMemory) * 100;
    }
    
    private function getDiskUsage(): float
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        
        return (($totalSpace - $freeSpace) / $totalSpace) * 100;
    }
    
    private function getFailedLoginCount(): int
    {
        // Implementation depends on login logging
        return 0; // Placeholder
    }
    
    private function getUnauthorizedAccessCount(): int
    {
        // Implementation depends on access logging
        return 0; // Placeholder
    }
    
    private function getLastBackupTime(): ?\Carbon\Carbon
    {
        $backupDir = storage_path('app/backups');
        $files = glob("{$backupDir}/backup_*.sql.gz");
        
        if (empty($files)) {
            return null;
        }
        
        $latestFile = max($files);
        $timestamp = filemtime($latestFile);
        
        return \Carbon\Carbon::createFromTimestamp($timestamp);
    }
    
    private function checkBackupIntegrity(): bool
    {
        $backupDir = storage_path('app/backups');
        $files = glob("{$backupDir}/backup_*.sql.gz");
        
        if (empty($files)) {
            return false;
        }
        
        $latestFile = max($files);
        
        // Test gzip integrity
        $exitCode = 0;
        exec("gzip -t {$latestFile}", $output, $exitCode);
        
        return $exitCode === 0;
    }
}
