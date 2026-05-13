<?php

namespace App\Imports;

use App\Models\Supplier;
use App\Models\SupplierCategory;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class SupplierImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading
{
    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $errors = [];
    protected $processedRows = 0;

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

        // Find category by name
        $category = SupplierCategory::where('name', $row['category'])->first();
        if (!$category) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Category '{$row['category']}' not found";
            return null;
        }

        // Check for duplicate (same name + phone)
        $phone = !empty($row['phone']) ? $row['phone'] : null;
        if ($phone) {
            $existingByNameAndPhone = Supplier::where('name', $row['name'])
                ->where('phone', $phone)
                ->first();
            if ($existingByNameAndPhone) {
                $this->skippedCount++;
                $this->errors[] = "Row {$this->processedRows}: Duplicate supplier (name + phone)";
                return null;
            }
        }

        // Check for duplicate name only
        $existingByName = Supplier::where('name', $row['name'])->first();
        if ($existingByName) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Duplicate supplier name '{$row['name']}'";
            return null;
        }

        // Validate phone if provided
        if (!empty($row['phone']) && !is_numeric($row['phone'])) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Phone must be numeric";
            return null;
        }

        // Validate email if provided
        if (!empty($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $this->skippedCount++;
            $this->errors[] = "Row {$this->processedRows}: Invalid email format";
            return null;
        }

        // Determine is_active status
        $isActive = 1;
        if (isset($row['is_active'])) {
            $isActive = in_array(strtolower($row['is_active']), ['1', 'yes', 'true', 'active']) ? 1 : 0;
        }

        // Validate type - accept only company or individual, default to individual if not specified
        $type = !empty($row['type']) ? strtolower(trim($row['type'])) : 'individual';
        if (!in_array($type, ['company', 'individual'])) {
            $type = 'individual'; // Default fallback
        }

        // Create the supplier
        $this->importedCount++;

        return new Supplier([
            'name' => $row['name'],
            'category_id' => $category->id,
            'type' => $type,
            'contact_person' => $row['contact_person'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'address' => $row['address'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'pincode' => $row['pincode'] ?? null,
            'country' => $row['country'] ?? null,
            'gst_number' => $row['gst_number'] ?? null,
            'pan_number' => $row['pan_number'] ?? null,
            'registration_number' => $row['registration_number'] ?? null,
            'bank_name' => $row['bank_name'] ?? null,
            'account_number' => $row['account_number'] ?? null,
            'ifsc_code' => $row['ifsc_code'] ?? null,
            'payment_terms' => $row['payment_terms'] ?? null,
            'is_active' => $isActive,
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
            'category' => 'required|string',
            'type' => 'nullable|string|in:company,individual',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|numeric',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|numeric',
            'country' => 'nullable|string|max:100',
            'gst_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'registration_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|numeric',
            'ifsc_code' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|string|max:50',
            'is_active' => 'nullable|in:0,1,yes,no,true,false',
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
            'phone.numeric' => 'Phone must be numeric',
            'email.email' => 'Invalid email format',
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

    /**
     * @return int
     */
    public function chunkSize(): int
    {
        return 1000;
    }
}
