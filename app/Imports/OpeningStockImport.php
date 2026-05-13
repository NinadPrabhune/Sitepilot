<?php

namespace App\Imports;

use App\Models\Material;
use App\Models\StockTransaction;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Validation\Rule;

class OpeningStockImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $projectId;
    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $errors = [];

    public function __construct($projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Skip rows with empty quantity
        if (empty($row['quantity'])) {
            $this->skippedCount++;
            return null;
        }

        // Validate material_id exists
        $material = Material::find($row['material_id']);
        if (!$material) {
            $this->skippedCount++;
            $this->errors[] = "Material ID {$row['material_id']} not found";
            return null;
        }

        // Check for duplicate opening stock for this project + material
        $existingStock = StockTransaction::where('type', 'opening')
            ->where('project_id', $this->projectId)
            ->where('material_id', $row['material_id'])
            ->exists();

        if ($existingStock) {
            $this->skippedCount++;
            $this->errors[] = "Opening stock already exists for material ID {$row['material_id']} in this project";
            return null;
        }

        // Create stock transaction
        $this->importedCount++;

        return new StockTransaction([
            'type' => 'opening',
            'project_id' => $this->projectId,
            'material_id' => $row['material_id'],
            'quantity' => $row['quantity'],
            'rate' => 0,
            'reference_type' => null,
            'reference_id' => null,
            'remarks' => 'Opening Stock Import',
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'material_id' => 'required|integer|exists:materials,id',
            'material_name' => 'nullable|string',
            'unit' => 'nullable|string',
            'quantity' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages(): array
    {
        return [
            'material_id.required' => 'Material ID is required',
            'material_id.exists' => 'Material ID does not exist',
            'quantity.numeric' => 'Quantity must be a number',
            'quantity.min' => 'Quantity must be greater than or equal to 0',
        ];
    }

    /**
     * @return int
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * @return int
     */
    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
