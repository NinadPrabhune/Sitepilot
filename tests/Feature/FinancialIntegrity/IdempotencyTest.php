<?php

namespace Tests\Feature\FinancialIntegrity;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Models\PaymentsModule;
use App\Services\ERPIntegration\MachineryPaymentIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\FinancialIntegrity\Traits\FinancialIntegrityAssertions;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase, FinancialIntegrityAssertions;

    /**
     * CRITICAL: Idempotent retry test with DB uniqueness
     * Phase B1.6: Operational Financial Proof
     * 
     * Test Scenario:
     * Same integration_reference_uuid, same source, same amount
     * Sent twice rapidly.
     * 
     * Expected:
     * First attempt: creates payment
     * Second attempt: returns existing payment safely
     * NOT: SQL crash, duplicate row, partial rollback
     */
    public function test_idempotent_retry_with_db_uniqueness(): void
    {
        // Create DB snapshot before test
        $this->createDbSnapshot('before_idempotency_test');

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
            'reference_number' => 'IDEMPOTENCY-TEST',
        ];

        $integrationService = app(MachineryPaymentIntegrationService::class);

        // Verify initial state
        $this->assertEquals(0, $request->payments()->count(), "Initial state: no payments should exist");

        // FIRST ATTEMPT: Should succeed and create payment
        $this->info("First attempt: Creating payment...");
        $result1 = $integrationService->createPayment($request, $paymentData, false);

        // CRITICAL VALIDATION: First attempt should succeed
        $this->assertTrue($result1['success'], "First attempt should succeed");
        $this->assertFalse($result1['retry'], "First attempt should not be marked as retry");
        $this->assertArrayHasKey('payment_id', $result1, "First attempt should return payment_id");
        $this->assertArrayHasKey('payment_number', $result1, "First attempt should return payment_number");

        $firstPaymentId = $result1['payment_id'];
        $this->info("First attempt succeeded: Payment ID {$firstPaymentId}");

        // Verify payment was actually created
        $this->assertEquals(1, $request->payments()->count(), "Payment should be created after first attempt");
        $this->assertEquals(1, PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $request->id)->count(), "Source linkage should exist after first attempt");

        // SECOND ATTEMPT: Same data, should return existing payment
        $this->info("Second attempt: Same integration reference...");
        $result2 = $integrationService->createPayment($request, $paymentData, false);

        // CRITICAL VALIDATION: Second attempt should return existing payment
        $this->assertTrue($result2['success'], "Second attempt should succeed");
        $this->assertTrue($result2['retry'], "Second attempt should be marked as retry");
        $this->assertEquals($firstPaymentId, $result2['payment_id'], "Second attempt should return same payment_id");
        $this->assertEquals($result1['payment_number'], $result2['payment_number'], "Second attempt should return same payment_number");

        $this->info("Second attempt succeeded: Returned existing payment ID {$result2['payment_id']}");

        // CRITICAL VALIDATION: Only one payment should exist
        $this->assertEquals(1, $request->payments()->count(), 
            "Only one payment should exist. Found: " . $request->payments()->count());

        // CRITICAL VALIDATION: No duplicate payments
        $duplicatePayments = PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $request->id)
            ->count();
        $this->assertEquals(1, $duplicatePayments, 
            "No duplicate payments allowed. Found: {$duplicatePayments}");

        // CRITICAL VALIDATION: Physical DB row count verification
        $integrationReference = $result1['integration_reference'] ?? 'test-reference';
        $this->assertIdempotencyWithPhysicalCount($integrationReference, $request->id, 1);

        // CRITICAL VALIDATION: Financial integrity verification
        $this->assertFinancialIntegrity($request->id, [
            'total_payments' => 1,
            'posted_payments' => 1,
            'posted_total' => 10000.00,
            'settlement_status' => 'partial',
        ]);

        // Create DB snapshot after test for audit trail
        $this->createDbSnapshot('after_idempotency_test');

        // Verify DB changes are as expected
        $this->assertDbSnapshotIntegrity('before_idempotency_test', 'after_idempotency_test', [
            'payments_module' => 1,
            'machinery_payment_requests' => 1,
        ]);

        $this->info("✅ CRITICAL VALIDATION PASSED: Idempotent retry behavior operationally verified");
    }

    /**
     * Test idempotency with different amounts (should fail)
     */
    public function test_idempotency_fails_with_different_amounts(): void
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

        $paymentData1 = [
            'amount' => 10000.00,
            'payment_mode' => 'bank_transfer',
            'payment_date' => now()->format('Y-m-d'),
            'reference_number' => 'IDEMPOTENCY-DIFF-TEST',
        ];

        $paymentData2 = [
            'amount' => 15000.00, // Different amount
            'payment_mode' => 'bank_transfer',
            'payment_date' => now()->format('Y-m-d'),
            'reference_number' => 'IDEMPOTENCY-DIFF-TEST', // Same reference
        ];

        $integrationService = app(MachineryPaymentIntegrationService::class);

        // First attempt should succeed
        $result1 = $integrationService->createPayment($request, $paymentData1, false);
        $this->assertTrue($result1['success']);

        // Second attempt with different amount should create new payment (different reference)
        $result2 = $integrationService->createPayment($request, $paymentData2, false);
        $this->assertTrue($result2['success']);
        $this->assertFalse($result2['retry']); // Should not be retry since amount differs

        // Should have 2 payments
        $this->assertEquals(2, $request->payments()->count());
    }

    /**
     * Helper method to output test information
     */
    protected function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }
}
