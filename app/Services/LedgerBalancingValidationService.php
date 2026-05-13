<?php

namespace App\Services;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryLedger;
use Illuminate\Support\Facades\DB;
use Exception;

class LedgerBalancingValidationService
{
    /**
     * Validate ledger balance before posting payment request
     */
    public static function validatePaymentRequestBalance(MachineryPaymentRequest $request): array
    {
        $issues = [];
        
        // Validate calculation formula: gross_amount - diesel_deduction = net_payable
        $calculatedNetPayable = ($request->gross_amount ?? 0) - ($request->diesel_deduction ?? 0);
        $actualNetPayable = $request->net_payable;
        
        if (abs($calculatedNetPayable - $actualNetPayable) > 0.01) {
            $issues[] = [
                'type' => 'calculation_mismatch',
                'severity' => 'critical',
                'description' => "Payment request calculation mismatch",
                'expected_net_payable' => $calculatedNetPayable,
                'actual_net_payable' => $actualNetPayable,
                'difference' => abs($calculatedNetPayable - $actualNetPayable)
            ];
        }
        
        // Validate ledger entries sum matches net payable
        $ledgerSum = self::getLedgerEntriesSum($request);
        
        if (abs($ledgerSum - $actualNetPayable) > 0.01) {
            $issues[] = [
                'type' => 'ledger_balance_mismatch',
                'severity' => 'critical',
                'description' => "Ledger entries sum does not match net payable",
                'ledger_sum' => $ledgerSum,
                'net_payable' => $actualNetPayable,
                'difference' => abs($ledgerSum - $actualNetPayable)
            ];
        }
        
        // Validate double-entry accounting
        $doubleEntryCheck = self::validateDoubleEntryAccounting($request);
        if (!$doubleEntryCheck['valid']) {
            $issues[] = [
                'type' => 'double_entry_violation',
                'severity' => 'high',
                'description' => "Double-entry accounting violation",
                'details' => $doubleEntryCheck['issues']
            ];
        }
        
        // Validate ledger entry integrity
        $integrityCheck = self::validateLedgerIntegrity($request);
        if (!$integrityCheck['valid']) {
            $issues = array_merge($issues, $integrityCheck['issues']);
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'validation_summary' => [
                'calculation_balanced' => abs($calculatedNetPayable - $actualNetPayable) <= 0.01,
                'ledger_balanced' => abs($ledgerSum - $actualNetPayable) <= 0.01,
                'double_entry_valid' => $doubleEntryCheck['valid'],
                'integrity_valid' => $integrityCheck['valid']
            ]
        ];
    }
    
    /**
     * Get sum of ledger entries for payment request
     */
    private static function getLedgerEntriesSum(MachineryPaymentRequest $request): float
    {
        return MachineryLedger::where('payment_request_id', $request->id)
            ->where('is_reversal', false)
            ->sum('amount');
    }
    
    /**
     * Validate double-entry accounting principles
     */
    private static function validateDoubleEntryAccounting(MachineryPaymentRequest $request): array
    {
        $issues = [];
        
        $ledgerEntries = MachineryLedger::where('payment_request_id', $request->id)
            ->where('is_reversal', false)
            ->get();
        
        // Check for proper credit/debit balance
        $totalCredits = $ledgerEntries->where('entry_direction', 'credit')->sum('amount');
        $totalDebits = $ledgerEntries->where('entry_direction', 'debit')->sum('amount');
        
        if (abs($totalCredits - $totalDebits) > 0.01) {
            $issues[] = "Credits ({$totalCredits}) and debits ({$totalDebits}) do not balance";
        }
        
        // Check for required entry types
        $entryTypes = $ledgerEntries->pluck('entry_type')->unique();
        
        // For rental machinery with diesel deduction, we should have both work charges and diesel recovery
        if ($request->diesel_deduction > 0) {
            if (!$entryTypes->contains('work_charges')) {
                $issues[] = "Missing work charges entry type";
            }
            if (!$entryTypes->contains('diesel_recovery')) {
                $issues[] = "Missing diesel recovery entry type";
            }
        }
        
        // Validate entry amounts make sense
        foreach ($ledgerEntries as $entry) {
            if ($entry->amount <= 0) {
                $issues[] = "Ledger entry {$entry->id} has non-positive amount: {$entry->amount}";
            }
            
            // Validate work charges amount matches gross amount
            if ($entry->entry_type === 'work_charges' && $entry->entry_direction === 'credit') {
                if (abs($entry->amount - ($request->gross_amount ?? 0)) > 0.01) {
                    $issues[] = "Work charges amount ({$entry->amount}) does not match gross amount ({$request->gross_amount})";
                }
            }
            
            // Validate diesel recovery amount matches diesel deduction
            if ($entry->entry_type === 'diesel_recovery' && $entry->entry_direction === 'debit') {
                if (abs($entry->amount - ($request->diesel_deduction ?? 0)) > 0.01) {
                    $issues[] = "Diesel recovery amount ({$entry->amount}) does not match diesel deduction ({$request->diesel_deduction})";
                }
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'summary' => [
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
                'entry_count' => $ledgerEntries->count(),
                'entry_types' => $entryTypes->toArray()
            ]
        ];
    }
    
    /**
     * Validate ledger entry integrity
     */
    private static function validateLedgerIntegrity(MachineryPaymentRequest $request): array
    {
        $issues = [];
        
        $ledgerEntries = MachineryLedger::where('payment_request_id', $request->id)
            ->where('is_reversal', false)
            ->get();
        
        foreach ($ledgerEntries as $entry) {
            // Check required fields
            if (!$entry->reference_type || !$entry->reference_id) {
                $issues[] = "Ledger entry {$entry->id} missing reference information";
            }
            
            // Check description
            if (!$entry->description) {
                $issues[] = "Ledger entry {$entry->id} missing description";
            }
            
            // Check date
            if (!$entry->date) {
                $issues[] = "Ledger entry {$entry->id} missing date";
            }
            
            // Check machinery_id matches payment request
            if ($entry->machinery_id !== $request->machinery_id) {
                $issues[] = "Ledger entry {$entry->id} machinery ID ({$entry->machinery_id}) does not match payment request machinery ID ({$request->machinery_id})";
            }
            
            // Check workspace_id matches
            if ($entry->workspace_id !== $request->workspace_id) {
                $issues[] = "Ledger entry {$entry->id} workspace ID ({$entry->workspace_id}) does not match payment request workspace ID ({$request->workspace_id})";
            }
            
            // Validate running balance calculation
            if ($entry->running_balance === null) {
                $issues[] = "Ledger entry {$entry->id} missing running balance";
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }
    
    /**
     * Validate machinery ledger balance integrity
     */
    public static function validateMachineryLedgerBalance(int $machineryId): array
    {
        $issues = [];
        
        // Get all non-reversed ledger entries
        $ledgerEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->where('is_reversal', false)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        
        $runningBalance = 0;
        $previousBalance = null;
        
        foreach ($ledgerEntries as $entry) {
            // Calculate expected running balance
            $runningBalance += $entry->amount;
            
            // Check if stored running balance matches calculated
            if ($entry->running_balance !== null && abs($entry->running_balance - $runningBalance) > 0.01) {
                $issues[] = [
                    'type' => 'running_balance_mismatch',
                    'severity' => 'high',
                    'description' => "Running balance mismatch for ledger entry {$entry->id}",
                    'entry_date' => $entry->date,
                    'stored_balance' => $entry->running_balance,
                    'calculated_balance' => $runningBalance,
                    'difference' => abs($entry->running_balance - $runningBalance)
                ];
            }
            
            // Check for negative balances (if not allowed)
            if ($runningBalance < -1000) { // Allow reasonable negative balances
                $issues[] = [
                    'type' => 'excessive_negative_balance',
                    'severity' => 'medium',
                    'description' => "Excessive negative balance: {$runningBalance}",
                    'entry_date' => $entry->date,
                    'balance' => $runningBalance
                ];
            }
            
            $previousBalance = $entry->running_balance;
        }
        
        // Check final balance
        $finalBalance = $ledgerEntries->last()?->running_balance ?? 0;
        $expectedFinalBalance = $ledgerEntries->sum('amount');
        
        if (abs($finalBalance - $expectedFinalBalance) > 0.01) {
            $issues[] = [
                'type' => 'final_balance_mismatch',
                'severity' => 'high',
                'description' => "Final balance mismatch",
                'final_balance' => $finalBalance,
                'expected_balance' => $expectedFinalBalance,
                'difference' => abs($finalBalance - $expectedFinalBalance)
            ];
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'summary' => [
                'total_entries' => $ledgerEntries->count(),
                'final_balance' => $finalBalance,
                'expected_balance' => $expectedFinalBalance,
                'balance_matches' => abs($finalBalance - $expectedFinalBalance) <= 0.01
            ]
        ];
    }
    
    /**
     * Perform comprehensive ledger validation for workspace
     */
    public static function validateWorkspaceLedgerIntegrity(int $workspaceId): array
    {
        $issues = [];
        
        // Get all machinery in workspace
        $machineryIds = DB::table('machineries')
            ->where('workspace_id', $workspaceId)
            ->pluck('id');
        
        foreach ($machineryIds as $machineryId) {
            $validation = self::validateMachineryLedgerBalance($machineryId);
            
            if (!$validation['valid']) {
                $issues[] = [
                    'machinery_id' => $machineryId,
                    'issues' => $validation['issues']
                ];
            }
        }
        
        // Check for orphaned ledger entries
        $orphanedEntries = DB::table('machinery_ledgers')
            ->where('workspace_id', $workspaceId)
            ->whereNull('reference_type')
            ->orWhereNull('reference_id')
            ->count();
        
        if ($orphanedEntries > 0) {
            $issues[] = [
                'type' => 'orphaned_entries',
                'severity' => 'medium',
                'description' => "Found {$orphanedEntries} orphaned ledger entries in workspace"
            ];
        }
        
        // Check for unreversed payment request deletions
        $unlinkedPaymentRequests = MachineryPaymentRequest::where('workspace_id', $workspaceId)
            ->where('status', 'paid')
            ->whereDoesntHave('ledgerEntries')
            ->count();
        
        if ($unlinkedPaymentRequests > 0) {
            $issues[] = [
                'type' => 'unlinked_payment_requests',
                'severity' => 'high',
                'description' => "Found {$unlinkedPaymentRequests} paid payment requests without ledger entries"
            ];
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'summary' => [
                'machinery_count' => $machineryIds->count(),
                'machinery_with_issues' => count(array_filter($issues, fn($i) => isset($i['machinery_id']))),
                'orphaned_entries' => $orphanedEntries,
                'unlinked_payment_requests' => $unlinkedPaymentRequests
            ]
        ];
    }
    
    /**
     * Recalculate and fix running balances
     */
    public static function recalculateRunningBalances(int $machineryId): array
    {
        $issues = [];
        $fixedCount = 0;
        
        $ledgerEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->where('is_reversal', false)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        
        $runningBalance = 0;
        
        foreach ($ledgerEntries as $entry) {
            $runningBalance += $entry->amount;
            
            if ($entry->running_balance !== $runningBalance) {
                $oldBalance = $entry->running_balance;
                $entry->update(['running_balance' => $runningBalance]);
                $fixedCount++;
                
                $issues[] = [
                    'entry_id' => $entry->id,
                    'date' => $entry->date,
                    'old_balance' => $oldBalance,
                    'new_balance' => $runningBalance,
                    'difference' => $runningBalance - $oldBalance
                ];
            }
        }
        
        return [
            'fixed_count' => $fixedCount,
            'issues' => $issues,
            'final_balance' => $runningBalance
        ];
    }
    
    /**
     * Validate payment request before approval (DB transaction protected)
     */
    public static function validateForApproval(MachineryPaymentRequest $request): void
    {
        $validation = self::validatePaymentRequestBalance($request);
        
        if (!$validation['valid']) {
            $errorMessages = array_map(fn($issue) => $issue['description'], $validation['issues']);
            throw new Exception("Ledger validation failed: " . implode('; ', $errorMessages));
        }
    }
}
