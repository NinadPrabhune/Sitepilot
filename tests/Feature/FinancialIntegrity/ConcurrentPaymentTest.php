<?php

namespace Tests\Feature\FinancialIntegrity;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Models\PaymentsModule;
use App\Services\ERPIntegration\MachineryPaymentIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ConcurrentPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CRITICAL: REAL concurrent payment collision test
     * Phase B1.6: Operational Financial Proof
     * 
     * Test Scenario:
     * Net Payable: ₹100,000
     * Attempt A: ₹60,000
     * Attempt B: ₹60,000
     * 
     * Expected: Exactly 1 succeeds, 1 fails
     */
    public function test_real_concurrent_payment_collision(): void
    {
        // Create test machinery payment request
        $request = MachineryPaymentRequest::create([
            'workspace_id' => 1,
            'supplier_id' => 1,
            'period_start' => now()->subMonth(),
            'period_end' => now(),
            'credits' => 0,
            'debits' => 100000.00,
            'net_payable' => 100000.00,
            'status' => 'locked',
            'locked_by' => 1,
            'locked_at' => now(),
        ]);

        $this->info("Created test request #{$request->id} with payable: {$request->net_payable}");

        // Test configuration
        $paymentAmount = 60000.00;
        $concurrentAttempts = 2;
        $totalAttempted = $paymentAmount * $concurrentAttempts;

        $this->info("Testing {$concurrentAttempts} concurrent attempts of {$paymentAmount} each");
        $this->info("Total attempted: {$totalAttempted}, Available: {$request->net_payable}");
        $this->info("Expected: 1 succeeds, 1 fails");

        // Prepare payment data for concurrent execution
        $paymentData = [
            'amount' => $paymentAmount,
            'payment_mode' => 'bank_transfer',
            'payment_date' => now()->format('Y-m-d'),
        ];

        // Simulate REAL concurrent payments using background processes
        $results = [];
        $processes = [];

        for ($i = 0; $i < $concurrentAttempts; $i++) {
            $referenceNumber = "CONCURRENT-TEST-" . $i . "-" . time();
            $testData = array_merge($paymentData, [
                'reference_number' => $referenceNumber,
            ]);

            // Create background process for each concurrent attempt
            $command = $this->buildConcurrentCommand($request->id, $testData);
            $processes[$i] = Process::start($command);
            
            $this->info("Started concurrent process {$i}: {$referenceNumber}");
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

            $this->info("Process {$index} completed: " . ($exitCode === 0 ? 'SUCCESS' : 'FAILED'));
        }

        // CRITICAL VALIDATION: Analyze results
        $successful = array_filter($results, fn($r) => $r['success']);
        $failed = array_filter($results, fn($r) => !$r['success']);

        $this->info("Results: " . count($successful) . " successful, " . count($failed) . " failed");

        // MANDATORY VALIDATION: Exactly one should succeed
        $this->assertEquals(1, count($successful), 
            "Exactly one payment should succeed. Got: " . count($successful) . " successful, " . count($failed) . " failed");

        // CRITICAL VALIDATION: Verify financial integrity
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

        // CRITICAL VALIDATION: No duplicate payments
        $duplicatePayments = PaymentsModule::where('source_type', 'machinery_payment_request')
            ->where('source_id', $request->id)
            ->count();
        $this->assertEquals(1, $duplicatePayments, 
            "No duplicate payments allowed. Found: {$duplicatePayments}");

        // CRITICAL VALIDATION: Settlement status should be 'partial'
        $settlementStatus = $request->settlement_status;
        $this->assertEquals('partial', $settlementStatus, 
            "Settlement status should be 'partial'. Got: {$settlementStatus}");

        $this->info("✅ CRITICAL VALIDATION PASSED: Real concurrent collision test successful");
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
