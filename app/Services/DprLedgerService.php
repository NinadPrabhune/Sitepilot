<?php

namespace App\Services;

use App\Models\DailyProgressReport;
use App\Domain\Machinery\Models\MachineryLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DprLedgerService
{
    /**
     * Create ledger entry from DPR - TRANSACTIONAL + IDEMPOTENT
     */
    public function createFromDpr(DailyProgressReport $dpr): MachineryLedger
    {
        return DB::transaction(function () use ($dpr) {
            // Generate idempotency key
            $idempotencyKey = "dpr_{$dpr->id}_operational";
            
            // Check idempotency (strong check)
            $existing = MachineryLedger::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                Log::info('Ledger entry already exists (idempotency)', [
                    'dpr_id' => $dpr->id,
                    'ledger_id' => $existing->id
                ]);
                return $existing;
            }
            
            // Calculate with snapshot
            $calculation = $this->calculateAmount($dpr);
            
            $ledger = MachineryLedger::create([
                'machinery_id' => $dpr->machinery_id,
                'amount' => $calculation['amount'],
                'entry_direction' => 'credit',
                'entry_type' => 'dpr_operational',
                'source_type' => $dpr->source_type,
                'entry_source' => 'dpr',
                'entry_source_id' => $dpr->id,
                'reference_type' => 'DailyProgressReport',
                'reference_id' => $dpr->id,
                'date' => $dpr->date,
                'workspace_id' => $dpr->workspace_id,
                'site_id' => $dpr->site_id,
                'is_settled' => false,
                'is_reversed' => false,
                'idempotency_key' => $idempotencyKey,
                'calculation_snapshot' => $calculation['snapshot'],
                'description' => "DPR for {$dpr->machinery?->name} on {$dpr->date->format('Y-m-d')}",
            ]);
            
            Log::info('Ledger entry created from DPR', [
                'dpr_id' => $dpr->id,
                'ledger_id' => $ledger->id,
                'idempotency_key' => $idempotencyKey,
                'source_type' => $dpr->source_type,
                'amount' => $calculation['amount'],
            ]);
            
            return $ledger;
        });
    }
    
    /**
     * Calculate payable amount from DPR with full snapshot
     */
    protected function calculateAmount(DailyProgressReport $dpr): array
    {
        $hours = ($dpr->machine_end_reading - $dpr->machine_start_reading - ($dpr->machine_idle_reading ?? 0));
        $rate = $dpr->machinery?->rental_rate ?? $dpr->machinery?->rate_per_hour ?? 0;
        $dieselConsumption = $dpr->diesel_consumption ?? 0;
        $dieselRate = config('machinery.diesel_rate', 80);
        $dieselCost = $dieselConsumption * $dieselRate;
        
        $operationalCost = $hours * $rate;
        $totalAmount = $operationalCost + $dieselCost;
        
        return [
            'amount' => max(0, $totalAmount),
            'snapshot' => [
                'hours' => $hours,
                'rate' => $rate,
                'operational_cost' => $operationalCost,
                'diesel_consumption' => $dieselConsumption,
                'diesel_rate' => $dieselRate,
                'diesel_cost' => $dieselCost,
                'total_amount' => $totalAmount,
                'calculated_at' => now()->toDateTimeString(),
            ]
        ];
    }
    
    /**
     * Mark ledger entry as settled
     */
    public function markAsSettled(int $ledgerId): void
    {
        MachineryLedger::where('id', $ledgerId)->update([
            'is_settled' => true,
        ]);
    }
    
    /**
     * Lock entries to payment request
     */
    public function lockToPaymentRequest(array $ledgerIds, int $paymentRequestId): void
    {
        DB::transaction(function () use ($ledgerIds, $paymentRequestId) {
            // Lock rows and verify availability
            $entries = MachineryLedger::whereIn('id', $ledgerIds)
                ->availableForPayment()
                ->lockForUpdate()
                ->get();
            
            if ($entries->count() !== count($ledgerIds)) {
                throw new \RuntimeException('Some ledger entries are already locked or settled');
            }
            
            // Update all at once
            MachineryLedger::whereIn('id', $ledgerIds)->update([
                'payment_request_id' => $paymentRequestId,
            ]);
        });
    }
}
