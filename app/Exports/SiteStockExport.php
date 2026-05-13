<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SiteStockExport implements FromCollection, WithHeadings
{
    protected $stockReport;

    public function __construct($stockReport)
    {
        $this->stockReport = $stockReport;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->stockReport->map(function ($stock) {
            return [
                'Project' => $stock->project->name ?? 'N/A',
                'Material' => $stock->material->name ?? 'N/A',
                'Unit' => $stock->material->unit->name ?? 'N/A',
                'Current Stock' => $stock->current_stock,
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Project',
            'Material',
            'Unit',
            'Current Stock',
        ];
    }
}
