<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectDocumentResource extends JsonResource
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
            'project_id' => $this->project_id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->getHumanFileSize(),
            'storage_disk' => $this->storage_disk,
            'description' => $this->description,
            'folder_path' => $this->folder_path,
            'file_icon' => $this->getFileIcon(),
            'uploaded_by' => [
                'id' => $this->uploadedBy->id ?? null,
                'name' => $this->uploadedBy->name ?? null,
                'email' => $this->uploadedBy->email ?? null,
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
