<?php

namespace App\Http\Controllers;

use App\Services\StockService;
use Illuminate\Http\Request;
use App\Models\Material;
use Workdo\Taskly\Entities\Project;

class SiteStockController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display the site stock report.
     *
     * @authenticated
     * @queryParam project_id integer Filter by project (site) ID. Example: 1
     * @queryParam material_id integer Filter by material ID. Example: 1
     * @response view="site-stock.index"
     */
    public function index(Request $request)
    {
        if (\Auth::user()->isAbleTo('site-stock manage')) {
            $projects = Project::where('workspace', getActiveWorkSpace())
                ->projectonly()
                ->get()
                ->pluck('name', 'id');
            
            $materials = Material::where('status', 'active')             
                ->pluck('name', 'id');

            $filters = [
                'project_id' => $request->project_id,
                'material_id' => $request->material_id,
            ];

            $stockReport = $this->stockService->getStockReport(
                $request->project_id,
                $request->material_id
            );

            return view('site-stock.index', compact('projects', 'materials', 'stockReport', 'filters'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Export the site stock report to Excel.
     *
     * @authenticated
     * @queryParam project_id integer Filter by project (site) ID. Example: 1
     * @queryParam material_id integer Filter by material ID. Example: 1
     * @response file="site_stock_report_*.xlsx"
     */
    public function export(Request $request)
    {
        if (\Auth::user()->isAbleTo('site-stock manage')) {
            $stockReport = $this->stockService->getStockReport(
                $request->project_id,
                $request->material_id
            );

            $fileName = 'site_stock_report_' . date('Ymd_His') . '.xlsx';

            return \Maatwebsite\Excel\Excel::download(
                new \App\Exports\SiteStockExport($stockReport),
                $fileName
            );
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
