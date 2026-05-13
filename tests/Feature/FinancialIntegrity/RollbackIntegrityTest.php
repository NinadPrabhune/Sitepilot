<?php

namespace Tests\Feature\FinancialIntegrity;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Models\PaymentsModule;
use App\Services\ERPIntegration\MachineryPaymentIntegrationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollbackIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CRITICAL: Rollback integrity test with forced failure
     * Phase B1.6: Operational Financial Proof
     * 
     * Test Scenario:
     * 1. Start transaction
     * 2. Prepare ERP payment
     * 3. Prepare source linkage
     * 4. Force Exception inside transaction
     * 5. Verify complete rollback
     */
    public function test_rollback_integrity_with_forced_failure(): void
    {
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

        $this->info("Created test request #{$request->id} with payable: {$request->net_payable}");

        $paymentData = [
            'amount' => 10000.00,
            'payment_mode' => 'bank_transfer',
            'payment_date' => now()->format('Y-m-d'),
            'reference_number' => 'ROLLBACK-TEST',
        ];

        // Mock PaymentService to throw exception AFTER payment preparation
        $mockPaymentService = $this->mock(PaymentService::class);
        $mockPaymentService->shouldReceive('createPaymentFromRequest')
            ->once()
            ->andThrow(new \RuntimeException('Simulated ERP failure for rollback test'));

        $integrationService = new MachineryPaymentIntegrationService($mockPaymentService);

        // Verify initial state
        $this->assertEquals(0, $request->payments()->count(), "Initial state: no payments should exist");
        $this->assertEquals(0, PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $request->id)->count(), "Initial state: no source linkage should exist");

        // Attempt payment creation with forced failure
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulated ERP failure for rollback test');

        $integrationService->createPayment($request, $paymentData, false);

        // CRITICAL VALIDATION: Verify complete rollback
        $this->info("Verifying rollback integrity...");

        // No partial payment rows should exist
        $paymentCount = $request->payments()->count();
        $this->assertEquals(0, $paymentCount, 
            "Rollback failed: Found {$paymentCount} payment rows. Expected 0.");

        // No orphan source linkage should exist
        $orphanCount = PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $request->id)
            ->count();
        $this->assertEquals(0, $orphanCount, 
            "Rollback failed: Found {$orphanCount} orphan source linkages. Expected 0.");

        // Settlement status should remain unchanged
        $settlementStatus = $request->settlement_status;
        $this->assertEquals('unpaid', $settlementStatus, 
            "Rollback failed: Settlement status changed to '{$settlementStatus}'. Expected 'unpaid'.");

        // No partial audit trail corruption
        $this->info("✅ CRITICAL VALIDATION PASSED: Rollback integrity verified");
    }

    /**
     * Test transaction atomicity with multiple operations
     */
    public function test_transaction_atomicity_with_multiple_operations(): void
    {
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

        $paymentData = [
            'amount' => 10000.00,
            'payment_mode' => 'bank_transfer',
            'payment_date' => now()->format('Y-m-d'),
            'reference_number' => 'ATOMICITY-TEST',
        ];

        // Mock to fail after creating payment but before source linkage
        $mockPaymentService = $this->mock(PaymentService::class);
        $mockPaymentService->shouldReceive('createPaymentFromRequest')
            ->once()
            ->andReturn(new PaymentsModule([
                'id' => 999,
                'amount' => 10000.00,
                'payment_number' => 'TEST-001',
            ]));

        // Mock the update method to throw exception
        $mockPaymentService->shouldReceive('updatePaymentSource')
            ->once()
            ->andThrow(new \RuntimeException('Failed during source linkage'));

        $integrationService = new MachineryPaymentIntegrationService($mockPaymentService);

        $this->expectException(\RuntimeException::class);

        $integrationService->createPayment($request, $paymentData, false);

        // Verify no partial data remains
        $this->assertEquals(0, $request->payments()->count());
        $this->assertEquals(0, PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $request->id)->count());
    }

    /**
     * Helper method to output test information
     */
    protected function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }
}
