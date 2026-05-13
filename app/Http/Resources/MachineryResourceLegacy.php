<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Legacy Machinery Resource for backward compatibility
 * Maintains the original API response structure for existing consumers
 */
class MachineryResourceLegacy extends JsonResource
{
    /**
     * Transform the resource into an array (legacy format).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Legacy format - only includes original fields
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'model_number' => $this->model_number,
            'manufacturer' => $this->manufacturer,
            'purchase_date' => $this->purchase_date,
            'capacity' => $this->capacity,
            'maintenance_schedule' => $this->maintenance_schedule,
            'remarks' => $this->remarks,
            'description' => $this->description,
            'vehicle_number' => $this->vehicle_number,
            'owned_by' => $this->owned_by,
            'supplier_id' => $this->supplier_id,
            'rate' => $this->rate,
            'operational_status' => $this->operational_status,
            'site_id' => $this->site_id,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'workspace_id' => $this->workspace_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
