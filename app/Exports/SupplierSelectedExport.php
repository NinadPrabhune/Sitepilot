<?php

namespace App\Exports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Facades\Log;

class SupplierSelectedExport implements FromQuery, WithHeadings, WithMapping
{
    protected $ids;

    public function __construct(array $ids)
    {
        $this->ids = $ids;
        // Log::info("SupplierSelectedExport initialized with IDs:", $this->ids);
    }

    public function query()
    {
        // Log::info("Querying suppliers with IDs:", $this->ids);
        
        return Supplier::query()->whereIn('id', $this->ids);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Category',
            'Type',
            'Contact Person',
            'Phone',
            'Email',
            'Address',
            'City',
            'State',
            'Pincode',
            'Country',
            'Status'
        ];
    }

    public function map($supplier): array
    {
        return [
            $supplier->id,
            $supplier->name,
            optional($supplier->category)->name ?? '',
            $supplier->type,
            $supplier->contact_person,
            $supplier->phone,
            $supplier->email,
            $supplier->address,
            $supplier->city,
            $supplier->state,
            $supplier->pincode,
            $supplier->country,
            $supplier->status == 0 ? 'Active' : 'Inactive'
        ];
    }
}
