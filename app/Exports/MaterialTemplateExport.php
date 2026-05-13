<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MaterialTemplateExport implements FromCollection, WithHeadings
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
                'name' => 'Cement OPC',
                'hsn_sac' => '2523',
                'gst_rate' => 28,
                'category' => 'Cement',
                'unit' => 'Bag',
                'description' => 'OPC Cement Grade 33/43/53',
                'price' => 350,
                'reorder_level' => 50,
                'status' => 'active',
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
            'hsn_sac',
            'gst_rate',
            'category',
            'unit',
            'description',
            'price',
            'reorder_level',
            'status',
        ];
    }
}
