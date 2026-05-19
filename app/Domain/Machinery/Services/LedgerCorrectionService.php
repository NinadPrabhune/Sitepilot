<?php

namespace App\Domain\Machinery\Services;

use App\Domain\Machinery\Services\MachineryLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerCorrectionService
{
    /**
     * Create a reversal + new entry correction for quantity changes
     * 
     * @param int $originalLedgerId Original ledger entry to reverse
     * @param float $newQuantity New quantity for the corrected entry
     * @param float $rate Rate per unit
     * @param array $referenceData Reference information (DPR ID, etc.)
     * @param string $correctionReason Reason for the correction
     * @return array Contains reversal and new ledger entries
     */
    public static function createQuantityCorrection(
        int $originalLedgerId,
        float $newQuantity,
        float $rate,
        array $referenceData,
        string $correctionReason = 'DPR quantity updated'
    ): array {
        // Get current state for validation
        $currentQuantity = self::getCurrentQuantity($referenceData['dpr_id'], $referenceData['material_id']);
        $currentActiveEntry = self::getActiveEntryForMaterial($referenceData['dpr_id'], $referenceData['material_id']);
        
        // State-aware idempotency check based on ledger state
        $correctionHash = self::generateStateAwareHash($originalLedgerId, $referenceData['material_id'], $currentQuantity, $newQuantity);
        
        if (self::correctionExists($correctionHash)) {
            return [
                'reversal_entry' => null,
                'correction_entry' => null,
                'message' => 'Correction already processed (idempotent)'
            ];
        }
        
        // Ledger-state validation - ensure we're working with the correct current state
        if ($currentActiveEntry && abs($currentActiveEntry->amount / self::getRateFromEntry($currentActiveEntry) - $currentQuantity) > 0.01) {
            throw new \RuntimeException('Ledger state inconsistency detected. Active ledger amount does not match current quantity. Please refresh and retry.');
        }
        
        // Stale update detection - ensure we're not applying outdated corrections
        if (abs($currentQuantity - ($referenceData['expected_old_quantity'] ?? $currentQuantity)) > 0.01) {
            throw new \RuntimeException('Stale update detected. Current quantity (' . $currentQuantity . ') differs from expected. Please refresh and retry.');
        }
        
        DB::beginTransaction();
        
        try {
            // Get original ledger entry
            $originalEntry = DB::table('machinery_ledger')->where('id', $originalLedgerId)->first();
            if (!$originalEntry) {
                throw new \RuntimeException("Original ledger entry not found for ID: {$originalLedgerId}");
            }
            
            // Validate original entry has amount
            if (!isset($originalEntry->amount) || is_null($originalEntry->amount)) {
                throw new \RuntimeException("Original ledger entry has no amount data for ID: {$originalLedgerId}");
            }
            
            // Get original rate from ledger entry for consistency
            $materialRates = $originalEntry->metadata['material_rates'] ?? [];
            $rate = $materialRates[$referenceData['material_id']] ?? ($originalEntry->metadata['original_rate'] ?? 1.0);
            
            // Validate rate
            if ($rate <= 0) {
                throw new \RuntimeException("Invalid rate ({$rate}) for material ID: {$referenceData['material_id']}");
            }
            
            // Determine root entry (first entry in correction chain)
            $rootEntryId = $originalEntry->metadata['root_entry_id'] ?? $originalEntry->id;
            
            $originalQuantity = $originalEntry->amount / $rate; // Calculate original quantity
            $quantityDifference = $newQuantity - $originalQuantity;
            
            // If no change in quantity, no correction needed
            if (abs($quantityDifference) < 0.01) {
                DB::rollBack();
                return [
                    'reversal_entry' => null,
                    'correction_entry' => null,
                    'message' => 'No quantity change detected'
                ];
            }
            
            // Step 1: Create reversal entry
            $reversalEntry = self::createReversalEntry($originalEntry, $correctionReason);
            
            // Step 2: Create corrected entry
            $correctedEntry = self::createCorrectedEntry(
                $newQuantity,
                $rate,
                $referenceData,
                $originalEntry,
                $reversalEntry->id,
                $correctionReason
            );
            
            DB::commit();
            
            Log::info('Ledger correction completed', [
                'original_entry_id' => $originalLedgerId,
                'reversal_entry_id' => $reversalEntry->id,
                'corrected_entry_id' => $correctedEntry->id,
                'original_quantity' => $originalQuantity,
                'new_quantity' => $newQuantity,
                'correction_reason' => $correctionReason
            ]);
            
            return [
                'reversal_entry' => $reversalEntry,
                'correction_entry' => $correctedEntry,
                'original_quantity' => $originalQuantity,
                'new_quantity' => $newQuantity,
                'quantity_difference' => $quantityDifference
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ledger correction failed', [
                'original_entry_id' => $originalLedgerId,
                'error' => $e->getMessage(),
                'new_quantity' => $newQuantity,
                'rate' => $rate
            ]);
            throw $e;
        }
    }
    
    /**
     * Create a reversal entry for an existing ledger entry
     */
    private static function createReversalEntry($originalEntry, string $correctionReason): object
    {
        $reversalData = [
            'machinery_id' => $originalEntry->machinery_id,
            'amount' => -$originalEntry->amount, // Negative amount for reversal
            'reference_type' => MachineryLedgerService::REFERENCE_TYPE_REVERSAL,
            'reference_id' => $originalEntry->reference_id,
            'entry_type' => MachineryLedgerService::ENTRY_TYPE_REVERSAL,
            'date' => now()->toDateString(),
            'description' => "REVERSAL: {$originalEntry->description}",
            'metadata' => array_merge($originalEntry->metadata ?? [], [
                'reversal_of_entry_id' => $originalEntry->id,
                'correction_reason' => $correctionReason,
                'corrected_by' => auth()->id(),
                'corrected_at' => now()->toISOString(),
                'correction_hash' => $correctionHash,
                'root_entry_id' => $rootEntryId,
                'is_active' => false
            ]),
            'created_by' => auth()->id(),
            'workspace_id' => $originalEntry->workspace_id
        ];
        
        $reversalId = DB::table('machinery_ledger')->insertGetId($reversalData);
        return DB::table('machinery_ledger')->where('id', $reversalId)->first();
    }
    
    /**
     * Create a corrected entry with the new quantity
     */
    private static function createCorrectedEntry(
        float $newQuantity,
        float $rate,
        array $referenceData,
        object $originalEntry,
        int $reversalEntryId,
        string $correctionReason
    ): object {
        $newAmount = $newQuantity * $rate;
        
        $correctedData = [
            'machinery_id' => $originalEntry->machinery_id,
            'amount' => $newAmount,
            'reference_type' => MachineryLedgerService::REFERENCE_TYPE_CORRECTED,
            'reference_id' => $originalEntry->reference_id,
            'entry_type' => MachineryLedgerService::ENTRY_TYPE_CORRECTED,
            'date' => $originalEntry->date, // Keep original date
            'description' => "CORRECTED: " . str_replace('REVERSAL: ', '', $originalEntry->description),
            'metadata' => array_merge($originalEntry->metadata ?? [], [
                'corrected_from_entry_id' => $originalEntry->id,
                'reversal_entry_id' => $reversalEntryId,
                'correction_reason' => $correctionReason,
                'corrected_by' => auth()->id(),
                'corrected_at' => now()->toISOString(),
                'original_quantity' => $originalEntry->amount / $rate,
                'corrected_quantity' => $newQuantity,
                'original_rate' => $rate,
                'correction_hash' => $correctionHash,
                'root_entry_id' => $rootEntryId,
                'is_active' => true
            ]),
            'created_by' => auth()->id(),
            'workspace_id' => $originalEntry->workspace_id
        ];
        
        $correctedId = DB::table('machinery_ledger')->insertGetId($correctedData);
        $correctedEntry = DB::table('machinery_ledger')->where('id', $correctedId)->first();
        
        // Delay-deactivation: Now safely deactivate old entries after new active entry is created
        self::deactivateCorrectionChain($rootEntryId, $correctedId);
        
        return $correctedEntry;
    }
    
    /**
     * Check if a ledger correction is needed for quantity changes
     */
    public static function needsCorrection(float $oldQuantity, float $newQuantity): bool
    {
        return abs($oldQuantity - $newQuantity) > 0.01;
    }
    
    /**
     * Get correction history for a reference
     */
    public static function getCorrectionHistory(int $referenceId, string $referenceType): array
    {
        return DB::table('machinery_ledger')
            ->where('reference_id', $referenceId)
            ->where('reference_type', 'LIKE', '%CORRECTED%')
            ->orWhere('reference_type', 'LIKE', '%REVERSAL%')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
    
    /**
     * Generate state-aware hash for correction to ensure idempotency with ledger-state validation
     */
    private static function generateStateAwareHash(int $originalLedgerId, int $materialId, float $oldQuantity, float $newQuantity): string
    {
        return hash('sha256', $originalLedgerId . '_' . $materialId . '_' . $oldQuantity . '_' . $newQuantity);
    }
    
    /**
     * Get current quantity for state validation
     */
    private static function getCurrentQuantity(int $dprId, int $materialId): float
    {
        $quantity = DB::table('daily_consumption_details as dcd')
            ->join('daily_consumption_masters as dcm', 'dcm.id', '=', 'dcd.daily_consumption_master_id')
            ->where('dcm.daily_progress_report_id', $dprId)
            ->where('dcd.material_id', $materialId)
            ->value('dcd.quantity');
            
        return (float) ($quantity ?? 0);
    }
    
    /**
     * Get active ledger entry for a specific material in a DPR
     */
    private static function getActiveEntryForMaterial(int $dprId, int $materialId): ?object
    {
        return DB::table('machinery_ledger')
            ->where('reference_id', $dprId)
            ->where('reference_type', MachineryLedgerService::REFERENCE_TYPE_DPR)
            ->where('metadata->is_active', true)
            ->where('metadata->material_id', $materialId)
            ->first();
    }
    
    /**
     * Extract rate from ledger entry metadata
     */
    private static function getRateFromEntry(object $entry): float
    {
        return $entry->metadata['original_rate'] ?? 1.0;
    }
    
    /**
     * Check if correction already exists (idempotency guard)
     */
    private static function correctionExists(string $correctionHash): bool
    {
        return DB::table('machinery_ledger')
            ->where('metadata->correction_hash', $correctionHash)
            ->exists();
    }
    
    /**
     * Deactivate all entries in a correction chain except the newly created one
     */
    private static function deactivateCorrectionChain(int $rootEntryId, int $excludeEntryId = null): void
    {
        $query = DB::table('machinery_ledger')
            ->where(function ($query) use ($rootEntryId) {
                $query->where('id', $rootEntryId)
                      ->orWhere('metadata->root_entry_id', $rootEntryId);
            });
            
        if ($excludeEntryId) {
            $query->where('id', '!=', $excludeEntryId);
        }
        
        $query->update(['metadata->is_active' => false]);
    }
    
    /**
     * Get active entry for a correction chain
     */
    public static function getActiveEntry(int $rootEntryId): ?object
    {
        return DB::table('machinery_ledger')
            ->where(function ($query) use ($rootEntryId) {
                $query->where('id', $rootEntryId)
                      ->orWhere('metadata->root_entry_id', $rootEntryId);
            })
            ->where('metadata->is_active', true)
            ->first();
    }
    
    /**
     * Structured correction reasons for audit clarity
     */
    public const CORRECTION_REASON_QUANTITY_EDIT = 'QUANTITY_EDIT';
    public const CORRECTION_REASON_RATE_CORRECTION = 'RATE_CORRECTION';
    public const CORRECTION_REASON_DATA_ENTRY_ERROR = 'DATA_ENTRY_ERROR';
    public const CORRECTION_REASON_SYSTEM_ADJUSTMENT = 'SYSTEM_ADJUSTMENT';
    
    /**
     * Get structured correction reason from text
     */
    public static function getStructuredReason(string $reason): string
    {
        $reasons = [
            'DPR quantity updated' => self::CORRECTION_REASON_QUANTITY_EDIT,
            'quantity updated' => self::CORRECTION_REASON_QUANTITY_EDIT,
            'rate corrected' => self::CORRECTION_REASON_RATE_CORRECTION,
            'data entry error' => self::CORRECTION_REASON_DATA_ENTRY_ERROR,
            'system adjustment' => self::CORRECTION_REASON_SYSTEM_ADJUSTMENT,
        ];
        
        return $reasons[$reason] ?? self::CORRECTION_REASON_QUANTITY_EDIT;
    }
}
