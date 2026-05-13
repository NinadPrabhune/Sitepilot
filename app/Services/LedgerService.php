<?php

namespace App\Services;

use App\Models\SupplierTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseInvoice;
use App\Models\Grn;
use App\Models\PaymentsModule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

class LedgerService
{
    private function getTraceId(): string
    {
        return request()->header('X-Request-ID') ?? (string) Str::uuid();
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'unique_reference') ||
               str_contains($e->getMessage(), 'Duplicate entry') ||
               str_contains($e->getMessage(), 'UNIQUE constraint');
    }

    private function isDeadlock(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'Deadlock') ||
               str_contains($e->getMessage(), 'deadlock') ||
               str_contains($e->getMessage(), '1213'); // MySQL deadlock error code
    }

    private function logCriticalEvent(string $event, array $context = [])
    {
        $traceId = $this->getTraceId();
        
        Log::critical('LEDGER_CRITICAL_EVENT', [
            'event' => $event,
            'trace_id' => $traceId,
            ...$context,
        ]);
        
        // Send alert via configured channels
        try {
            $alertChannel = config('services.alerts.channel', 'slack');
            app(\App\Services\AlertService::class)->send($alertChannel, $event, "Ledger critical event: {$event}", $context);
        } catch (\Exception $e) {
            Log::error('Failed to send alert', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    private function retryWithBackoff(callable $callback, int $maxAttempts = 3, int $delayMs = 100)
    {
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            try {
                return $callback();
            } catch (QueryException $e) {
                $attempts++;
                
                if ($this->isDeadlock($e) && $attempts < $maxAttempts) {
                    Log::warning('Deadlock detected, retrying', [
                        'attempt' => $attempts,
                        'max_attempts' => $maxAttempts,
                        'delay_ms' => $delayMs,
                    ]);
                    
                    // Alert if retrying multiple times (potential performance issue)
                    if ($attempts >= 2) {
                        $this->logCriticalEvent('DEADLOCK_RETRY_THRESHOLD_EXCEEDED', [
                            'attempt' => $attempts,
                            'max_attempts' => $maxAttempts,
                            'delay_ms' => $delayMs,
                        ]);
                    }
                    
                    usleep($delayMs * 1000);
                    $delayMs *= 2; // Exponential backoff
                    continue;
                }
                
                throw $e;
            }
        }
        
        throw new \Exception('Operation failed after ' . $maxAttempts . ' attempts due to deadlocks');
    }

    /**
     * Create PO ledger entry (transaction-agnostic - caller must wrap in transaction)
     */
    public function createPOEntry(PurchaseOrder $po): SupplierTransaction
    {
        return $this->retryWithBackoff(function () use ($po) {
            $traceId = $this->getTraceId();

            // Check for existing entry (idempotency)
            $existing = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_PO)
                ->where('reference_id', $po->id)
                ->where('supplier_id', $po->supplier_id)
                ->where('site_id', $po->site_id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                Log::info('PO ledger entry already exists', [
                    'trace_id' => $traceId,
                    'po_id' => $po->id,
                    'ledger_id' => $existing->id,
                ]);
                return $existing;
            }

            $totalQty = $po->items->sum('quantity');

            try {
                $ledgerEntry = SupplierTransaction::create([
                    'supplier_id' => $po->supplier_id,
                    'site_id' => $po->site_id,
                    'reference_type' => SupplierTransaction::TYPE_PO,
                    'reference_id' => $po->id,
                    'reference_amount' => $po->grand_total,
                    'transaction_date' => $po->po_date,
                    'debit' => 0,
                    'credit' => 0,
                    'meta' => json_encode(['non_accounting' => true]),
                    'description' => "{$po->po_number} / PO Created / Total Qty " . number_format($totalQty, 0) . " / ₹" . number_format($po->grand_total, 2),
                    'workspace_id' => $po->workspace_id,
                    'created_by' => $po->created_by,
                ]);

                Log::info('PO ledger entry created', [
                    'trace_id' => $traceId,
                    'ledger_id' => $ledgerEntry->id,
                    'po_id' => $po->id,
                    'po_number' => $po->po_number,
                ]);

                return $ledgerEntry;
            } catch (QueryException $e) {
                if ($this->isDuplicateKey($e)) {
                    $this->logCriticalEvent('DUPLICATE_KEY_EXCEPTION', [
                        'exception' => $e->getMessage(),
                        'reference_type' => SupplierTransaction::TYPE_PO,
                        'reference_id' => $po->id,
                        'supplier_id' => $po->supplier_id,
                        'site_id' => $po->site_id,
                    ]);
                    // Race condition - fetch and return existing
                    $existing = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_PO)
                        ->where('reference_id', $po->id)
                        ->where('supplier_id', $po->supplier_id)
                        ->where('site_id', $po->site_id)
                        ->first();
                    
                    if ($existing) {
                        Log::warning('PO ledger entry race condition resolved', [
                            'trace_id' => $traceId,
                            'po_id' => $po->id,
                            'ledger_id' => $existing->id,
                        ]);
                        return $existing;
                    }
                }
                throw $e;
            }
        });
    }

    /**
     * Create GRN ledger entry (transaction-agnostic - caller must wrap in transaction)
     */
    public function createGRNEntry(Grn $grn): SupplierTransaction
    {
        return $this->retryWithBackoff(function () use ($grn) {
            $traceId = $this->getTraceId();

            // Check for existing entry (idempotency)
            $existing = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_GRN)
                ->where('reference_id', $grn->id)
                ->where('supplier_id', $grn->supplier_id)
                ->where('site_id', $grn->site_id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                Log::info('GRN ledger entry already exists', [
                    'trace_id' => $traceId,
                    'grn_id' => $grn->id,
                    'ledger_id' => $existing->id,
                ]);
                return $existing;
            }

            $totalQty = $grn->items->sum('received_qty');

            try {
                $ledgerEntry = SupplierTransaction::create([
                    'supplier_id' => $grn->supplier_id,
                    'site_id' => $grn->site_id,
                    'reference_type' => SupplierTransaction::TYPE_GRN,
                    'reference_id' => $grn->id,
                    'reference_amount' => $grn->total_amount ?? 0,
                    'transaction_date' => $grn->grn_date,
                    'debit' => 0,
                    'credit' => 0,
                    'meta' => json_encode(['non_accounting' => true]),
                    'description' => "{$grn->grn_number} / GRN Received / Total Qty " . number_format($totalQty, 0),
                    'workspace_id' => $grn->workspace_id,
                    'created_by' => $grn->created_by,
                ]);

                Log::info('GRN ledger entry created', [
                    'trace_id' => $traceId,
                    'ledger_id' => $ledgerEntry->id,
                    'grn_id' => $grn->id,
                    'grn_number' => $grn->grn_number,
                ]);

                return $ledgerEntry;
            } catch (QueryException $e) {
                if ($this->isDuplicateKey($e)) {
                    $existing = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_GRN)
                        ->where('reference_id', $grn->id)
                        ->where('supplier_id', $grn->supplier_id)
                        ->where('site_id', $grn->site_id)
                        ->first();
                    
                    if ($existing) {
                        Log::warning('GRN ledger entry race condition resolved', [
                            'trace_id' => $traceId,
                            'grn_id' => $grn->id,
                            'ledger_id' => $existing->id,
                        ]);
                        return $existing;
                    }
                }
                throw $e;
            }
        });
    }

    /**
     * Create Invoice ledger entry (transaction-agnostic - caller must wrap in transaction)
     */
    public function createInvoiceEntry(PurchaseInvoice $invoice): SupplierTransaction
    {
        return $this->retryWithBackoff(function () use ($invoice) {
            $traceId = $this->getTraceId();

            // Check for existing entry (idempotency)
            $existing = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_INVOICE)
                ->where('reference_id', $invoice->id)
                ->where('supplier_id', $invoice->supplier_id)
                ->where('site_id', $invoice->site_id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // Update existing entry if amount changed
                if ($existing->reference_amount != $invoice->grand_total) {
                    $existing->update([
                        'reference_amount' => $invoice->grand_total,
                        'debit' => $invoice->grand_total,
                        'transaction_date' => $invoice->invoice_date,
                        'description' => "{$invoice->invoice_number} / Invoice Updated / ₹" . number_format($invoice->grand_total, 2),
                    ]);
                    Log::info('Invoice ledger entry updated', [
                        'trace_id' => $traceId,
                        'ledger_id' => $existing->id,
                        'invoice_id' => $invoice->id,
                        'old_amount' => $existing->reference_amount,
                        'new_amount' => $invoice->grand_total,
                    ]);
                }
                return $existing;
            }

            try {
                $ledgerEntry = SupplierTransaction::create([
                    'supplier_id' => $invoice->supplier_id,
                    'site_id' => $invoice->site_id,
                    'reference_type' => SupplierTransaction::TYPE_INVOICE,
                    'reference_id' => $invoice->id,
                    'reference_amount' => $invoice->grand_total,
                    'transaction_date' => $invoice->invoice_date,
                    'debit' => $invoice->grand_total,
                    'credit' => 0,
                    'description' => "{$invoice->invoice_number} / Invoice Generated / ₹" . number_format($invoice->grand_total, 2),
                    'workspace_id' => $invoice->workspace_id,
                    'created_by' => $invoice->created_by,
                ]);

                Log::info('Invoice ledger entry created', [
                    'trace_id' => $traceId,
                    'ledger_id' => $ledgerEntry->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $invoice->grand_total,
                ]);

                return $ledgerEntry;
            } catch (QueryException $e) {
                if ($this->isDuplicateKey($e)) {
                    $existing = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_INVOICE)
                        ->where('reference_id', $invoice->id)
                        ->where('supplier_id', $invoice->supplier_id)
                        ->where('site_id', $invoice->site_id)
                        ->first();
                    
                    if ($existing) {
                        Log::warning('Invoice ledger entry race condition resolved', [
                            'trace_id' => $traceId,
                            'invoice_id' => $invoice->id,
                            'ledger_id' => $existing->id,
                        ]);
                        return $existing;
                    }
                }
                throw $e;
            }
        });
    }

    /**
     * Create Payment ledger entry (transaction-agnostic - caller must wrap in transaction)
     * ENFORCED: Only call from PaymentService
     */
    public function createPaymentEntry(PaymentsModule $payment, bool $fromPaymentService = false): SupplierTransaction
    {
        return $this->retryWithBackoff(function () use ($payment, $fromPaymentService) {
            $traceId = $this->getTraceId();

            // Enforcement guard: ensure called from PaymentService
            if (!$fromPaymentService && !app()->runningInConsole()) {
                $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['class'] ?? 'unknown';
                if (!str_contains($caller, 'PaymentService')) {
                    Log::error('Payment ledger entry created outside PaymentService - VIOLATION', [
                        'trace_id' => $traceId,
                        'payment_id' => $payment->id,
                        'caller' => $caller,
                    ]);
                    throw new \Exception('Payment ledger entries must be created via PaymentService only');
                }
            }

            // Determine reference type
            $referenceType = $payment->payment_type === 'advance_against_po'
                ? SupplierTransaction::TYPE_ADVANCE
                : SupplierTransaction::TYPE_PAYMENT;

            // Check for existing entry (idempotency) - check specific type only
            $existing = SupplierTransaction::where('reference_id', $payment->id)
                ->where('reference_type', $referenceType)
                ->where('supplier_id', $payment->supplier_id)
                ->where('site_id', $payment->site_id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                Log::info('Payment ledger entry already exists', [
                    'trace_id' => $traceId,
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'ledger_id' => $existing->id,
                ]);
                return $existing;
            }

            $mode = $payment->mode ?? 'bank_transfer';

            $typeLabel = match($payment->payment_type) {
                'advance_against_po' => 'Advance Against PO',
                'against_invoice' => 'Against Invoice',
                'against_po' => 'Against PO',
                default => 'Payment'
            };

            $paymentSubtype = match($payment->payment_type) {
                'advance_against_po' => 'advance',
                'against_invoice', 'against_po' => 'invoice_payment',
                default => 'invoice_payment'
            };

            try {
                $ledgerEntry = SupplierTransaction::create([
                    'supplier_id' => $payment->supplier_id,
                    'site_id' => $payment->site_id,
                    'reference_type' => $referenceType,
                    'reference_id' => $payment->id,
                    'reference_amount' => $payment->amount,
                    'transaction_date' => $payment->payment_date,
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'meta' => json_encode(['payment_subtype' => $paymentSubtype]),
                    'description' => "{$payment->payment_number} / {$mode} / {$typeLabel}",
                    'workspace_id' => $payment->workspace_id,
                    'created_by' => $payment->created_by,
                ]);

                Log::info('Payment ledger entry created', [
                    'trace_id' => $traceId,
                    'ledger_id' => $ledgerEntry->id,
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_type' => $payment->payment_type,
                    'amount' => $payment->amount,
                ]);

                return $ledgerEntry;
            } catch (QueryException $e) {
                if ($this->isDuplicateKey($e)) {
                    $existing = SupplierTransaction::where('reference_id', $payment->id)
                        ->where('reference_type', $referenceType)
                        ->where('supplier_id', $payment->supplier_id)
                        ->where('site_id', $payment->site_id)
                        ->first();
                    
                    if ($existing) {
                        Log::warning('Payment ledger entry race condition resolved', [
                            'trace_id' => $traceId,
                            'payment_id' => $payment->id,
                            'ledger_id' => $existing->id,
                        ]);
                        return $existing;
                    }
                }
                throw $e;
            }
        });
    }

    /**
     * Create reversal entry for a payment (audit-safe - never delete ledger rows)
     * Creates a reverse entry to negate the original payment
     * 
     * @param PaymentsModule $payment The payment to reverse
     * @param string $reason Reason for reversal
     * @return SupplierTransaction The reversal ledger entry
     */
    public function reversePaymentEntry(PaymentsModule $payment, string $reason = 'Payment reversal'): SupplierTransaction
    {
        return $this->retryWithBackoff(function () use ($payment, $reason) {
            $traceId = $this->getTraceId();

            // Get original payment ledger entry
            $originalEntry = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_PAYMENT)
                ->where('reference_id', $payment->id)
                ->where('supplier_id', $payment->supplier_id)
                ->where('site_id', $payment->site_id)
                ->first();

            if (!$originalEntry) {
                throw new \Exception('Original payment ledger entry not found');
            }

            // Check if reversal already exists
            $existingReversal = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_ADJUSTMENT)
                ->where('reference_id', $payment->id)
                ->where('supplier_id', $payment->supplier_id)
                ->where('site_id', $payment->site_id)
                ->where('meta->like', '%"reversal_of":' . $originalEntry->id . '%')
                ->first();

            if ($existingReversal) {
                Log::warning('Payment reversal already exists', [
                    'trace_id' => $traceId,
                    'payment_id' => $payment->id,
                    'reversal_ledger_id' => $existingReversal->id,
                ]);
                return $existingReversal;
            }

            // Create reversal entry (debit to negate original credit)
            $reversalEntry = SupplierTransaction::create([
                'supplier_id' => $payment->supplier_id,
                'site_id' => $payment->site_id,
                'reference_type' => SupplierTransaction::TYPE_ADJUSTMENT,
                'reference_id' => $payment->id,
                'reference_amount' => $payment->amount,
                'transaction_date' => now(),
                'debit' => $payment->amount, // Reverse: debit instead of credit
                'credit' => 0,
                'meta' => json_encode([
                    'reversal_of' => $originalEntry->id,
                    'reversal_reason' => $reason,
                    'original_payment_number' => $payment->payment_number,
                ]),
                'description' => "Reversal: {$payment->payment_number} - {$reason}",
                'workspace_id' => $payment->workspace_id,
                'created_by' => auth()->id() ?? 1,
            ]);

            Log::info('Payment reversal ledger entry created', [
                'trace_id' => $traceId,
                'reversal_ledger_id' => $reversalEntry->id,
                'original_ledger_id' => $originalEntry->id,
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'reason' => $reason,
            ]);

            return $reversalEntry;
        });
    }

    /**
     * Log critical event for ledger rebuild
     */
    public static function logLedgerRebuild($supplierId, $siteId = null)
    {
        Log::critical('LEDGER_REBUILD_EXECUTED', [
            'supplier_id' => $supplierId,
            'site_id' => $siteId,
            'timestamp' => now()->toIso8601String(),
        ]);
        
        // Send alert via configured channels
        try {
            $alertChannel = config('services.alerts.channel', 'slack');
            app(\App\Services\AlertService::class)->send($alertChannel, 'LEDGER_REBUILD', "Ledger rebuild executed", [
                'supplier_id' => $supplierId,
                'site_id' => $siteId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send alert for ledger rebuild', ['error' => $e->getMessage()]);
        }
    }
}
