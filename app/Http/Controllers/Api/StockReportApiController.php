<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Stock Report
 * Endpoints for stock report data retrieval with filtering options
 */
use Illuminate\Http\Request;

class StockReportApiController extends Controller
{
    /**
     * Return stock report data as JSON for Flutter app.
     */
    public function index(Request $request)
    {
        try {
            // ✅ Accept site_id from Flutter app request
            $siteId = $request->input('site_id');
            if (empty($siteId)) {
                $siteId = getActiveProject(); // fallback to active project
            }

            // ✅ Accept date filters (optional)
            $startDate = $request->input('start_date'); // can be null
            $endDate   = $request->input('end_date');   // can be null

            // ✅ Accept material_id (optional)
            $materialId = $request->input('material_id'); // can be null

            // Fetch stock data
            $stockData = getCurrentStockBySiteId(
                $siteId,
                null, // excludeConsumptionId
                null, // excludeMaterialTransferId
                $startDate,
                $endDate,
                $materialId
            );

            return response()->json([
                'status'     => true,
                'message'    => 'Stock report fetched successfully',
                'site_id'    => $siteId,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'material_id'=> $materialId,
                'data'       => $stockData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error fetching stock report',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
