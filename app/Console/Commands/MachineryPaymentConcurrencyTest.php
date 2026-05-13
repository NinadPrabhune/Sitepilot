<?php

namespace App\Console\Commands;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Services\ERPIntegration\MachineryPaymentIntegrationService;
use App\Support\IntegrationAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class MachineryPaymentConcurrencyTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:test-concurrency {--request-id= : Specific machinery payment request ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test concurrent payment creation to verify race condition protection';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏁 Machinery Payment Concurrency Test');
        $this->line('Testing race condition protection with simultaneous payment attempts');
        $this->line('================================================================');

        try {
            $request = $this->findTestRequest();
            
            if (!$request) {
                $this->error('❌ No suitable machinery payment request found for testing');
                return 1;
            }

            $this->info("✅ Found test request: #{$request->id}");
            $this->line("  Net Payable: {$request->net_payable}");
            $this->line("  Status: {$request->status}");

            // Calculate test amounts for concurrent payments
            $maxConcurrent = 3;
            $paymentAmount = min(5000.00, $request->net_payable * 0.6); // 60% of payable
            $totalAttempted = $paymentAmount * $maxConcurrent;

            $this->line("\n📊 Test Configuration:");
            $this->line("  Concurrent Attempts: {$maxConcurrent}");
            $this->line("  Amount per Attempt: {$paymentAmount}");
            $this->line("  Total Attempted: {$totalAttempted}");
            $this->line("  Available Payable: {$request->net_payable}");
            $this->line("  Expected: 1 succeeds, 2 fail");

            // Prepare payment data
            $paymentData = [
                'amount' => $paymentAmount,
                'payment_mode' => 'bank_transfer',
                'payment_date' => now()->format('Y-m-d'),
                'reference_number' => 'CONCURRENCY-TEST',
                'notes' => 'Concurrency test payment',
            ];

            $this->line("\n🚀 Starting concurrent payment attempts...");

            // Simulate concurrent requests using background processes
            $processes = [];
            $results = [];

            for ($i = 0; $i < $maxConcurrent; $i++) {
                $referenceNumber = "CONCURRENCY-TEST-" . $i . "-" . time();
                $testData = array_merge($paymentData, [
                    'reference_number' => $referenceNumber,
                ]);

                // Create background process for each concurrent attempt
                $command = $this->buildConcurrentCommand($request->id, $testData);
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
            $this->analyzeResults($results, $request);

            IntegrationAuditLogger::logMachineryPaymentEvent('concurrency_test_completed', [
                'request_id' => $request->id,
                'total_attempts' => $maxConcurrent,
                'successful_attempts' => count(array_filter($results, fn($r) => $r['success'])),
                'payment_amount' => $paymentAmount,
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Concurrency test failed: " . $e->getMessage());
            IntegrationAuditLogger::logMachineryPaymentEvent('concurrency_test_failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            return 1;
        }
    }

    /**
     * Find a suitable machinery payment request for testing
     */
    protected function findTestRequest(): ?MachineryPaymentRequest
    {
        if ($requestId = $this->option('request-id')) {
            return MachineryPaymentRequest::find($requestId);
        }

        return MachineryPaymentRequest::where('status', 'locked')
            ->where('net_payable', '>', 5000) // Need enough for concurrent test
            ->with('supplier')
            ->first();
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
     * Analyze concurrent test results
     */
    protected function analyzeResults(array $results, MachineryPaymentRequest $request): void
    {
        $this->line("\n📈 Results Analysis:");
        
        $successful = array_filter($results, fn($r) => $r['success']);
        $failed = array_filter($results, fn($r) => !$r['success']);

        $this->line("  ✅ Successful: " . count($successful));
        $this->line("  ❌ Failed: " . count($failed));

        foreach ($results as $index => $result) {
            $status = $result['success'] ? '✅' : '❌';
            $output = substr($result['output'], 0, 100);
            $attemptNum = $index + 1;
            $this->line("  Attempt {$attemptNum}: {$status} {$output}");
        }

        // Verify financial integrity
        $this->line("\n🔍 Financial Integrity Check:");
        
        $postedPayments = $request->payments()->posted()->sum('amount');
        $totalPayments = $request->payments()->sum('amount');
        $settlementStatus = $request->settlement_status;

        $this->line("  Posted Payments Total: {$postedPayments}");
        $this->line("  All Payments Total: {$totalPayments}");
        $this->line("  Settlement Status: {$settlementStatus}");

        if (count($successful) === 1 && $postedPayments > 0) {
            $this->info("✅ PASS: Exactly one payment succeeded, race condition protection working");
        } elseif (count($successful) === 0) {
            $this->warn("⚠️  WARNING: All payments failed, may indicate over-protection");
        } else {
            $this->error("❌ FAIL: Multiple payments succeeded, race condition protection failed!");
        }

        // Check for overpayment
        if ($postedPayments > $request->net_payable) {
            $this->error("❌ CRITICAL: Overpayment detected! Posted: {$postedPayments}, Payable: {$request->net_payable}");
        } else {
            $this->info("✅ No overpayment detected");
        }
    }
}
