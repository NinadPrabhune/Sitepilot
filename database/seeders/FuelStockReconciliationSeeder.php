<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MaterialProjectStock;
use App\Services\StockService;

class FuelStockReconciliationSeeder extends Seeder
{
    /**
     * Run the database seeds to reconcile fuel stock gaps.
     */
    public function run()
    {
        $this->command->info('Starting fuel stock reconciliation...');
        
        try {
            // Get all fuel materials (category_id = 2)
            $fuelMaterials = DB::table('materials')
                ->where('category_id', 2)
                ->pluck('id')
                ->toArray();
            
            if (empty($fuelMaterials)) {
                $this->command->info('No fuel materials found (category_id = 2)');
                return;
            }
            
            $this->command->info('Found ' . count($fuelMaterials) . ' fuel materials');
            
            // Get all sites/projects
            $sites = DB::table('projects')->pluck('id')->toArray();
            
            $discrepancies = [];
            $adjustments = [];
            
            foreach ($sites as $siteId) {
                foreach ($fuelMaterials as $materialId) {
                    // Get calculated stock using getCurrentStockBySiteId
                    $calculatedStock = $this->getCalculatedStock($siteId, $materialId);
                    
                    // Get actual stock from MaterialProjectStock
                    $actualStock = MaterialProjectStock::getCurrentStock($siteId, $materialId);
                    
                    // Check for discrepancy
                    $difference = $calculatedStock - $actualStock;
                    
                    if (abs($difference) > 0.01) { // Only flag significant differences
                        $discrepancies[] = [
                            'site_id' => $siteId,
                            'material_id' => $materialId,
                            'calculated_stock' => $calculatedStock,
                            'actual_stock' => $actualStock,
                            'difference' => $difference
                        ];
                        
                        // Prepare adjustment if needed
                        if (abs($difference) > 0.01) {
                            $adjustments[] = [
                                'site_id' => $siteId,
                                'material_id' => $materialId,
                                'adjustment_amount' => $difference,
                                'reason' => 'Stock reconciliation - fuel consumption gap fix'
                            ];
                        }
                    }
                }
            }
            
            // Report discrepancies
            if (!empty($discrepancies)) {
                $this->command->warn('Found ' . count($discrepancies) . ' stock discrepancies:');
                
                foreach ($discrepancies as $discrepancy) {
                    $materialName = DB::table('materials')
                        ->where('id', $discrepancy['material_id'])
                        ->value('name');
                    
                    $siteName = DB::table('projects')
                        ->where('id', $discrepancy['site_id'])
                        ->value('name');
                    
                    $this->command->line(sprintf(
                        'Site: %s, Material: %s | Calculated: %.2f, Actual: %.2f, Diff: %.2f',
                        $siteName ?? 'Unknown',
                        $materialName ?? 'Unknown',
                        $discrepancy['calculated_stock'],
                        $discrepancy['actual_stock'],
                        $discrepancy['difference']
                    ));
                }
                
                // Ask if user wants to apply adjustments
                if ($this->command->confirm('Apply stock adjustments to fix discrepancies?')) {
                    $this->applyStockAdjustments($adjustments);
                }
            } else {
                $this->command->info('No stock discrepancies found. All fuel stocks are consistent.');
            }
            
            // Generate reconciliation report
            $this->generateReconciliationReport($discrepancies);
            
        } catch (\Exception $e) {
            $this->command->error('Error during stock reconciliation: ' . $e->getMessage());
            Log::error('Fuel stock reconciliation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        $this->command->info('Fuel stock reconciliation completed.');
    }
    
    /**
     * Get calculated stock using the same logic as getCurrentStockBySiteId
     */
    private function getCalculatedStock($siteId, $materialId)
    {
        // Purchases
        $purchased = DB::table('purchase_invoice_items as pii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
            ->where('pi.site_id', $siteId)
            ->where('pii.material_id', $materialId)
            ->select(DB::raw('SUM(pii.quantity) as purchased_qty'))
            ->value('purchased_qty') ?? 0;
        
        // Transfers OUT
        $transferredOut = DB::table('material_transfer_items as mti')
            ->join('material_transfers as mt', 'mt.id', '=', 'mti.material_transfer_id')
            ->where('mt.from_site_id', $siteId)
            ->where('mti.material_id', $materialId)
            ->select(DB::raw('SUM(mti.quantity) as transferred_out_qty'))
            ->value('transferred_out_qty') ?? 0;
        
        // Transfers IN
        $transferredIn = DB::table('material_transfer_items as mti')
            ->join('material_transfers as mt', 'mt.id', '=', 'mti.material_transfer_id')
            ->where('mt.to_site_id', $siteId)
            ->where('mti.material_id', $materialId)
            ->select(DB::raw('SUM(mti.quantity) as transferred_in_qty'))
            ->value('transferred_in_qty') ?? 0;
        
        // Consumption
        $consumed = DB::table('daily_consumption_details as dcd')
            ->join('daily_consumption_masters as dc', 'dc.id', '=', 'dcd.daily_consumption_master_id')
            ->where('dc.site_id', $siteId)
            ->where('dcd.material_id', $materialId)
            ->select(DB::raw('SUM(dcd.quantity) as consumed_qty'))
            ->value('consumed_qty') ?? 0;
        
        // Calculate available stock
        $availableStock = max(0, $purchased + $transferredIn - $transferredOut - $consumed);
        
        return $availableStock;
    }
    
    /**
     * Apply stock adjustments using StockService
     */
    private function applyStockAdjustments($adjustments)
    {
        $stockService = new StockService();
        
        foreach ($adjustments as $adjustment) {
            try {
                $materialName = DB::table('materials')
                    ->where('id', $adjustment['material_id'])
                    ->value('name');
                
                $siteName = DB::table('projects')
                    ->where('id', $adjustment['site_id'])
                    ->value('name');
                
                $this->command->info(sprintf(
                    'Adjusting stock for Site: %s, Material: %s by %.2f',
                    $siteName ?? 'Unknown',
                    $materialName ?? 'Unknown',
                    $adjustment['adjustment_amount']
                ));
                
                // Create stock adjustment transaction
                $stockService->adjustStock(
                    $adjustment['site_id'],
                    $adjustment['material_id'],
                    $adjustment['adjustment_amount'],
                    $adjustment['reason']
                );
                
                $this->command->info('✓ Adjustment applied successfully');
                
            } catch (\Exception $e) {
                $this->command->error('Failed to apply adjustment: ' . $e->getMessage());
                Log::error('Stock adjustment failed', [
                    'adjustment' => $adjustment,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Generate reconciliation report
     */
    private function generateReconciliationReport($discrepancies)
    {
        $reportData = [
            'generated_at' => now()->toISOString(),
            'total_discrepancies' => count($discrepancies),
            'discrepancies' => $discrepancies
        ];
        
        $reportPath = storage_path('logs/fuel_stock_reconciliation_' . date('Y-m-d_H-i-s') . '.json');
        file_put_contents($reportPath, json_encode($reportData, JSON_PRETTY_PRINT));
        
        $this->command->info('Reconciliation report saved to: ' . $reportPath);
    }
}
