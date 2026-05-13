<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\MaterialIssue;
use App\Models\MaterialReturnItem;

class StoreMaterialReturnRequest extends FormRequest
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
            'issue_id' => 'required|exists:material_issues,id',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.issue_item_id' => 'required|exists:material_issue_items,id',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.remarks' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'issue_id.required' => __('Material Issue is required.'),
            'issue_id.exists' => __('Selected Material Issue does not exist.'),
            'items.*.issue_item_id.required' => __('Issue item is required.'),
            'items.*.issue_item_id.exists' => __('Selected issue item does not exist.'),
            'items.*.quantity.min' => __('Return quantity must be at least 0.01.'),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->has('issue_id') || !$this->has('items')) {
                return;
            }

            $issueId = $this->input('issue_id');
            $items = $this->input('items', []);

            // Get the issue with its items
            $issue = MaterialIssue::with('items')->find($issueId);
            if (!$issue) {
                return;
            }

            // Group return items by issue_item_id
            $returnItemsByIssueItem = [];
            foreach ($items as $index => $item) {
                if (isset($item['issue_item_id'])) {
                    $issueItemId = $item['issue_item_id'];
                    if (!isset($returnItemsByIssueItem[$issueItemId])) {
                        $returnItemsByIssueItem[$issueItemId] = 0;
                    }
                    $returnItemsByIssueItem[$issueItemId] += floatval($item['quantity']);
                }
            }

            // Validate each issue item
            foreach ($returnItemsByIssueItem as $issueItemId => $returnQty) {
                $issueItem = $issue->items->firstWhere('id', $issueItemId);
                
                if (!$issueItem) {
                    $validator->errors()->add(
                        "items.{$issueItemId}.issue_item_id",
                        __('Invalid issue item selected.')
                    );
                    continue;
                }

                // Calculate already returned quantity for this issue item
                $alreadyReturnedQty = MaterialReturnItem::where('issue_item_id', $issueItemId)
                    ->join('material_returns', 'material_return_items.return_id', '=', 'material_returns.id')
                    ->where('material_returns.issue_id', $issueId)
                    ->sum('material_return_items.quantity');

                // Calculate remaining quantity
                $issuedQty = floatval($issueItem->quantity);
                $remainingQty = $issuedQty - $alreadyReturnedQty;

                // Validate return quantity doesn't exceed remaining
                if ($returnQty > $remainingQty) {
                    $validator->errors()->add(
                        "items.{$issueItemId}.quantity",
                        __('Return quantity cannot exceed remaining issued quantity. Issued: :issued, Already Returned: :returned, Remaining: :remaining', [
                            'issued' => $issuedQty,
                            'returned' => $alreadyReturnedQty,
                            'remaining' => $remainingQty,
                        ])
                    );
                }
            }
        });
    }
}
