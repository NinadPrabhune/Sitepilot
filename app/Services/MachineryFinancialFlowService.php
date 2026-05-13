<?php

namespace App\Services;

use App\Models\Machinery;
use App\Models\DailyProgressReport;
use App\Models\MachineryLedger;
use App\Domain\Machinery\Services\MachineryLedgerService;
use Illuminate\Support\Facades\Log;

/**
 * Machinery Financial Flow Service
 * Handles the separation of owned vs rental machinery financial treatment
 */
class MachineryFinancialFlowService
{
    /**
     * Process DPR with proper financial flow based on machinery ownership
     */
    public static function processDprFinancials(DailyProgressReport $dpr): array
    {
        $machinery = $dpr->machinery;
        $financialFlow = self::determineFinancialFlow($machinery->owned_by);
        
        switch ($financialFlow) {
            case 'owned':
                return self::processOwnedMachineryDpr($dpr, $machinery);
                
            case 'rental':
                return self::processRentalMachineryDpr($dpr, $machinery);
                
            default:
                throw new \Exception("Unknown machinery ownership type: {$machinery->owned_by}");
        }
    }
    
    /**
     * Determine financial flow based on machinery ownership
     */
    private static function determineFinancialFlow(string $ownedBy): string
    {
        return match($ownedBy) {
            'owned' => 'owned',
            'rental' => 'rental',
            default => 'unknown'
        };
    }
    
    /**
     * Process owned machinery DPR (internal cost allocation)
     */
    private static function processOwnedMachineryDpr(DailyProgressReport $dpr, Machinery $machinery): array
    {
        // 🔴 CRITICAL: Owned machinery creates INTERNAL COST ledger only
        $ledgerEntry = MachineryLedgerService::createCredit([
            'machinery_id' => $machinery->id,
            'amount' => $dpr->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'dpr_id' => $dpr->id,
            'entry_type' => 'reading',
            'description' => "Internal Cost - {$machinery->name} - {$dpr->date}",
            'date' => $dpr->date,
            'idempotency_key' => "dpr_{$dpr->id}_internal_cost",
        ]);
        
        Log::info('Owned machinery DPR processed as internal cost', [
            'dpr_id' => $dpr->id,
            'machinery_id' => $machinery->id,
            'machinery_name' => $machinery->name,
            'calculated_amount' => $dpr->calculated_amount,
            'ledger_type' => $ledgerEntry->ledger_type,
            'financial_flow' => 'internal_cost_allocation',
        ]);
        
        return [
            'financial_flow' => 'owned',
            'ledger_type' => 'internal_cost',
            'payment_required' => false,
            'ledger_entry' => $ledgerEntry,
            'cost_components' => [
                'machine_cost' => $dpr->calculated_amount,
                'diesel_cost' => 0, // Will be processed separately
                'operator_cost' => 0, // Will be processed separately
            ]
        ];
    }
    
    /**
     * Process rental machinery DPR (payable with payment request)
     */
    private static function processRentalMachineryDpr(DailyProgressReport $dpr, Machinery $machinery): array
    {
        // 🔴 CRITICAL: Rental machinery creates PAYABLE ledger with payment request
        $ledgerEntry = MachineryLedgerService::createCredit([
            'machinery_id' => $machinery->id,
            'amount' => $dpr->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'dpr_id' => $dpr->id,
            'entry_type' => 'reading',
            'description' => "Rental Charge - {$machinery->name} - {$dpr->date}",
            'date' => $dpr->date,
            'idempotency_key' => "dpr_{$dpr->id}_rental_charge",
        ]);
        
        Log::info('Rental machinery DPR processed as payable', [
            'dpr_id' => $dpr->id,
            'machinery_id' => $machinery->id,
            'machinery_name' => $machinery->name,
            'calculated_amount' => $dpr->calculated_amount,
            'ledger_type' => $ledgerEntry->ledger_type,
            'financial_flow' => 'payable_with_payment_request',
        ]);
        
        return [
            'financial_flow' => 'rental',
            'ledger_type' => 'payable',
            'payment_required' => true,
            'ledger_entry' => $ledgerEntry,
            'cost_components' => [
                'machine_cost' => $dpr->calculated_amount,
                'diesel_cost' => 0, // May be included or separate based on contract
                'operator_cost' => 0, // May be included or separate based on contract
            ]
        ];
    }
    
    /**
     * Check if payment request is allowed for machinery
     */
    public static function isPaymentRequestAllowed(Machinery $machinery): bool
    {
        return $machinery->owned_by === 'rental';
    }
    
    /**
     * Get financial treatment summary for machinery
     */
    public static function getFinancialTreatment(Machinery $machinery): array
    {
        return [
            'machinery_id' => $machinery->id,
            'machinery_name' => $machinery->name,
            'owned_by' => $machinery->owned_by,
            'financial_flow' => self::determineFinancialFlow($machinery->owned_by),
            'ledger_type' => $machinery->owned_by === 'owned' ? 'internal_cost' : 'payable',
            'payment_required' => $machinery->owned_by === 'rental',
            'cost_tracking' => $machinery->owned_by === 'owned' ? 'internal' : 'external',
            'supplier_involved' => $machinery->owned_by === 'rental',
            'minimum_billing_applies' => $machinery->owned_by === 'rental' && $machinery->minimum_billing_hours > 0,
        ];
    }
    
    /**
     * Validate financial flow consistency
     */
    public static function validateFinancialFlow(DailyProgressReport $dpr): array
    {
        $issues = [];
        
        // Check ledger type matches machinery ownership
        $ledger = MachineryLedger::where('dpr_id', $dpr->id)->first();
        if ($ledger) {
            $expectedLedgerType = $dpr->machinery->owned_by === 'owned' ? 'internal_cost' : 'payable';
            if ($ledger->ledger_type !== $expectedLedgerType) {
                $issues[] = [
                    'type' => 'ledger_type_mismatch',
                    'message' => "Ledger type '{$ledger->ledger_type}' does not match expected '{$expectedLedgerType}' for {$dpr->machinery->owned_by} machinery",
                    'dpr_id' => $dpr->id,
                    'machinery_id' => $dpr->machinery_id,
                ];
            }
        }
        
        // Check payment requests only for rental machinery
        if ($dpr->machinery->owned_by === 'owned' && $dpr->hasPaymentRequest()) {
            $issues[] = [
                'type' => 'payment_request_for_owned',
                'message' => 'Payment request found for owned machinery (should not exist)',
                'dpr_id' => $dpr->id,
                'machinery_id' => $dpr->machinery_id,
            ];
        }
        
        return $issues;
    }
}
