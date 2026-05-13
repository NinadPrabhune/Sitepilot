<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Material Resource
 *
 * Transforms Material model for API responses with proper schema structure
 * This improves OpenAPI schema accuracy in Scribe documentation
 *
 * @example
 * {
 *   "id": 1,
 *   "name": "Cement",
 *   "sku": "MAT-00001",
 *   "hsn_sac": "2523",
 *   "category_id": 5,
 *   "category": {
 *     "id": 5,
 *     "name": "Construction Materials"
 *   },
 *   "unit_id": 3,
 *   "unit": {
 *     "id": 3,
 *     "name": "Bag"
 *   },
 *   "description": "Portland cement",
 *   "price": 450.00,
 *   "reorder_level": 100,
 *   "status": "active",
 *   "image": "images/material/cement.jpg",
 *   "created_by": 1,
 *   "created_at": "2024-01-15T10:30:00.000000Z",
 *   "updated_at": "2024-01-15T10:30:00.000000Z"
 * }
 */
class MaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'hsn_sac' => $this->hsn_sac,
            'gst_master_id' => $this->gst_master_id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'name' => $this->unit->name,
                ];
            }),
            'description' => $this->description,
            'price' => (float) $this->price,
            'reorder_level' => (int) $this->reorder_level,
            'status' => $this->status,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
