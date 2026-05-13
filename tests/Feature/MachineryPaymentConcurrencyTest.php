<?php

namespace Tests\Feature;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Services\ERPIntegration\MachineryPaymentIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class MachineryPaymentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test concurrent payment creation with race condition protection
     * Phase B1.5: Critical finance-grade validation
     */
    public function test_concurrent_payment_creation_prevents_overpayment(): void
    {
        // Create test machinery payment request
        $request = MachineryPaymentRequest::factory()->create([
            'status' => 'locked',
            'net_payable' => 100000.00, // ₹100,000 payable
        ]);

        $this->info("Created test request #{$request->id} with payable: {$request->net_payable}");

        // Concurrent payment attempts (₹60,000 each)
        $paymentAmount = 60000.00;
        $concurrentAttempts = 2;
        $totalAttempted = $paymentAmount * $concurrentAttempts;

        $this->info("Testing {$concurrentAttempts} concurrent attempts of {$paymentAmount} each");
        $this->info("Total attempted: {$totalAttempted}, Available: {$request->net_payable}");
        $this->info("Expected: 1 succeeds, 1 fails");

        // Simulate concurrent payments using separate database connections
        $results = [];
        $processes = [];

        for ($i = 0; $i < $concurrentAttempts; $i++) {
            $referenceNumber = "CONCURRENT-TEST-" . $i . "-" . time();
            $paymentData = [
                'amount' => $paymentAmount,
                'payment_mode' => 'bank_transfer',
                'payment_date' => now()->format('Y-m-d'),
                'reference_number' => $referenceNumber,
            ];

            // Create background process for each concurrent attempt
            $command = $this->buildConcurrentCommand($request->id, $paymentData);
            $processes[$i] = Process::start($command);
        }

        // Wait for all processes to complete
        foreach ($processes as $index => $process) {
            $result = $process->wait();
            $output = $result->output();
            $errorOutput = $result->errorOutput();
            $exitCode = $result->exitCode();

            $results[$index] = [
                'exit_code' => $exitCode,
                'output' => $output,
                'error' => $errorOutput,
                'success' => $exitCode === 0,
            ];
        }

        // Analyze results
        $successful = array_filter($results, fn($r) => $r['success']);
        $failed = array_filter($results, fn($r) => !$r['success']);

        $this->info("Results: " . count($successful) . " successful, " . count($failed) . " failed");

        // CRITICAL VALIDATION: Exactly one should succeed
        $this->assertEquals(1, count($successful), 
            "Exactly one payment should succeed. Got: " . count($successful) . " successful, " . count($failed) . " failed");

        // Verify financial integrity
        $postedPayments = $request->payments()->posted()->sum('amount');
        $totalPayments = $request->payments()->sum('amount');

        $this->info("Financial integrity check:");
        $this->info("  Posted payments total: {$postedPayments}");
        $this->info("  All payments total: {$totalPayments}");

        // CRITICAL VALIDATION: No overpayment
        $this->assertLessThanOrEqual($request->net_payable, $postedPayments, 
            "Posted payments ({$postedPayments}) cannot exceed payable amount ({$request->net_payable})");

        // CRITICAL VALIDATION: Only one payment created
        $this->assertEquals(1, $request->payments()->count(), 
            "Exactly one payment should be created. Got: " . $request->payments()->count());
    }

    /**
     * Test idempotency with retry-safe behavior
     */
    public function test_idempotency_retry_safe_behavior(): void
    {
        $request = MachineryPaymentRequest::factory()->create([
            'status' => 'locked',
            'net_payable' => 50000.00,
        ]);

        $paymentData = [
            'amount' => 10000.00,
            'payment_mode' => 'bank_transfer',
            'payment_date' => now()->format('Y-m-d'),
            'reference_number' => 'IDEMPOTENCY-TEST',
        ];

        $integrationService = app(MachineryPaymentIntegrationService::class);

        // First attempt should succeed
        $result1 = $integrationService->createPayment($request, $paymentData, false);
        $this->assertTrue($result1['success']);
        $this->assertFalse($result1['retry']);

        // Second attempt with same reference should return existing payment
        $result2 = $integrationService->createPayment($request, $paymentData, false);
        $this->assertTrue($result2['success']);
        $this->assertTrue($result2['retry']);
        $this->assertEquals($result1['payment_id'], $result2['payment_id']);

        // Verify only one payment was created
        $this->assertEquals(1, $request->payments()->count());
    }

    /**
     * Test rollback integrity on ERP failure
     */
    public function test_rollback_integrity_on_erp_failure(): void
    {
        $request = MachineryPaymentRequest::factory()->create([
            'status' => 'locked',
            'net_payable' => 50000.00,
        ]);

        $paymentData = [
            'amount' => 10000.00,
            'payment_mode' => 'bank_transfer',
            'payment_date' => now()->format('Y-m-d'),
            'reference_number' => 'ROLLBACK-TEST',
        ];

        // Mock PaymentService to throw exception
        $mockPaymentService = $this->mock(\App\Services\PaymentService::class);
        $mockPaymentService->shouldReceive('createPaymentFromRequest')
            ->once()
            ->andThrow(new \RuntimeException('Simulated ERP failure'));

        $integrationService = new MachineryPaymentIntegrationService($mockPaymentService);

        // Attempt should fail and rollback
        $this->expectException(\RuntimeException::class);
        $integrationService->createPayment($request, $paymentData, false);

        // Verify no partial data remains after rollback
        $this->assertEquals(0, $request->payments()->count());
        
        // Verify no orphan source linkage
        $orphanPayments = \App\Models\PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $request->id)
            ->count();
        $this->assertEquals(0, $orphanPayments);
    }

    /**
     * Build command for concurrent execution
     */
    protected function buildConcurrentCommand(int $requestId, array $paymentData): string
    {
        $escapedData = escapeshellarg(json_encode($paymentData));
        $referenceNumber = $paymentData['reference_number'];
        
        return "php artisan machinery:test-single-payment {$requestId} '{$referenceNumber}' {$escapedData}";
    }

    /**
     * Helper method to output test information
     */
    protected function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }
}
