<?php

namespace App\Services;

use App\Models\SupplierLedger;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierLedgerService
{
    // Entry types
    const ENTRY_TYPE_DIESEL = 'diesel';
    const ENTRY_TYPE_PAYMENT = 'payment';
    const ENTRY_TYPE_ADJUSTMENT = 'adjustment';

    /**
     * Create a credit entry (supplier receives money/credit)
     */
    public static function createCredit(array $data): SupplierLedger
    {
        return self::createEntry(array_merge($data, [
            'entry_direction' => 'credit',
        ]));
    }

    /**
     * Create a debit entry (supplier owes money)
     */
    public static function createDebit(array $data): SupplierLedger
    {
        return self::createEntry(array_merge($data, [
            'entry_direction' => 'debit',
        ]));
    }

    /**
     * Create a ledger entry with running balance calculation
     */
    private static function createEntry(array $data): SupplierLedger
    {
        return DB::transaction(function () use ($data) {
            $supplierId = $data['supplier_id'];
            $amount = $data['amount'];
            $direction = $data['entry_direction'];
            $idempotencyKey = $data['idempotency_key'] ?? null;

            // Check for idempotency to prevent duplicate entries
            if ($idempotencyKey) {
                $existing = SupplierLedger::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            // Get last running balance for this supplier with row lock to prevent race conditions
            $lastEntry = SupplierLedger::where('supplier_id', $supplierId)
                ->where('is_reversal', false)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $previousBalance = $lastEntry ? $lastEntry->running_balance : 0;

            // Calculate new running balance
            if ($direction === 'credit') {
                $newBalance = $previousBalance + $amount;
            } else {
                $newBalance = $previousBalance - $amount;
            }

            // Create the entry
            $ledgerEntry = SupplierLedger::create([
                'supplier_id' => $supplierId,
                'workspace_id' => $data['workspace_id'] ?? getActiveWorkSpace(),
                'entry_direction' => $direction,
                'entry_type' => $data['entry_type'] ?? self::ENTRY_TYPE_DIESEL,
                'amount' => $amount,
                'running_balance' => $newBalance,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'date' => $data['date'] ?? now(),
                'description' => $data['description'] ?? '',
                'metadata' => $data['metadata'] ?? [],
                'idempotency_key' => $idempotencyKey,
                'is_reversal' => false,
            ]);

            return $ledgerEntry;
        });
    }

    /**
     * Reverse a ledger entry
     */
    public static function reverseEntry(int $entryId, string $reason = 'Reversal'): SupplierLedger
    {
        return DB::transaction(function () use ($entryId, $reason) {
            $original = SupplierLedger::findOrFail($entryId);

            if ($original->is_reversal) {
                throw new \RuntimeException('Cannot reverse a reversal entry.');
            }

            if ($original->reversed_entry_id) {
                throw new \RuntimeException('Entry already reversed.');
            }

            // Create reversal entry with opposite direction
            $reversal = self::createEntry([
                'supplier_id' => $original->supplier_id,
                'workspace_id' => $original->workspace_id,
                'amount' => $original->amount,
                'entry_direction' => $original->entry_direction === 'credit' ? 'debit' : 'credit',
                'entry_type' => $original->entry_type,
                'reference_type' => 'reversal',
                'reference_id' => $original->id,
                'date' => now(),
                'description' => "Reversal of entry #{$original->id}: {$reason}",
                'metadata' => [
                    'original_entry_id' => $original->id,
                    'original_reference_type' => $original->reference_type,
                    'original_reference_id' => $original->reference_id,
                ],
            ]);

            // Mark original as reversed
            $original->update([
                'reversed_entry_id' => $reversal->id,
            ]);

            return $reversal;
        });
    }

    /**
     * Get supplier balance
     */
    public static function getBalance(int $supplierId): float
    {
        $lastEntry = SupplierLedger::where('supplier_id', $supplierId)
            ->where('is_reversal', false)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? $lastEntry->running_balance : 0;
    }
}
