<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Models\Machinery;
use App\Models\DailyProgressReport;
use App\Services\MachineryBillingCalculatorService;
use App\Services\MachineryDieselAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackfillPaymentRequestBreakdowns extends Seeder
{
    /**
     * Run the database seeds for payment request breakdown backfill.
     */
    public function run(): void
    {
        Log::info('Starting payment request breakdown backfill');
        
        $backfilledCount = 0;
        $errors = [];
        $batchSize = 100;
        
        try {
            // Get payment requests that need breakdown backfill
            $totalRequests = MachineryPaymentRequest::whereNull('gross_amount')->count();
            Log::info("Found {$totalRequests} payment requests to backfill");
            
            if ($totalRequests === 0) {
                Log::info('No payment requests require backfill');
                return;
            }
            
            // Process in batches to avoid memory issues
            $processed = 0;
            while ($processed < $totalRequests) {
                DB::transaction(function () use (&$backfilledCount, &$errors, &$processed, $batchSize) {
                    $requests = MachineryPaymentRequest::whereNull('gross_amount')
                        ->with(['machinery'])
                        ->take($batchSize)
                        ->get();
                    
                    foreach ($requests as $request) {
                        try {
                            $this->backfillSingleRequest($request);
                            $backfilledCount++;
                            $processed++;
                            
                            if ($backfilledCount % 50 === 0) {
                                Log::info("Backfilled {$backfilledCount} payment requests");
                            }
                            
                        } catch (\Exception $e) {
                            $errors[] = [
                                'payment_request_id' => $request->id,
                                'error' => $e->getMessage()
                            ];
                            Log::error("Failed to backfill payment request {$request->id}", [
                                'error' => $e->getMessage()
                            ]);
                            $processed++; // Still count as processed to avoid infinite loop
                        }
                    }
                });
                
                // Small delay to prevent database overload
                usleep(100000); // 0.1 seconds
            }
            
            Log::info("Payment request breakdown backfill completed. Total backfilled: {$backfilledCount}");
            
            if (!empty($errors)) {
                Log::warning('Backfill completed with errors', ['error_count' => count($errors), 'errors' => $errors]);
            }
            
            // Validate backfill results
            $this->validateBackfillResults();
            
        } catch (\Exception $e) {
            Log::error('Payment request breakdown backfill failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Backfill a single payment request with breakdown data
     */
    private function backfillSingleRequest(MachineryPaymentRequest $request): void
    {
        $machinery = $request->machinery;
        
        if (!$machinery) {
            throw new \Exception("Machinery not found for payment request {$request->id}");
        }
        
        $from = Carbon::parse($request->period_start);
        $to = Carbon::parse($request->period_end);
        
        // Get DPRs for the period
        $dprs = DailyProgressReport::where('machinery_id', $machinery->id)
            ->whereBetween('date', [$from, $to])
            ->get();
        
        // Calculate billing using centralized service
        $billingResult = MachineryBillingCalculatorService::calculate($machinery, $dprs, $from, $to);
        
        // Calculate diesel deduction
        $dieselResult = MachineryDieselAdjustmentService::calculateDieselDeduction($machinery, $from, $to);
        
        $grossAmount = $billingResult['gross_amount'];
        $dieselDeduction = $dieselResult['applicable_for_deduction'] ? $dieselResult['total_cost'] : 0;
        
        // Validate calculation matches existing net payable (within small tolerance)
        $expectedNetPayable = $grossAmount - $dieselDeduction;
        $existingNetPayable = $request->net_payable;
        
        if (abs($expectedNetPayable - $existingNetPayable) > 0.01) {
            Log::warning("Calculation mismatch for payment request {$request->id}", [
                'existing_net_payable' => $existingNetPayable,
                'calculated_net_payable' => $expectedNetPayable,
                'difference' => abs($expectedNetPayable - $existingNetPayable)
            ]);
            
            // Use existing net payable for consistency, but store breakdown
            $grossAmount = $existingNetPayable + $dieselDeduction;
        }
        
        // Update payment request with breakdown data
        $request->update([
            'gross_amount' => $grossAmount,
            'diesel_deduction' => $dieselDeduction,
            'calculation_method' => $billingResult['calculation_type'],
            'billing_breakdown' => $billingResult,
            'diesel_breakdown' => $dieselResult
        ]);
    }
    
    /**
     * Validate backfill results
     */
    private function validateBackfillResults(): void
    {
        Log::info('Validating backfill results');
        
        // Check for any remaining null breakdown fields
        $nullGrossAmount = MachineryPaymentRequest::whereNull('gross_amount')->count();
        $nullCalculationMethod = MachineryPaymentRequest::whereNull('calculation_method')->count();
        
        if ($nullGrossAmount > 0) {
            Log::error("Found {$nullGrossAmount} payment requests with null gross_amount after backfill");
        }
        
        if ($nullCalculationMethod > 0) {
            Log::error("Found {$nullCalculationMethod} payment requests with null calculation_method after backfill");
        }
        
        // Validate calculation consistency
        $inconsistentCalculations = DB::select("
            SELECT COUNT(*) as inconsistent_count
            FROM machinery_payment_requests
            WHERE gross_amount IS NOT NULL
            AND diesel_deduction IS NOT NULL
            AND ABS((gross_amount - diesel_deduction) - net_payable) > 0.01
        ")[0]->inconsistent_count;
        
        if ($inconsistentCalculations > 0) {
            Log::error("Found {$inconsistentCalculations} payment requests with inconsistent calculations");
        }
        
        // Summary statistics
        $summary = DB::select("
            SELECT 
                calculation_method,
                COUNT(*) as count,
                AVG(gross_amount) as avg_gross_amount,
                AVG(diesel_deduction) as avg_diesel_deduction,
                AVG(net_payable) as avg_net_payable
            FROM machinery_payment_requests
            WHERE gross_amount IS NOT NULL
            GROUP BY calculation_method
            ORDER BY count DESC
        ");
        
        Log::info('Backfill summary by calculation method', [
            'summary' => $summary
        ]);
        
        Log::info('Backfill validation completed');
    }
    
    /**
     * Clean up backfill data (for rollback purposes)
     */
    public function cleanup(): void
    {
        Log::info('Cleaning up backfilled breakdown data');
        
        try {
            $clearedCount = MachineryPaymentRequest::whereNotNull('gross_amount')
                ->update([
                    'gross_amount' => null,
                    'diesel_deduction' => null,
                    'calculation_method' => null,
                    'billing_breakdown' => null,
                    'diesel_breakdown' => null
                ]);
            
            Log::info("Cleared breakdown data from {$clearedCount} payment requests");
            
        } catch (\Exception $e) {
            Log::error('Cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
