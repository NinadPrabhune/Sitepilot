<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OpeningStockTemplateExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Fetch all materials with their unit information
        $materials = \App\Models\Material::with('unit')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return $materials->map(function ($material) {
            return [
                'material_id' => $material->id,
                'material_name' => $material->name,
                'unit' => $material->unit->name ?? 'N/A',
                'quantity' => '', // Empty for user to fill
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'material_id',
            'material_name',
            'unit',
            'quantity',
        ];
    }
}
