<?php

namespace App\Console\Commands;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Services\ERPIntegration\MachineryPaymentIntegrationService;
use Illuminate\Console\Command;

class MachineryTestSinglePayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:test-single-payment {requestId : Machinery payment request ID} {referenceNumber : Payment reference number} {paymentData : JSON encoded payment data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create single payment for concurrency testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $requestId = $this->argument('requestId');
        $referenceNumber = $this->argument('referenceNumber');
        $paymentDataJson = $this->argument('paymentData');
        $paymentData = json_decode($paymentDataJson, true);

        if (!$paymentData) {
            echo "ERROR: Invalid JSON data";
            exit(1);
        }

        try {
            $request = MachineryPaymentRequest::find($requestId);
            if (!$request) {
                echo "ERROR: Request not found";
                exit(1);
            }

            $service = app(MachineryPaymentIntegrationService::class);
            
            // Update reference number for this specific attempt
            $paymentData['reference_number'] = $referenceNumber;
            
            $result = $service->createPayment($request, $paymentData, false);
            
            echo "SUCCESS:" . $result['payment_id'] . ":" . $result['integration_reference'];
            exit(0);

        } catch (\Exception $e) {
            echo "ERROR:" . $e->getMessage();
            exit(1);
        }
    }
}
