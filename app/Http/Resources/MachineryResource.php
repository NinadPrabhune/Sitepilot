<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MachineryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'machine_id' => $this->machine_id,
            'name' => $this->name,
            
            // Basic information
            'category' => [
                'id' => $this->category_id,
                'name' => $this->category?->name,
            ],
            'model_number' => $this->model_number,
            'manufacturer' => $this->manufacturer,
            'purchase_date' => $this->purchase_date,
            'capacity' => $this->capacity,
            'maintenance_schedule' => $this->maintenance_schedule,
            'remarks' => $this->remarks,
            'description' => $this->description,
            'vehicle_number' => $this->vehicle_number,
            
            // Ownership information
            'owned_by' => $this->owned_by,
            'supplier' => $this->when($this->supplier_id, [
                'id' => $this->supplier_id,
                'name' => $this->supplier?->name,
            ]),
            'rate' => $this->rate,
            
            // Rental-specific fields
            'rate_type' => $this->rate_type,
            'minimum_billing_hours' => $this->minimum_billing_hours,
            'diesel_by_company' => $this->diesel_by_company,
            'operator_by_supplier' => $this->operator_by_supplier,
            'number_of_operators' => $this->number_of_operators,
            'rental_agreement_file' => $this->when($this->rental_agreement_file, function() {
                return [
                    'filename' => $this->rental_agreement_file,
                    'url' => asset('storage/machinery_documents/' . $this->rental_agreement_file),
                ];
            }),
            
            // Owned-specific fields
            'purchase_value' => $this->purchase_value,
            'insurance_due_date' => $this->insurance_due_date,
            'puc_due_date' => $this->puc_due_date,
            'fitness_due_date' => $this->fitness_due_date,
            'last_service_date' => $this->last_service_date,
            'ownership_documents_file' => $this->when($this->ownership_documents_file, function() {
                return [
                    'filename' => $this->ownership_documents_file,
                    'url' => asset('storage/machinery_documents/' . $this->ownership_documents_file),
                ];
            }),
            
            // Status and location
            'operational_status' => $this->operational_status,
            'site' => $this->when($this->site_id, [
                'id' => $this->site_id,
                'name' => $this->site?->name,
            ]),
            'workspace' => [
                'id' => $this->workspace_id,
                'name' => $this->workspace?->name,
            ],
            'status' => $this->status,
            
            // Metadata
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
