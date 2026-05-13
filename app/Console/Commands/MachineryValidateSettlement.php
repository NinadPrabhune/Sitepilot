<?php

namespace App\Console\Commands;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Support\IntegrationAuditLogger;
use Illuminate\Console\Command;

class MachineryValidateSettlement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:validate-settlement {--fix : Attempt to fix settlement drift issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate settlement status calculations and detect drift';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Machinery Settlement Validation (Phase B1.5)');
        $this->line('Checking for settlement drift and calculation integrity...');
        $this->line('=====================================================');

        $issuesFound = 0;
        $totalRequests = 0;

        try {
            $requests = MachineryPaymentRequest::with('payments')->get();
            $totalRequests = $requests->count();

            $this->line("Processing {$totalRequests} machinery payment requests...\n");

            foreach ($requests as $request) {
                $issues = $this->validateRequestSettlement($request);
                $issuesFound += count($issues);

                if (!empty($issues)) {
                    $this->error("❌ Request #{$request->id} has issues:");
                    foreach ($issues as $issue) {
                        $this->line("  - {$issue}");
                    }
                    $this->line('');
                }
            }

            // Summary
            if ($issuesFound === 0) {
                $this->info("✅ All settlement calculations are correct!");
                $this->line("✅ No drift detected in {$totalRequests} requests");
                
                IntegrationAuditLogger::logMachineryPaymentEvent('settlement_validation_completed', [
                    'total_requests' => $totalRequests,
                    'issues_found' => 0,
                    'status' => 'healthy',
                ]);
            } else {
                $this->error("❌ Found {$issuesFound} settlement issues in {$totalRequests} requests");
                
                IntegrationAuditLogger::logMachineryPaymentEvent('settlement_validation_completed', [
                    'total_requests' => $totalRequests,
                    'issues_found' => $issuesFound,
                    'status' => 'issues_detected',
                ]);

                if ($this->option('fix')) {
                    $this->warn('🔧 Auto-fix not implemented yet. Please review issues manually.');
                }
            }

            // Payment State Isolation Validation
            $this->validatePaymentStateIsolation();

            return $issuesFound === 0 ? 0 : 1;

        } catch (\Exception $e) {
            $this->error("❌ Settlement validation failed: " . $e->getMessage());
            
            IntegrationAuditLogger::logMachineryPaymentEvent('settlement_validation_failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            return 1;
        }
    }

    /**
     * Validate individual request settlement calculation
     */
    protected function validateRequestSettlement(MachineryPaymentRequest $request): array
    {
        $issues = [];

        // Check 1: Settlement status calculation
        $computedStatus = $request->settlement_status;
        $totalPosted = $request->payments()->posted()->sum('amount');
        $netPayable = $request->net_payable;

        $expectedStatus = $this->calculateExpectedStatus($totalPosted, $netPayable);

        if ($computedStatus !== $expectedStatus) {
            $issues[] = "Settlement status mismatch: computed='{$computedStatus}', expected='{$expectedStatus}'";
        }

        // Check 2: Precision issues
        if (bccomp($totalPosted, $netPayable, 2) > 0) {
            $issues[] = "Overpayment detected: posted={$totalPosted}, payable={$netPayable}";
        }

        // Check 3: Cached vs actual totals (if cache fields exist)
        if (isset($request->paid_amount_cached)) {
            $cachedTotal = $request->paid_amount_cached;
            if (bccomp($cachedTotal, $totalPosted, 2) !== 0) {
                $issues[] = "Cached total drift: cached={$cachedTotal}, actual={$totalPosted}";
            }
        }

        // Check 4: Negative balances
        if ($totalPosted < 0) {
            $issues[] = "Negative posted total: {$totalPosted}";
        }

        return $issues;
    }

    /**
     * Calculate expected settlement status
     */
    protected function calculateExpectedStatus(float $totalPosted, float $netPayable): string
    {
        if ($totalPosted == 0) return 'unpaid';
        if (bccomp($totalPosted, $netPayable, 2) < 0) return 'partial';
        if (bccomp($totalPosted, $netPayable, 2) === 0) return 'paid';
        return 'overpaid';
    }

    /**
     * Validate payment state isolation
     */
    protected function validatePaymentStateIsolation(): void
    {
        $this->line("\n🔍 Validating Payment State Isolation...");
        
        $request = MachineryPaymentRequest::with('payments')->first();
        
        if (!$request) {
            $this->warn("⚠️  No machinery payment requests found for state isolation test");
            return;
        }

        $paymentStates = [
            'draft' => $request->payments()->where('status', 'draft')->sum('amount'),
            'posted' => $request->payments()->where('status', 'posted')->sum('amount'),
            'cancelled' => $request->payments()->where('status', 'cancelled')->sum('amount'),
            'reversed' => $request->payments()->where('status', 'reversed')->sum('amount'),
            'failed' => $request->payments()->where('status', 'failed')->sum('amount'),
        ];

        $this->line("Payment State Impact Test:");
        $this->line("  Draft payments: {$paymentStates['draft']} (should NOT affect settlement)");
        $this->line("  Posted payments: {$paymentStates['posted']} (SHOULD affect settlement)");
        $this->line("  Cancelled payments: {$paymentStates['cancelled']} (should NOT affect settlement)");
        $this->line("  Reversed payments: {$paymentStates['reversed']} (should NOT affect settlement)");
        $this->line("  Failed payments: {$paymentStates['failed']} (should NOT affect settlement)");

        // Verify only posted payments affect settlement
        $settlementTotal = $request->payments()->posted()->sum('amount');
        $expectedSettlementStatus = $this->calculateExpectedStatus($settlementTotal, $request->net_payable);
        $actualSettlementStatus = $request->settlement_status;

        if ($actualSettlementStatus === $expectedSettlementStatus) {
            $this->info("✅ Payment state isolation working correctly");
            $this->line("  Only posted payments affect settlement status");
        } else {
            $this->error("❌ Payment state isolation failed");
            $this->line("  Expected: {$expectedSettlementStatus}, Got: {$actualSettlementStatus}");
        }
    }
}
