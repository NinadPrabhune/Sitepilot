<?php

namespace App\Console\Commands;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Models\PaymentsModule;
use App\Services\ERPIntegration\MachineryPaymentIntegrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExecuteFinancialProof extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:execute-financial-proof {test=idempotency : Test to execute (idempotency|rollback|concurrency)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute operational financial proof tests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $test = $this->argument('test');
        
        $this->info('🔬 EXECUTING OPERATIONAL FINANCIAL PROOF');
        $this->line('==========================================');
        $this->info("Test: {$test}");
        $this->line('');

        try {
            switch ($test) {
                case 'idempotency':
                    return $this->executeIdempotencyProof();
                case 'rollback':
                    return $this->executeRollbackProof();
                case 'concurrency':
                    return $this->executeConcurrencyProof();
                default:
                    $this->error("Unknown test: {$test}");
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Execute idempotency proof with physical DB verification
     */
    protected function executeIdempotencyProof(): int
    {
        $this->info('📋 STEP 1: Idempotency Proof with Physical DB Verification');
        $this->line('Creating test machinery payment request...');

        // Create test machinery payment request
        $request = MachineryPaymentRequest::create([
            'workspace_id' => 1,
            'supplier_id' => 1,
            'period_start' => now()->subMonth(),
            'period_end' => now(),
            'credits' => 0,
            'debits' => 50000.00,
            'net_payable' => 50000.00,
            'status' => 'locked',
            'locked_by' => 1,
            'locked_at' => now(),
        ]);

        $this->info("✅ Created test request #{$request->id} with payable: {$request->net_payable}");

        // Create DB snapshot
        $beforeSnapshot = $this->createDbSnapshot('before_idempotency');

        $paymentData = [
            'amount' => 10000.00,
            'payment_mode' => 'bank_transfer',
            'payment_date' => now()->format('Y-m-d'),
            'reference_number' => 'IDEMPOTENCY-PROOF',
        ];

        $integrationService = app(MachineryPaymentIntegrationService::class);

        // FIRST ATTEMPT
        $this->info('📋 STEP 2: First attempt - should create payment');
        $result1 = $integrationService->createPayment($request, $paymentData, false);

        if (!$result1['success']) {
            $this->error("❌ First attempt failed");
            return 1;
        }

        $this->info("✅ First attempt succeeded: Payment ID {$result1['payment_id']}");

        // Verify physical DB state after first attempt
        $this->verifyPhysicalDbState($request->id, 1, 10000.00, 'partial');

        // SECOND ATTEMPT
        $this->info('📋 STEP 3: Second attempt - should return existing payment');
        $result2 = $integrationService->createPayment($request, $paymentData, false);

        if (!$result2['success'] || !$result2['retry']) {
            $this->error("❌ Second attempt failed or not marked as retry");
            return 1;
        }

        if ($result1['payment_id'] !== $result2['payment_id']) {
            $this->error("❌ Second attempt returned different payment ID");
            return 1;
        }

        $this->info("✅ Second attempt succeeded: Returned existing payment ID {$result2['payment_id']}");

        // CRITICAL VALIDATION: Physical DB row count
        $integrationReference = $result1['integration_reference'] ?? 'test-reference';
        $actualCount = PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $request->id)
            ->where('integration_reference_uuid', $integrationReference)
            ->count();

        if ($actualCount !== 1) {
            $this->error("❌ Idempotency violation: expected 1 row, found {$actualCount}");
            return 1;
        }

        $this->info("✅ Physical DB verification: {$actualCount} rows for integration reference");

        // Final integrity verification
        $this->verifyPhysicalDbState($request->id, 1, 10000.00, 'partial');

        // Create final snapshot
        $afterSnapshot = $this->createDbSnapshot('after_idempotency');

        // Verify DB changes
        $this->verifyDbChanges($beforeSnapshot, $afterSnapshot, [
            'payments_module' => 1,
            'machinery_payment_requests' => 0, // Test request created before snapshot
        ]);

        $this->info('🎉 IDEMPOTENCY PROOF COMPLETED SUCCESSFULLY');
        $this->info('✅ Physical DB constraints verified');
        $this->info('✅ Retry behavior operationally validated');
        $this->info('✅ Financial integrity maintained');

        return 0;
    }

    /**
     * Verify physical DB state
     */
    protected function verifyPhysicalDbState(int $requestId, int $expectedTotalPayments, float $expectedPostedTotal, string $expectedStatus): void
    {
        $request = MachineryPaymentRequest::find($requestId);
        
        $actualState = [
            'total_payments' => $request->payments()->count(),
            'posted_payments' => $request->payments()->posted()->count(),
            'posted_total' => $request->payments()->posted()->sum('amount'),
            'settlement_status' => $request->settlement_status,
        ];

        if ($actualState['total_payments'] !== $expectedTotalPayments) {
            throw new \RuntimeException("Total payments mismatch: expected {$expectedTotalPayments}, got {$actualState['total_payments']}");
        }

        if (bccomp($actualState['posted_total'], $expectedPostedTotal, 2) !== 0) {
            throw new \RuntimeException("Posted total mismatch: expected {$expectedPostedTotal}, got {$actualState['posted_total']}");
        }

        if ($actualState['settlement_status'] !== $expectedStatus) {
            throw new \RuntimeException("Settlement status mismatch: expected {$expectedStatus}, got {$actualState['settlement_status']}");
        }

        $this->info("✅ DB state verified: {$actualState['total_payments']} payments, {$actualState['posted_total']} posted, {$actualState['settlement_status']} status");
    }

    /**
     * Create DB snapshot
     */
    protected function createDbSnapshot(string $name): array
    {
        $snapshot = [
            'name' => $name,
            'timestamp' => now()->toISOString(),
            'payments_module' => DB::table('payments_module')->count(),
            'machinery_payment_requests' => DB::table('machinery_payment_requests')->count(),
        ];

        $this->info("📸 DB Snapshot '{$name}' created");
        return $snapshot;
    }

    /**
     * Verify DB changes
     */
    protected function verifyDbChanges(array $before, array $after, array $expectedChanges): void
    {
        foreach ($expectedChanges as $table => $expectedChange) {
            $actualChange = ($after[$table] ?? 0) - ($before[$table] ?? 0);
            if ($actualChange !== $expectedChange) {
                throw new \RuntimeException("DB change mismatch for table '{$table}': expected {$expectedChange}, got {$actualChange}");
            }
        }

        $this->info("✅ DB changes verified");
    }

    protected function executeRollbackProof(): int
    {
        $this->info('📋 Rollback proof - to be implemented');
        return 0;
    }

    protected function executeConcurrencyProof(): int
    {
        $this->info('📋 Concurrency proof - to be implemented');
        return 0;
    }
}
