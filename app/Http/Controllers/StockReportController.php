<?php

namespace App\Http\Controllers;

use App\DataTables\StockReportDataTable;

class StockReportController extends Controller
{
    public function index(StockReportDataTable $dataTable)
    {
        
        
        if (!\Auth::user()->isAbleTo('stock-report manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
            return $dataTable->render('stock-reports.index');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
