<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MachineryBillingProtectionService;
use App\Services\LedgerBalancingValidationService;
use App\Services\DieselStockReconciliationService;
use App\Services\MeterReadingValidationService;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;
use Illuminate\Support\Facades\Log;

class CheckMachineryBillingIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'machinery:check-billing-integrity {--workspace-id=} {--site-id=} {--detailed}';
    
    /**
     * The console command description.
     */
    protected $description = 'Check machinery billing system integrity and generate alerts';
    
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting machinery billing integrity check...');
        
        $workspaceId = $this->option('workspace-id');
        $siteId = $this->option('site-id');
        $detailed = $this->option('detailed');
        
        $issues = [];
        $warnings = [];
        
        try {
            // Check 1: Duplicate billing prevention
            $this->line('Checking duplicate billing prevention...');
            $duplicateRisks = MachineryBillingProtectionService::detectDuplicateBillingRisks();
            
            if (!empty($duplicateRisks)) {
                $this->error('Found ' . count($duplicateRisks) . ' duplicate billing risks');
                $issues = array_merge($issues, $duplicateRisks);
                
                if ($detailed) {
                    foreach ($duplicateRisks as $risk) {
                        $this->line("  - {$risk['type']}: {$risk['description']}");
                    }
                }
            } else {
                $this->info('✓ No duplicate billing risks detected');
            }
            
            // Check 2: Ledger balance integrity
            $this->line('Checking ledger balance integrity...');
            
            if ($workspaceId) {
                $ledgerValidation = LedgerBalancingValidationService::validateWorkspaceLedgerIntegrity($workspaceId);
                
                if (!$ledgerValidation['valid']) {
                    $this->error('Found ledger integrity issues');
                    $issues = array_merge($issues, $ledgerValidation['issues']);
                    
                    if ($detailed) {
                        foreach ($ledgerValidation['issues'] as $issue) {
                            $this->line("  - " . json_encode($issue));
                        }
                    }
                } else {
                    $this->info('✓ Ledger integrity validated');
                }
            } else {
                $this->warn('Skipping ledger validation - workspace ID required');
            }
            
            // Check 3: Diesel stock reconciliation
            $this->line('Checking diesel stock reconciliation...');
            
            if ($workspaceId) {
                $from = now()->subMonth();
                $to = now();
                
                $dieselReconciliation = DieselStockReconciliationService::getReconciliationReport($workspaceId, $siteId, $from, $to);
                
                if (!$dieselReconciliation['integrity_check']['stock_balanced'] || !$dieselReconciliation['integrity_check']['recovery_balanced']) {
                    $this->error('Found diesel reconciliation issues');
                    $issues = array_merge($issues, $dieselReconciliation['integrity_check']['issues']);
                    
                    if ($detailed) {
                        foreach ($dieselReconciliation['integrity_check']['issues'] as $issue) {
                            $this->line("  - {$issue['type']}: {$issue['description']}");
                        }
                    }
                } else {
                    $this->info('✓ Diesel stock reconciliation balanced');
                }
                
                // Check for diesel stock alerts
                $dieselAlerts = DieselStockReconciliationService::getStockAlerts($workspaceId, $siteId);
                
                if (!empty($dieselAlerts)) {
                    $this->warn('Found ' . count($dieselAlerts) . ' diesel stock alerts');
                    $warnings = array_merge($warnings, $dieselAlerts);
                    
                    if ($detailed) {
                        foreach ($dieselAlerts as $alert) {
                            $this->line("  - {$alert['type']}: {$alert['message']}");
                        }
                    }
                }
            } else {
                $this->warn('Skipping diesel reconciliation - workspace ID required');
            }
            
            // Check 4: Meter reading anomalies
            $this->line('Checking meter reading anomalies...');
            
            if ($workspaceId) {
                $machineryIds = Machinery::where('workspace_id', $workspaceId)
                    ->when($siteId, fn($query) => $query->where('site_id', $siteId))
                    ->pluck('id');
                
                $totalAnomalies = 0;
                foreach ($machineryIds as $machineryId) {
                    $machinery = Machinery::find($machineryId);
                    $from = now()->subDays(30);
                    $to = now();
                    
                    $anomalies = MeterReadingValidationService::checkForAnomalies($machinery, $from, $to);
                    $totalAnomalies += count($anomalies);
                    
                    if (!empty($anomalies) && $detailed) {
                        $this->line("  Machinery {$machineryId} ({$machinery->name}): " . count($anomalies) . ' anomalies');
                        foreach ($anomalies as $anomaly) {
                            $this->line("    - {$anomaly['severity']}: {$anomaly['message']}");
                        }
                    }
                }
                
                if ($totalAnomalies > 0) {
                    $this->warn("Found {$totalAnomalies} meter reading anomalies in last 30 days");
                } else {
                    $this->info('✓ No significant meter reading anomalies detected');
                }
            } else {
                $this->warn('Skipping meter reading anomaly check - workspace ID required');
            }
            
            // Check 5: Payment request anomalies
            $this->line('Checking payment request anomalies...');
            
            $query = MachineryPaymentRequest::whereIn('status', ['draft', 'submitted', 'approved']);
            if ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            }
            
            $oldPaymentRequests = $query->where('created_at', '<', now()->subDays(7))->count();
            
            if ($oldPaymentRequests > 0) {
                $this->warn("Found {$oldPaymentRequests} payment requests older than 7 days in non-final status");
                $warnings[] = [
                    'type' => 'old_payment_requests',
                    'count' => $oldPaymentRequests,
                    'description' => "Payment requests pending for more than 7 days"
                ];
            } else {
                $this->info('✓ No old payment requests found');
            }
            
            // Check 6: Negative balances
            $this->line('Checking for negative balances...');
            
            $negativeBalances = MachineryLedger::selectRaw('machinery_id, SUM(amount) as balance')
                ->where('is_reversal', false)
                ->when($workspaceId, fn($query) => $query->where('workspace_id', $workspaceId))
                ->groupBy('machinery_id')
                ->having('balance', '<', -100) // Allow reasonable negative
                ->get();
            
            if ($negativeBalances->count() > 0) {
                $this->error('Found ' . $negativeBalances->count() . ' machinery with excessive negative balances');
                $issues[] = [
                    'type' => 'negative_balances',
                    'count' => $negativeBalances->count(),
                    'description' => "Machinery with excessive negative balances",
                    'machinery' => $negativeBalances->toArray()
                ];
                
                if ($detailed) {
                    foreach ($negativeBalances as $balance) {
                        $this->line("  - Machinery {$balance->machinery_id}: Balance {$balance->balance}");
                    }
                }
            } else {
                $this->info('✓ No excessive negative balances found');
            }
            
            // Generate summary
            $this->line("\n" . str_repeat('=', 50));
            $this->info('INTEGRITY CHECK SUMMARY');
            $this->line(str_repeat('=', 50));
            
            $this->line('Issues found: ' . count($issues));
            $this->line('Warnings found: ' . count($warnings));
            
            if (!empty($issues)) {
                $this->error('CRITICAL ISSUES REQUIRE ATTENTION');
                
                // Log critical issues
                Log::critical('Machinery billing integrity check found critical issues', [
                    'workspace_id' => $workspaceId,
                    'site_id' => $siteId,
                    'issues' => $issues,
                    'warnings' => $warnings
                ]);
                
                return 1; // Error exit code
            } elseif (!empty($warnings)) {
                $this->warn('WARNINGS DETECTED - MONITOR RECOMMENDED');
                
                // Log warnings
                Log::warning('Machinery billing integrity check found warnings', [
                    'workspace_id' => $workspaceId,
                    'site_id' => $siteId,
                    'warnings' => $warnings
                ]);
                
                return 0; // Success but with warnings
            } else {
                $this->info('✓ ALL CHECKS PASSED - SYSTEM HEALTHY');
                
                Log::info('Machinery billing integrity check completed successfully', [
                    'workspace_id' => $workspaceId,
                    'site_id' => $siteId
                ]);
                
                return 0; // Success
            }
            
        } catch (\Exception $e) {
            $this->error('Integrity check failed: ' . $e->getMessage());
            Log::error('Machinery billing integrity check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1; // Error exit code
        }
    }
}
