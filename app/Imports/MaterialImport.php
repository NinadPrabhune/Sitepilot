<?php

namespace App\Imports;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Unit;
use App\Models\GstMaster;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MaterialImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $errors = [];
    protected $processedRows = 0;

    /**
     * Generate auto SKU for materials
     */
    private function generateSku()
    {
        $lastMaterial = Material::latest('id')->first();
        $nextNumber = $lastMaterial ? $lastMaterial->id + 1 : 1;
        return 'MAT-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $this->processedRows++;

        // Skip if name is empty
        if (empty($row['name'])) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Missing name";
            return null;
        }

        // Skip if category is empty
        if (empty($row['category'])) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Missing category";
            return null;
        }

        // Skip if unit is empty
        if (empty($row['unit'])) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Missing unit";
            return null;
        }

        // Find category by name
        $category = MaterialCategory::where('name', $row['category'])->first();
        if (!$category) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Category '{$row['category']}' not found";
            return null;
        }

        // Find unit by name
        $unit = Unit::where('name', $row['unit'])->first();
        if (!$unit) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Unit '{$row['unit']}' not found";
            return null;
        }

        // Find GST master by rate - skip if column doesn't exist
        $gstMasterId = null;
        if (!empty($row['gst_rate'])) {
            try {
                $gstMaster = GstMaster::where('total_gst', $row['gst_rate'])->first();
                if ($gstMaster) {
                    $gstMasterId = $gstMaster->id;
                }
            } catch (\Exception $e) {
                // Skip GST lookup if table/column doesn't exist
                Log::warning('GST lookup failed: ' . $e->getMessage());
            }
        }

        // Check for duplicate SKU
        if (!empty($row['sku'])) {
            $existingBySku = Material::where('sku', $row['sku'])->first();
            if ($existingBySku) {
                $this->skippedCount++;
                $this->errors[] = "Row {$this->processedRows}: Duplicate SKU '{$row['sku']}'";
                return null;
            }
        }

        // Check for duplicate name
        $existingByName = Material::where('name', $row['name'])->first();
        if ($existingByName) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Duplicate name '{$row['name']}'";
            return null;
        }

        // Validate status
        $status = !empty($row['status']) ? $row['status'] : 'active';
        if (!in_array($status, ['active', 'inactive'])) {
            $status = 'active';
        }

        // Auto-generate SKU (ignore if provided in import)
        $sku = $this->generateSku();

        // Create the material
        $this->importedCount++;

        return new Material([
            'sku' => $sku,
            'name' => $row['name'],
            'hsn_sac' => isset($row['hsn_sac']) ? (string)$row['hsn_sac'] : null,
            'gst_master_id' => $gstMasterId,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'description' => $row['description'] ?? null,
            'price' => $row['price'] ?? 0,
            'reorder_level' => $row['reorder_level'] ?? 10,
            'status' => $status,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'hsn_sac' => 'nullable|max:20',
            'gst_rate' => 'nullable|numeric',
            'category' => 'required|string',
            'unit' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric',
            'reorder_level' => 'nullable|integer',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Name is required',
            'category.required' => 'Category is required',
            'unit.required' => 'Unit is required',
            'sku.unique' => 'SKU already exists',
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
     * @return int
     */
    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
