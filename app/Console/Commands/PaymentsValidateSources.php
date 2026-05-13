<?php

namespace App\Console\Commands;

use App\Models\PaymentsModule;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Support\PaymentSources;
use App\Support\IntegrationAuditLogger;
use Illuminate\Console\Command;

class PaymentsValidateSources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:validate-sources {--fix : Attempt to fix orphaned records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate payment source linkages and detect orphaned records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Validating payment source linkages...');
        
        $issuesFound = 0;
        
        // Check 1: Invalid source types
        $this->line('Checking invalid source types...');
        $invalidSourceTypes = PaymentsModule::whereNotNull('source_type')
            ->whereNotIn('source_type', PaymentSources::getAll())
            ->get();
            
        foreach ($invalidSourceTypes as $payment) {
            $this->error("❌ Payment #{$payment->id} has invalid source_type: {$payment->source_type}");
            IntegrationAuditLogger::logInvalidSourceType($payment->source_type, [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
            ]);
            $issuesFound++;
        }
        
        // Check 2: Machinery payment request orphans
        $this->line('Checking machinery payment request orphans...');
        $machineryPayments = PaymentsModule::forMachineryPaymentRequest()->get();
        
        foreach ($machineryPayments as $payment) {
            $request = MachineryPaymentRequest::find($payment->source_id);
            
            if (!$request) {
                $this->error("❌ Payment #{$payment->id} references non-existent machinery payment request #{$payment->source_id}");
                IntegrationAuditLogger::logOrphanRecord('payment_without_request', [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'missing_request_id' => $payment->source_id,
                ]);
                $issuesFound++;
            }
        }
        
        // Check 3: Machinery payment requests with payment history but no source linkage
        $this->line('Checking machinery payment requests with unlinked payments...');
        $machineryRequests = MachineryPaymentRequest::with('payments')->get();
        
        foreach ($machineryRequests as $request) {
            $linkedPayments = $request->payments()->count();
            $expectedPayments = PaymentsModule::where('source_type', PaymentSources::MACHINERY_PAYMENT_REQUEST)
                ->where('source_id', $request->id)
                ->count();
                
            if ($linkedPayments !== $expectedPayments) {
                $this->warn("⚠️  Request #{$request->id} has {$linkedPayments} linked payments but {$expectedPayments} expected in DB");
                IntegrationAuditLogger::logOrphanRecord('payment_count_mismatch', [
                    'request_id' => $request->id,
                    'linked_count' => $linkedPayments,
                    'expected_count' => $expectedPayments,
                ]);
                $issuesFound++;
            }
        }
        
        // Summary
        if ($issuesFound === 0) {
            $this->info('✅ All payment source linkages are valid!');
            IntegrationAuditLogger::logMachineryPaymentEvent('source_validation_completed', [
                'issues_found' => 0,
                'status' => 'healthy',
            ]);
        } else {
            $this->error("❌ Found {$issuesFound} issues with payment source linkages");
            IntegrationAuditLogger::logMachineryPaymentEvent('source_validation_completed', [
                'issues_found' => $issuesFound,
                'status' => 'issues_detected',
            ]);
            
            if ($this->option('fix')) {
                $this->warn('🔧 Auto-fix not implemented yet. Please review issues manually.');
            }
        }
        
        return $issuesFound === 0 ? 0 : 1;
    }
}
