<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SupplierTemplateExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Return an empty collection with example data
        // This is just for the template example row
        return collect([
            [
                'name' => 'ABC Suppliers Pvt Ltd',
                'category' => 'Subcontractors',
                'type' => 'individual',
                'contact_person' => 'Rahul Sharma',
                'phone' => '9876543210',
                'email' => 'abc@gmail.com',
                'address' => 'Pune MIDC',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411001',
                'country' => 'India',
                'gst_number' => '27ABCDE1234F1Z5',
                'pan_number' => 'ABCDE1234F',
                'registration_number' => 'ABC123456',
                'bank_name' => 'HDFC Bank',
                'account_number' => '123456789',
                'ifsc_code' => 'HDFC0001234',
                'payment_terms' => '30 Days',
                'is_active' => 1,
            ]
        ]);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'name',
            'category',
            'type',
            'contact_person',
            'phone',
            'email',
            'address',
            'city',
            'state',
            'pincode',
            'country',
            'gst_number',
            'pan_number',
            'registration_number',
            'bank_name',
            'account_number',
            'ifsc_code',
            'payment_terms',
            'is_active',
        ];
    }
}
