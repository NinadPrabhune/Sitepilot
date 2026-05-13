<?php

if (!function_exists('validateStockConsistency')) {

    /**
     * Validate stock consistency between calculation method and MaterialProjectStock
     * 
     * @param int $siteId
     * @param int|null $materialId
     * @return array - Returns discrepancies found
     */
    function validateStockConsistency($siteId, $materialId = null)
    {
        $discrepancies = [];
        
        // Get stock using calculation method
        $calculatedStocks = getCurrentStockBySiteId(
            $siteId, 
            null, null, null, null, 
            $materialId, 
            false // Use calculation method
        );
        
        // Get stock from MaterialProjectStock
        $actualStocks = getCurrentStockBySiteId(
            $siteId, 
            null, null, null, null, 
            $materialId, 
            true // Use MaterialProjectStock
        );
        
        // Compare both results
        foreach ($calculatedStocks as $calculated) {
            $actual = $actualStocks->firstWhere('material_id', $calculated->material_id);
            $actualQty = $actual ? $actual->total_qty : 0;
            
            $difference = abs($calculated->total_qty - $actualQty);
            
            if ($difference > 0.01) { // Only flag significant differences
                $discrepancies[] = [
                    'material_id' => $calculated->material_id,
                    'material_name' => $calculated->material_name,
                    'calculated_stock' => $calculated->total_qty,
                    'actual_stock' => $actualQty,
                    'difference' => $difference
                ];
            }
        }
        
        return $discrepancies;
    }
}

if (!function_exists('syncMaterialProjectStock')) {

    /**
     * Sync MaterialProjectStock with calculated values
     * 
     * @param int $siteId
     * @param int|null $materialId
     * @param bool $dryRun - If true, only returns what would be updated
     * @return array - Returns sync results
     */
    function syncMaterialProjectStock($siteId, $materialId = null, $dryRun = false)
    {
        $results = [];
        $stockService = new \App\Services\StockService();
        
        // Get calculated stock values
        $calculatedStocks = getCurrentStockBySiteId(
            $siteId, 
            null, null, null, null, 
            $materialId, 
            false // Use calculation method
        );
        
        foreach ($calculatedStocks as $calculated) {
            $actualStock = \App\Models\MaterialProjectStock::getCurrentStock($siteId, $calculated->material_id);
            $difference = $calculated->total_qty - $actualStock;
            
            if (abs($difference) > 0.01) { // Only sync significant differences
                $result = [
                    'material_id' => $calculated->material_id,
                    'material_name' => $calculated->material_name,
                    'current_stock' => $actualStock,
                    'target_stock' => $calculated->total_qty,
                    'adjustment_needed' => $difference,
                    'action' => $dryRun ? 'WOULD_SYNC' : 'SYNCED'
                ];
                
                if (!$dryRun) {
                    try {
                        // Create stock adjustment to sync the values
                        $stockService->adjustStock(
                            $siteId,
                            $calculated->material_id,
                            $difference,
                            'Stock synchronization - fuel consumption gap fix'
                        );
                        
                        $result['success'] = true;
                    } catch (\Exception $e) {
                        $result['success'] = false;
                        $result['error'] = $e->getMessage();
                    }
                }
                
                $results[] = $result;
            }
        }
        
        return $results;
    }
}

if (!function_exists('getFuelStockReport')) {

    /**
     * Generate comprehensive fuel stock report for a site
     * 
     * @param int $siteId
     * @return array
     */
    function getFuelStockReport($siteId)
    {
        // Get all fuel materials (category_id = 2)
        $fuelMaterials = DB::table('materials')
            ->where('category_id', 2)
            ->pluck('id')
            ->toArray();
        
        if (empty($fuelMaterials)) {
            return [
                'site_id' => $siteId,
                'fuel_materials_count' => 0,
                'materials' => [],
                'discrepancies' => []
            ];
        }
        
        // Get stock data for fuel materials only
        $calculatedStocks = getCurrentStockBySiteId(
            $siteId, 
            null, null, null, null, 
            null, 
            false // Use calculation method
        )->filter(function($item) use ($fuelMaterials) {
            return in_array($item->material_id, $fuelMaterials);
        });
        
        $actualStocks = getCurrentStockBySiteId(
            $siteId, 
            null, null, null, null, 
            null, 
            true // Use MaterialProjectStock
        )->filter(function($item) use ($fuelMaterials) {
            return in_array($item->material_id, $fuelMaterials);
        });
        
        $materials = [];
        $discrepancies = [];
        
        foreach ($calculatedStocks as $calculated) {
            $actual = $actualStocks->firstWhere('material_id', $calculated->material_id);
            $actualQty = $actual ? $actual->total_qty : 0;
            $difference = $calculated->total_qty - $actualQty;
            
            $materialData = [
                'material_id' => $calculated->material_id,
                'material_name' => $calculated->material_name,
                'category_name' => $calculated->category_name,
                'unit_name' => $calculated->unit_name,
                'calculated_stock' => $calculated->total_qty,
                'actual_stock' => $actualQty,
                'difference' => $difference,
                'is_consistent' => abs($difference) <= 0.01
            ];
            
            $materials[] = $materialData;
            
            if (!$materialData['is_consistent']) {
                $discrepancies[] = $materialData;
            }
        }
        
        return [
            'site_id' => $siteId,
            'site_name' => DB::table('projects')->where('id', $siteId)->value('name'),
            'fuel_materials_count' => count($materials),
            'consistent_materials_count' => count($materials) - count($discrepancies),
            'discrepancies_count' => count($discrepancies),
            'materials' => $materials,
            'discrepancies' => $discrepancies,
            'generated_at' => now()->toISOString()
        ];
    }
}
