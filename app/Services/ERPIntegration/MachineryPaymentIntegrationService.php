<?php

namespace App\Services\ERPIntegration;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Models\PaymentsModule;
use App\Models\PaymentRequest;
use App\Services\PaymentService;
use App\Support\PaymentSources;
use App\Support\IntegrationAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MachineryPaymentIntegrationService
{
    protected PaymentService $paymentService;
    
    // Phase B1.6: Standardized precision scale for all financial calculations
    const FINANCIAL_PRECISION_SCALE = 2;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create ERP payment from machinery payment request
     * Phase B1: Developer-only integration with full safeguards
     */
    public function createPayment(MachineryPaymentRequest $request, array $paymentData, bool $dryRun = false): array
    {
        // Phase B1.6: Production safety kill switch
        if (!$dryRun && !config('machinery_payment.write_enabled', false)) {
            $financialEventUuid = Str::uuid()->toString();
            IntegrationAuditLogger::logMachineryPaymentEvent('payment_creation_blocked', [
                'financial_event_uuid' => $financialEventUuid,
                'payment_request_id' => $request->id,
                'reason' => 'Production safety kill switch engaged',
                'write_enabled' => config('machinery_payment.write_enabled', false),
            ]);

            throw new \RuntimeException('Machinery payment creation is currently disabled for production safety');
        }

        $integrationReference = $this->generateIntegrationReference($paymentData);
        $financialEventUuid = Str::uuid()->toString();
        
        // Create financial snapshot for audit and validation
        $snapshot = $this->createFinancialSnapshot($request, $paymentData);
        
        IntegrationAuditLogger::logMachineryPaymentEvent('payment_creation_started', [
            'financial_event_uuid' => $financialEventUuid,
            'payment_request_id' => $request->id,
            'integration_reference' => $integrationReference,
            'amount' => $paymentData['amount'],
            'dry_run' => $dryRun,
        ]);

        try {
            // SAFEGUARD 3: Idempotency protection - OUTSIDE transaction to prevent duplicate constraint
            // Only check for exact integration reference match to allow partial payments
            $existingPayment = PaymentsModule::where('source_type', 'machinery_payment_request')
                ->where('source_id', $request->id)
                ->where('integration_reference_uuid', $integrationReference)
                ->first();

            if ($existingPayment) {
                IntegrationAuditLogger::logMachineryPaymentEvent('payment_idempotent_retry', [
                    'payment_request_id' => $request->id,
                    'integration_reference' => $integrationReference,
                    'existing_payment_id' => $existingPayment->id,
                    'payment_number' => $existingPayment->payment_number,
                ]);

                return [
                    'success' => true,
                    'dry_run' => false,
                    'integration_reference' => $integrationReference,
                    'payment_id' => $existingPayment->id,
                    'payment_number' => $existingPayment->payment_number,
                    'amount' => $existingPayment->amount,
                    'voucher_id' => $existingPayment->id,
                    'retry' => true,
                    'message' => 'Payment already exists (idempotent retry)',
                ];
            }

            // Phase B1.6: Deadlock retry handling with exponential backoff
            return $this->executeWithRetry(function () use ($request, $paymentData, $integrationReference, $dryRun, $snapshot) {
                return DB::transaction(function () use ($request, $paymentData, $integrationReference, $dryRun, $snapshot) {
                // SAFEGUARD 1: lockForUpdate() prevents race conditions
                $lockedRequest = MachineryPaymentRequest::lockForUpdate()->find($request->id);
                
                if (!$lockedRequest) {
                    throw new \InvalidArgumentException("Machinery payment request #{$request->id} not found");
                }

                // SAFEGUARD 2: Hard validation layer
                $this->validatePaymentCreation($lockedRequest, $paymentData);

                // Build ERP-compatible payload
                $erpPayload = $this->buildErpPayload($lockedRequest, $paymentData, $integrationReference, $snapshot);

                // Create payment through existing ERP service using create() method
                $payment = $this->paymentService->create($erpPayload);

                // Link payment to source (machinery payment request) directly
                $payment->update([
                    'source_type' => 'machinery_payment_request',
                    'source_id' => $lockedRequest->id,
                    'integration_reference_uuid' => $integrationReference,
                ]);

                IntegrationAuditLogger::logMachineryPaymentEvent('payment_created', [
                    'payment_request_id' => $lockedRequest->id,
                    'integration_reference' => $integrationReference,
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => $payment->amount,
                    'voucher_id' => $payment->id, // Assuming payment ID is voucher
                ]);

                return [
                    'success' => true,
                    'dry_run' => false,
                    'integration_reference' => $integrationReference,
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => $payment->amount,
                    'voucher_id' => $payment->id,
                    'snapshot' => $snapshot,
                    'retry' => false,
                ];
                });
            });

        } catch (\Exception $e) {
            IntegrationAuditLogger::logMachineryPaymentEvent('payment_creation_failed', [
                'payment_request_id' => $request->id,
                'integration_reference' => $integrationReference,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * Create financial snapshot for audit and validation
     */
    protected function createFinancialSnapshot(MachineryPaymentRequest $request, array $paymentData): array
    {
        $previousPostedTotal = $request->payments()->posted()->sum('amount');
        $remainingBalance = $request->net_payable - $previousPostedTotal;
        
        return [
            'source_snapshot_total' => $request->net_payable,
            'previous_posted_total' => $previousPostedTotal,
            'allocated_amount' => $paymentData['amount'],
            'remaining_balance_after' => $remainingBalance - $paymentData['amount'],
        ];
    }

    /**
     * Generate unique integration reference for idempotency
     */
    protected function generateIntegrationReference(array $paymentData): string
    {
        return 'mach_' . Str::uuid()->toString() . '_' . time();
    }

    /**
     * Generate unique payment number for partial payments
     */
    protected function generatePartialPaymentNumber(MachineryPaymentRequest $request): string
    {
        // Count existing payments for this request to generate sequence
        $existingCount = \App\Models\PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $request->id)
            ->count();
        
        $sequence = $existingCount + 1;
        
        return 'MACH-' . date('Y') . '-' . str_pad($request->id, 6, '0', STR_PAD_LEFT) . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Hard validation layer before ERP create
     */
    protected function validatePaymentCreation(MachineryPaymentRequest $request, array $paymentData): void
    {
        // Validate request exists and is in proper state
        if (!$request) {
            throw new \InvalidArgumentException('Machinery payment request not found');
        }

        // Must be locked to prevent modification during payment
        if ($request->status !== 'locked') {
            throw new \InvalidArgumentException("Payment request must be in 'locked' status. Current status: {$request->status}");
        }

        // Validate payable amount
        if ($request->net_payable <= 0) {
            throw new \InvalidArgumentException('Net payable must be positive');
        }

        // Validate payment amount
        if (empty($paymentData['amount']) || $paymentData['amount'] <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be positive']);
        }

        // Validate payment mode
        $validModes = ['bank_transfer', 'cash', 'cheque', 'upi', 'neft', 'rtgs'];
        if (empty($paymentData['payment_mode']) || !in_array($paymentData['payment_mode'], $validModes)) {
            throw ValidationException::withMessages(['payment_mode' => 'Invalid payment mode']);
        }

        // Validate payment date
        if (empty($paymentData['payment_date'])) {
            throw ValidationException::withMessages(['payment_date' => 'Payment date is required']);
        }
    }

    /**
     * Idempotency protection with retry-safe behavior
     */
    protected function checkIdempotency(string $integrationReference, int $requestId): ?PaymentsModule
    {
        // Check for existing payment using DB-level unique constraint
        $existingPayment = PaymentsModule::where('source_type', PaymentSources::MACHINERY_PAYMENT_REQUEST)
            ->where('source_id', $requestId)
            ->where('integration_reference_uuid', $integrationReference)
            ->first();

        if ($existingPayment) {
            IntegrationAuditLogger::logMachineryPaymentEvent('payment_retry_detected', [
                'payment_request_id' => $requestId,
                'integration_reference' => $integrationReference,
                'existing_payment_id' => $existingPayment->id,
                'existing_payment_status' => $existingPayment->status,
            ]);

            // Return existing payment for retry-safe behavior
            return $existingPayment;
        }

        return null;
    }

    /**
     * Build ERP-compatible payload
     */
    protected function buildErpPayload(MachineryPaymentRequest $request, array $paymentData, string $integrationReference, array $snapshot): array
    {
        return [
            'amount' => $paymentData['amount'],
            'payment_date' => $paymentData['payment_date'],
            'mode' => $paymentData['payment_mode'] ?? $paymentData['mode'] ?? 'bank_transfer', // Use actual ERP field name
            'reference_number' => $paymentData['reference_number'] ?? $integrationReference,
            'payment_type' => 'against_invoice',
            'purchase_invoice_id' => null, // Machinery payments don't require a purchase invoice
            'source_type' => PaymentSources::MACHINERY_PAYMENT_REQUEST,
            'payment_number' => $this->generatePartialPaymentNumber($request), // Unique payment number for partial payments
'notes'                 => "Machinery Payment Integration - Ref: {$integrationReference}\n" .
                      "Request ID: {$request->id}\n" .
                      "Period: {$request->period_start} to {$request->period_end}\n" .
                      "Supplier: " . ($request->supplier->name ?? 'N/A') . "\n" .
                      "Snapshot: " . json_encode($snapshot),
             'supplier_id'           => $request->supplier_id,
             'workspace_id'          => $request->workspace_id,
             'created_by'            => auth()->id() ?? 1,
             'site_id'               => $request->machinery->site_id ?? 1,
            'payment_proff_file' => $paymentData['payment_proof_file'] ?? null, // Add payment proof file
            // Include snapshot metadata for audit
            'source_snapshot_total' => $snapshot['source_snapshot_total'],
            'allocated_amount' => $snapshot['allocated_amount'],
        ];
    }

    /**
     * Create mock PaymentRequest for ERP compatibility
     * This bridges machinery requests to existing ERP PaymentService
     */
    protected function createMockPaymentRequest(array $erpPayload): PaymentRequest
    {
        $mockRequest = new PaymentRequest();
        $mockRequest->id = 0; // Will be replaced by actual linking
        $mockRequest->supplier_id = $erpPayload['supplier_id'];
        $mockRequest->requested_amount = $erpPayload['amount'];
        $mockRequest->approved_amount = $erpPayload['amount'];
        $mockRequest->status = 'approved';
        $mockRequest->workspace_id = $erpPayload['workspace_id'];
        
        return $mockRequest;
    }

    /**
     * Get payment breakdown for validation
     */
    public function getPaymentBreakdown(MachineryPaymentRequest $request): array
    {
        return SettlementCalculator::getPaymentBreakdown($request);
    }

    /**
     * Phase B1.6: Execute with retry for deadlock handling
     */
    protected function executeWithRetry(callable $callback, int $maxAttempts = 3, int $baseDelay = 100): mixed
    {
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $attempt++;
                
                // Check if it's a deadlock or lock wait timeout
                $isDeadlock = str_contains(strtolower($e->getMessage()), 'deadlock') || 
                             str_contains(strtolower($e->getMessage()), 'lock wait timeout');
                
                if (!$isDeadlock || $attempt >= $maxAttempts) {
                    throw $e;
                }
                
                // Exponential backoff: 100ms, 200ms, 400ms
                $delay = $baseDelay * (2 ** ($attempt - 1));
                usleep($delay * 1000); // Convert to microseconds
                
                IntegrationAuditLogger::logMachineryPaymentEvent('deadlock_retry', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'delay_ms' => $delay,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        throw new \RuntimeException('Max retry attempts exceeded');
    }

    /**
     * Check if payment can be created for request
     */
    public function canCreatePayment(MachineryPaymentRequest $request): array
    {
        $errors = [];
        
        if ($request->status !== 'locked') {
            $errors[] = "Request must be in 'locked' status. Current: {$request->status}";
        }

        if ($request->net_payable <= 0) {
            $errors[] = "Net payable must be positive. Current: {$request->net_payable}";
        }

        $alreadyPosted = $request->payments()->posted()->sum('amount');
        $remainingBalance = $request->net_payable - $alreadyPosted;

        if ($remainingBalance <= 0) {
            $errors[] = "No remaining balance. Already posted: {$alreadyPosted}";
        }

        return [
            'can_create' => empty($errors),
            'errors' => $errors,
            'remaining_balance' => $remainingBalance,
            'already_posted' => $alreadyPosted,
        ];
    }
}
