<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\PurchaseOrder;

class StorePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Purchase Order fields
            'po_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'tax_type' => ['required', 'in:cgst,igst'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:po_date'],
            'reference_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'delivery_terms_conditions' => ['nullable', 'string', 'max:1000'],
            'remark' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],

            // Items array
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:materials,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.gst_master_id' => ['nullable', 'exists:gst_masters,id'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.indent_quantity' => ['nullable', 'numeric', 'min:0'],

            // Additional fields
            'additional_charge' => ['nullable', 'numeric', 'min:0'],
            'additional_deduction' => ['nullable', 'numeric', 'min:0'],
            'additional_discount' => ['nullable', 'numeric', 'min:0'],

            // Calculated totals (for reference only - will be recalculated)
            'total_taxable_value' => ['nullable', 'numeric'],
            'total_cgst' => ['nullable', 'numeric'],
            'total_sgst' => ['nullable', 'numeric'],
            'total_igst' => ['nullable', 'numeric'],
            'total_tax' => ['nullable', 'numeric'],
            'total_discount' => ['nullable', 'numeric'],
            'grand_total' => ['nullable', 'numeric'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required.',
            'items.*.material_id.required' => 'Material is required for each item.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.unit.required' => 'Unit is required for each item.',
            'items.*.price.required' => 'Price is required for each item.',
            'supplier_id.required' => 'Supplier is required.',
            'tax_type.required' => 'Tax type (CGST/IGST) is required.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate discount doesn't exceed row total
            if ($this->has('items')) {
                foreach ($this->input('items') as $index => $item) {
                    $quantity = floatval($item['quantity'] ?? 0);
                    $price = floatval($item['price'] ?? 0);
                    $discount = floatval($item['discount_amount'] ?? 0);
                    $rowTotal = $quantity * $price;

                    if ($discount > $rowTotal) {
                        $validator->errors()->add(
                            "items.{$index}.discount_amount",
                            "Discount cannot exceed row total (₹{$rowTotal}) for item #" . ($index + 1)
                        );
                    }
                }
            }
        });
    }
}
