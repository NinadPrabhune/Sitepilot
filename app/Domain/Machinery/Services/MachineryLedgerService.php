<?php

namespace App\Domain\Machinery\Services;

use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MachineryLedgerService
{
    // Reference type constants
    const REFERENCE_TYPE_DPR = 'DailyProgressReport';
    const REFERENCE_TYPE_DIESEL = 'DailyConsumptionMaster';
    const REFERENCE_TYPE_MAINTENANCE = 'MaintenanceLog';
    const REFERENCE_TYPE_PAYMENT = 'MachineryPayment';
    const REFERENCE_TYPE_TRANSFER = 'GeneralTransfer';
    const REFERENCE_TYPE_REVERSAL = 'Reversal';
    const REFERENCE_TYPE_CORRECTED = 'Corrected';

    // Entry type constants
    const ENTRY_TYPE_READING = 'reading';
    const ENTRY_TYPE_DIESEL = 'diesel';
    const ENTRY_TYPE_MAINTENANCE = 'maintenance';
    const ENTRY_TYPE_ADVANCE = 'advance';
    const ENTRY_TYPE_PAYMENT = 'payment';
    const ENTRY_TYPE_TRANSFER = 'transfer';
    const ENTRY_TYPE_OPENING_BALANCE = 'opening_balance';
    const ENTRY_TYPE_CORRECTION = 'correction';
    const ENTRY_TYPE_REVERSAL = 'reversal';
    const ENTRY_TYPE_CORRECTED = 'corrected';

    /**
     * Create a credit entry (income/earnings) - WRITE-ONCE
     * Used for: DPR (work credits)
     */
    public static function createCredit(array $data): MachineryLedger
    {
        // Idempotency check
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey && self::creditExists($idempotencyKey)) {
            throw new \Exception('Credit entry already exists for this reference');
        }

        return DB::transaction(function () use ($data) {
            $machinery = Machinery::findOrFail($data['machinery_id']);
            
            // 🔴 CRITICAL: Determine ledger type based on machinery ownership
            $ledgerType = self::determineLedgerType($machinery->owned_by, $data['entry_type'] ?? 'reading');
            
            // 🔴 CRITICAL: Block payment requests for owned machinery
            if ($machinery->owned_by === 'owned' && isset($data['payment_request_id'])) {
                throw new \Exception('Payment requests are not allowed for owned machinery');
            }
            
            // Calculate running balance with row lock to prevent race conditions
            $runningBalance = DB::table('machinery_ledger')
                ->where('machinery_id', $data['machinery_id'])
                ->where('is_reversal', false)
                ->lockForUpdate()
                ->sum('amount');

            $newBalance = $runningBalance + $data['amount'];

            // 🔴 CRITICAL: Determine cost category to prevent double counting
            $costCategory = self::determineCostCategory($data['entry_type'] ?? 'reading', $ledgerType);
            
            // Create ledger entry with proper financial treatment and classification
            $ledger = MachineryLedger::create([
                'machinery_id' => $data['machinery_id'],
                'workspace_id' => $data['workspace_id'] ?? getActiveWorkSpace(),
                'entry_direction' => 'credit',
                'entry_type' => $data['entry_type'] ?? 'reading',
                'ledger_type' => $ledgerType,
                'cost_category' => $costCategory,
                'reference_type' => $data['reference_type'],
                'reference_id' => $data['reference_id'],
                'dpr_id' => $data['dpr_id'] ?? null,
                'payment_request_id' => $data['payment_request_id'] ?? null,
                'amount' => $data['amount'],
                'running_balance' => $newBalance,
                'date' => $data['date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? 'Credit entry',
                'is_reversal' => false,
            ]);

            Log::info('Machinery ledger credit created', [
                'ledger_id' => $ledger->id,
                'machinery_id' => $data['machinery_id'],
                'machinery_owned_by' => $machinery->owned_by,
                'ledger_type' => $ledgerType,
                'cost_category' => $costCategory,
                'amount' => $data['amount'],
                'running_balance' => $newBalance,
                'reference_type' => $data['reference_type'],
                'reference_id' => $data['reference_id'],
                'payment_request_id' => $data['payment_request_id'] ?? null,
            ]);

            return $ledger;
        });
    }

    /**
     * Determine cost category to prevent double counting
     */
    private static function determineCostCategory(string $entryType, string $ledgerType): string
    {
        // 🔴 CRITICAL: Cost categorization to prevent double counting
        return match($entryType) {
            'reading' => 'machine',        // DPR machine cost only
            'diesel' => 'diesel',          // Diesel expense separate
            'maintenance' => 'maintenance', // Maintenance expense separate
            'advance' => 'advance',         // Machinery advances
            'operator' => 'operator',       // Operator costs (if tracked here)
            default => 'other'              // Catch-all for unknown types
        };
    }

    /**
     * Determine ledger type based on machinery ownership and entry type
     */
    private static function determineLedgerType(string $ownedBy, string $entryType): string
    {
        // DPR reading entries
        if ($entryType === 'reading') {
            return $ownedBy === 'owned' ? 'internal_cost' : 'payable';
        }
        
        // Diesel and other expenses are always expenses
        if ($entryType === 'diesel' || $entryType === 'maintenance' || $entryType === 'advance') {
            return 'expense';
        }
        
        // Default to payable for safety
        return 'payable';
    }

    /**
     * Create a debit entry (expense/cost) - WRITE-ONCE
     * Used for: Diesel, Maintenance, Advances
     */
    public static function createDebit(array $data): MachineryLedger
    {
        return DB::transaction(function () use ($data) {
            $machineryId = $data['machinery_id'];
            $amount = $data['amount'];
            $referenceType = $data['reference_type'];
            $referenceId = $data['reference_id'];
            $date = $data['date'] ?? now()->toDateString();
            $description = $data['description'] ?? null;
            $metadata = $data['metadata'] ?? null;
            $idempotencyKey = $data['idempotency_key'] ?? null;
            $dprId = $data['dpr_id'] ?? null;
            $paymentRequestId = $data['payment_request_id'] ?? null;

            // Check for idempotency to prevent duplicate entries
            if ($idempotencyKey) {
                $existing = MachineryLedger::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    Log::info('Ledger entry already exists (idempotency)', [
                        'idempotency_key' => $idempotencyKey,
                        'existing_entry_id' => $existing->id,
                    ]);
                    return $existing;
                }
            }

            // Get machinery for workspace_id
            $machinery = Machinery::findOrFail($machineryId);
            $workspaceId = $machinery->workspace_id;

            // Calculate running balance with row lock to prevent race conditions
            $lastBalance = MachineryLedger::where('machinery_id', $machineryId)
                ->where('is_reversal', false)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->value('running_balance') ?? 0;

            $runningBalance = $lastBalance - $amount;

            // Create ledger entry
            $ledgerEntry = MachineryLedger::create([
                'machinery_id' => $machineryId,
                'workspace_id' => $workspaceId,
                'entry_direction' => 'debit',
                'entry_type' => $data['entry_type'] ?? self::ENTRY_TYPE_DIESEL,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'dpr_id' => $dprId, // STRICT LINKAGE
                'payment_request_id' => $paymentRequestId, // STRICT LINKAGE
                'amount' => $amount,
                'running_balance' => $runningBalance,
                'date' => $date,
                'description' => $description,
                'metadata' => $metadata,
                'idempotency_key' => $idempotencyKey,
            ]);

            Log::info('ledger.created', [
                'event' => 'ledger.debit.created',
                'ledger_entry_id' => $ledgerEntry->id,
                'machinery_id' => $machineryId,
                'amount' => $amount,
                'running_balance' => $runningBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => auth()->id(),
                'workspace_id' => $workspaceId,
                'timestamp' => now()->toISOString(),
            ]);

            return $ledgerEntry;
        });
    }

    /**
     * Reverse a ledger entry
     */
    public static function reverseEntry(int $entryId, string $reason = 'Reversal'): MachineryLedger
    {
        // Reversal governance: Only Admin/Accounts can reverse
        $user = auth()->user();
        if (!$user || !$user->hasRole(['super admin', 'admin', 'company'])) {
            throw new \RuntimeException('Only Admin or Accounts users can reverse ledger entries.');
        }

        // Require reason for audit trail
        if (empty($reason) || trim($reason) === '') {
            throw new \RuntimeException('A reason is required for reversal.');
        }

        return DB::transaction(function () use ($entryId, $reason) {
            $originalEntry = MachineryLedger::findOrFail($entryId);

            if ($originalEntry->is_reversal) {
                throw new \RuntimeException('Cannot reverse a reversal entry.');
            }

            if ($originalEntry->reversed_entry_id) {
                throw new \RuntimeException('Entry already reversed.');
            }

            $workspaceId = $originalEntry->workspace_id;

            // Calculate running balance with row lock to prevent race conditions
            $lastBalance = MachineryLedger::where('machinery_id', $originalEntry->machinery_id)
                ->where('is_reversal', false)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->value('running_balance') ?? 0;

            // Reverse the amount
            $reversedAmount = $originalEntry->amount;
            if ($originalEntry->entry_direction === 'credit') {
                $runningBalance = $lastBalance - $reversedAmount;
                $reversalDirection = 'debit';
            } else {
                $runningBalance = $lastBalance + $reversedAmount;
                $reversalDirection = 'credit';
            }

            // Create reversal entry
            $reversalEntry = MachineryLedger::create([
                'machinery_id' => $originalEntry->machinery_id,
                'workspace_id' => $workspaceId,
                'entry_direction' => $reversalDirection,
                'entry_type' => $originalEntry->entry_type,
                'reference_type' => $originalEntry->reference_type,
                'reference_id' => $originalEntry->reference_id,
                'amount' => $reversedAmount,
                'running_balance' => $runningBalance,
                'date' => now()->toDateString(),
                'description' => "Reversal of entry #{$originalEntry->id}: {$reason}",
                'metadata' => array_merge($originalEntry->metadata ?? [], [
                    'reversal_of' => $originalEntry->id,
                    'reversal_reason' => $reason,
                ]),
                'reversed_entry_id' => $originalEntry->id,
                'is_reversal' => true,
            ]);

            // Mark original as reversed
            $originalEntry->update([
                'reversed_entry_id' => $reversalEntry->id,
            ]);

            Log::info('ledger.reversed', [
                'event' => 'ledger.entry.reversed',
                'original_entry_id' => $originalEntry->id,
                'reversal_entry_id' => $reversalEntry->id,
                'reason' => $reason,
                'machinery_id' => $originalEntry->machinery_id,
                'amount' => $reversedAmount,
                'user_id' => auth()->id(),
                'workspace_id' => $workspaceId,
                'timestamp' => now()->toISOString(),
            ]);

            return $reversalEntry;
        });
    }

    /**
     * Create an opening balance entry (Admin only)
     * Used for initial balance setup
     */
    public static function createOpeningBalance(array $data): MachineryLedger
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole(['super admin', 'admin'])) {
            throw new \RuntimeException('Only Admin users can create opening balance entries.');
        }

        if (empty($data['remarks']) || trim($data['remarks']) === '') {
            throw new \RuntimeException('Remarks are mandatory for opening balance entries.');
        }

        return DB::transaction(function () use ($data) {
            $machineryId = $data['machinery_id'];
            $amount = $data['amount'];
            $date = $data['date'] ?? now()->toDateString();
            $remarks = $data['remarks'];
            $workspaceId = getActiveWorkSpace();

            // Get current running balance
            $currentBalance = MachineryLedger::where('machinery_id', $machineryId)
                ->where('workspace_id', $workspaceId)
                ->where('is_reversal', false)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->value('running_balance') ?? 0;

            // Create opening balance entry
            $entry = MachineryLedger::create([
                'machinery_id' => $machineryId,
                'workspace_id' => $workspaceId,
                'entry_direction' => $amount >= 0 ? 'credit' : 'debit',
                'entry_type' => self::ENTRY_TYPE_OPENING_BALANCE,
                'reference_type' => 'OpeningBalance',
                'reference_id' => null,
                'amount' => abs($amount),
                'running_balance' => $currentBalance + $amount,
                'date' => $date,
                'description' => "Opening Balance: {$remarks}",
                'metadata' => [
                    'remarks' => $remarks,
                    'created_by' => auth()->id(),
                ],
            ]);

            Log::info('ledger.opening_balance', [
                'event' => 'ledger.opening_balance.created',
                'entry_id' => $entry->id,
                'machinery_id' => $machineryId,
                'amount' => $amount,
                'new_balance' => $entry->running_balance,
                'user_id' => auth()->id(),
                'workspace_id' => $workspaceId,
                'timestamp' => now()->toISOString(),
            ]);

            return $entry;
        });
    }

    /**
     * Create a correction entry (Admin only)
     * Used for manual adjustments with mandatory remarks
     */
    public static function createCorrection(array $data): MachineryLedger
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole(['super admin', 'admin'])) {
            throw new \RuntimeException('Only Admin users can create correction entries.');
        }

        if (empty($data['remarks']) || trim($data['remarks']) === '') {
            throw new \RuntimeException('Remarks are mandatory for correction entries.');
        }

        return DB::transaction(function () use ($data) {
            $machineryId = $data['machinery_id'];
            $amount = $data['amount'];
            $date = $data['date'] ?? now()->toDateString();
            $remarks = $data['remarks'];
            $workspaceId = getActiveWorkSpace();

            // Get current running balance
            $currentBalance = MachineryLedger::where('machinery_id', $machineryId)
                ->where('workspace_id', $workspaceId)
                ->where('is_reversal', false)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->value('running_balance') ?? 0;

            // Create correction entry
            $entry = MachineryLedger::create([
                'machinery_id' => $machineryId,
                'workspace_id' => $workspaceId,
                'entry_direction' => $amount >= 0 ? 'credit' : 'debit',
                'entry_type' => self::ENTRY_TYPE_CORRECTION,
                'reference_type' => 'Correction',
                'reference_id' => null,
                'amount' => abs($amount),
                'running_balance' => $currentBalance + $amount,
                'date' => $date,
                'description' => "Correction: {$remarks}",
                'metadata' => [
                    'remarks' => $remarks,
                    'created_by' => auth()->id(),
                ],
            ]);

            Log::info('ledger.correction', [
                'event' => 'ledger.correction.created',
                'entry_id' => $entry->id,
                'machinery_id' => $machineryId,
                'amount' => $amount,
                'new_balance' => $entry->running_balance,
                'user_id' => auth()->id(),
                'workspace_id' => $workspaceId,
                'timestamp' => now()->toISOString(),
            ]);

            return $entry;
        });
    }

    /**
     * Check if a credit entry already exists with the given idempotency key
     */
    public static function creditExists(string $idempotencyKey): bool
    {
        return MachineryLedger::where('idempotency_key', $idempotencyKey)->exists();
    }
}
