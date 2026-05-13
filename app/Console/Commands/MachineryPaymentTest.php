<?php

namespace App\Console\Commands;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Services\ERPIntegration\MachineryPaymentIntegrationService;
use App\Support\IntegrationAuditLogger;
use Illuminate\Console\Command;

class MachineryPaymentTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:test-payment {--dry-run : Test without creating actual payment} {--request-id= : Specific machinery payment request ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test machinery payment integration service (Phase B1)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Machinery Payment Integration Test (Phase B1)');
        $this->line('=====================================');

        try {
            $integrationService = app(MachineryPaymentIntegrationService::class);

            // Find a suitable machinery payment request
            $request = $this->findTestRequest();
            
            if (!$request) {
                $this->error('❌ No suitable machinery payment request found for testing');
                $this->line('Requirements: status = "locked", net_payable > 0');
                return 1;
            }

            $this->info("✅ Found test request: #{$request->id}");
            $this->line("  Net Payable: {$request->net_payable}");
            $this->line("  Status: {$request->status}");
            $this->line("  Supplier: " . ($request->supplier->name ?? 'N/A'));

            // Check if payment can be created
            $canCreate = $integrationService->canCreatePayment($request);
            
            if (!$canCreate['can_create']) {
                $this->error('❌ Cannot create payment for this request:');
                foreach ($canCreate['errors'] as $error) {
                    $this->line("  - {$error}");
                }
                return 1;
            }

            $this->info("✅ Payment creation validation passed");
            $this->line("  Remaining Balance: {$canCreate['remaining_balance']}");
            $this->line("  Already Posted: {$canCreate['already_posted']}");

            // Prepare test payment data
            $paymentData = $this->prepareTestPaymentData($canCreate['remaining_balance']);

            $this->line("\n📝 Test Payment Data:");
            $this->line("  Amount: {$paymentData['amount']}");
            $this->line("  Mode: {$paymentData['payment_mode']}");
            $this->line("  Date: {$paymentData['payment_date']}");

            // Test dry run first
            $this->line("\n🔍 Testing dry-run...");
            $dryRunResult = $integrationService->createPayment($request, $paymentData, true);

            if ($dryRunResult['success']) {
                $this->info("✅ Dry-run successful");
                $this->line("  Integration Reference: {$dryRunResult['integration_reference']}");
                $this->line("  ERP Payload Validated: " . (isset($dryRunResult['erp_payload']) ? 'Yes' : 'No'));
            } else {
                $this->error("❌ Dry-run failed");
                return 1;
            }

            // Ask user if they want to proceed with actual payment creation
            if (!$this->option('dry-run')) {
                if ($this->confirm('Do you want to create an actual payment? (This will create a real ERP payment)', false)) {
                    $this->line("\n💸 Creating actual payment...");
                    $result = $integrationService->createPayment($request, $paymentData, false);

                    if ($result['success']) {
                        $this->info("✅ Payment created successfully!");
                        $this->line("  Payment ID: {$result['payment_id']}");
                        $this->line("  Payment Number: {$result['payment_number']}");
                        $this->line("  Voucher ID: {$result['voucher_id']}");
                        $this->line("  Integration Reference: {$result['integration_reference']}");
                        
                        // Show updated settlement status
                        $breakdown = $integrationService->getPaymentBreakdown($request);
                        $this->line("\n📊 Updated Settlement Status:");
                        $this->line("  Settlement Status: {$breakdown['settlement_status']}");
                        $this->line("  Total Posted: {$breakdown['total_posted']}");
                        $this->line("  Balance: {$breakdown['balance']}");
                    } else {
                        $this->error("❌ Payment creation failed");
                        return 1;
                    }
                } else {
                    $this->info("ℹ️  Skipping actual payment creation");
                }
            } else {
                $this->info("ℹ️  Dry-run mode - no actual payment created");
            }

            $this->line("\n🎉 Integration test completed successfully!");
            IntegrationAuditLogger::logMachineryPaymentEvent('integration_test_completed', [
                'request_id' => $request->id,
                'dry_run' => $this->option('dry-run'),
                'success' => true,
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Integration test failed: " . $e->getMessage());
            $this->line("Error Class: " . get_class($e));
            
            IntegrationAuditLogger::logMachineryPaymentEvent('integration_test_failed', [
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

        // Find first locked request with positive payable
        return MachineryPaymentRequest::where('status', 'locked')
            ->where('net_payable', '>', 0)
            ->with('supplier')
            ->first();
    }

    /**
     * Prepare test payment data
     */
    protected function prepareTestPaymentData(float $maxAmount): array
    {
        // Use a reasonable test amount (not the full amount for safety)
        $testAmount = min(1000.00, $maxAmount * 0.5); // 50% of balance or ₹1000, whichever is less

        return [
            'amount' => $testAmount,
            'payment_mode' => 'bank_transfer',
            'payment_date' => now()->format('Y-m-d'),
            'reference_number' => 'TEST-' . time(),
            'notes' => 'Test payment from machinery integration',
        ];
    }
}
