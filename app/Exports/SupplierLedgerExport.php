<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SupplierLedgerExport implements FromCollection, WithHeadings
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
                'Date' => $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : 'N/A',
                'Supplier' => $transaction->supplier ? $transaction->supplier->name : 'N/A',
                'Site/Project' => $transaction->site ? $transaction->site->name : 'N/A',
                'Reference Type' => ucfirst($transaction->reference_type),
                'Reference ID' => $transaction->reference_id ?? 'N/A',
                'Description' => $transaction->description ?? 'N/A',
                'Debit Amount' => number_format((float)$transaction->debit, 2, '.', ''),
                'Credit Amount' => number_format((float)$transaction->credit, 2, '.', ''),
                'Balance' => number_format((float)$transaction->balance, 2, '.', ''),
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
            'Supplier',
            'Site/Project',
            'Reference Type',
            'Reference ID',
            'Description',
            'Debit Amount',
            'Credit Amount',
            'Balance',
        ];
    }
}