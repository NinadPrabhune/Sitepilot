<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'project_id' => 'nullable|integer|exists:projects,id',
            'material_id' => 'nullable|integer|exists:materials,id',
            'type' => 'nullable|in:opening,grn,issue,transfer_in,transfer_out,adjustment',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'project_id.integer' => 'Project ID must be an integer.',
            'project_id.exists' => 'The selected project does not exist.',
            'material_id.integer' => 'Material ID must be an integer.',
            'material_id.exists' => 'The selected material does not exist.',
            'type.in' => 'Type must be one of: opening, grn, issue, transfer_in, transfer_out, adjustment.',
            'start_date.date' => 'Start date must be a valid date.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be greater than or equal to start date.',
        ];
    }
}
