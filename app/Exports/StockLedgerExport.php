<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockLedgerExport implements FromCollection, WithHeadings
{
    protected $transactions;

    public function __construct($transactions)
    {
        $this->transactions = $transactions;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->transactions->map(function ($transaction) {
            return [
                'Date' => $transaction->created_at->format('Y-m-d H:i:s'),
                'Project' => $transaction->project->name ?? 'N/A',
                'Material' => $transaction->material->name ?? 'N/A',
                'Type' => $transaction->type_label,
                'Quantity' => $transaction->quantity,
                'Rate' => $transaction->rate ?? 'N/A',
                'Reference' => $transaction->reference_type ? $transaction->reference_type . ' #' . $transaction->reference_id : 'N/A',
                'Remarks' => $transaction->remarks ?? 'N/A',
                'Created By' => $transaction->creator->name ?? 'N/A',
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Date',
            'Project',
            'Material',
            'Type',
            'Quantity',
            'Rate',
            'Reference',
            'Remarks',
            'Created By',
        ];
    }
}
