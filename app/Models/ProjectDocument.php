<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Workdo\Taskly\Entities\Project;

class ProjectDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'user_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'storage_disk',
        'description',
        'folder_path',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the project this document belongs to
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who uploaded this document
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get human-readable file size
     */
    public function getHumanFileSize()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $i < count($units) - 1; $i++) {
            if ($bytes < 1024) {
                return round($bytes, 2) . ' ' . $units[$i];
            }
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . end($units);
    }

    /**
     * Scope to get documents in a specific project
     */
    public function scopeInProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to get documents in a specific folder
     */
    public function scopeInFolder($query, $folderPath)
    {
        return $query->where('folder_path', $folderPath);
    }

    /**
     * Scope to get only files (not directories)
     */
    public function scopeFiles($query)
    {
        return $query->whereNotNull('file_name')->whereNotNull('file_type');
    }

    /**
     * Check if file has specific extension
     */
    public function hasExtension($extension)
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION)) === strtolower($extension);
    }

    /**
     * Get file icon class based on file type
     */
    public function getFileIcon()
    {
        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));

//        $iconMap = [
//            // Images
//            'jpg' => 'ti ti-photo',
//            'jpeg' => 'ti ti-photo',
//            'png' => 'ti ti-photo',
//            'gif' => 'ti ti-photo',
//            'svg' => 'ti ti-photo',
//            'webp' => 'ti ti-photo',
//            // Documents
//            'pdf' => 'ti ti-file-pdf',
//            'doc' => 'ti ti-file-word',
//            'docx' => 'ti ti-file-word',
//            'xls' => 'ti ti-file-spreadsheet',
//            'xlsx' => 'ti ti-file-spreadsheet',
//            'ppt' => 'ti ti-presentation',
//            'pptx' => 'ti ti-presentation',
//            'txt' => 'ti ti-file-text',
//            // Archives
//            'zip' => 'ti ti-file-zip',
//            'rar' => 'ti ti-file-zip',
//            '7z' => 'ti ti-file-zip',
//            // Video
//            'mp4' => 'ti ti-file-video',
//            'avi' => 'ti ti-file-video',
//            'mov' => 'ti ti-file-video',
//            'mkv' => 'ti ti-file-video',
//            // Audio
//            'mp3' => 'ti ti-file-music',
//            'wav' => 'ti ti-file-music',
//            'flac' => 'ti ti-file-music',
//            'm4a' => 'ti ti-file-music',
//        ];
        
        $iconMap = [
    // Images
    'jpg'   => 'ti ti-photo',
    'jpeg'  => 'ti ti-photo',
    'png'   => 'ti ti-photo',
    'gif'   => 'ti ti-photo',
    'svg'   => 'ti ti-photo',
    'webp'  => 'ti ti-photo',

    // Documents
    'pdf'   => 'ti ti-file-type-pdf',
    'doc'   => 'ti ti-file-type-doc',
    'docx'  => 'ti ti-file-type-doc',
    'xls'   => 'ti ti-file-spreadsheet',
    'xlsx'  => 'ti ti-file-spreadsheet',
    'ppt'   => 'ti ti-file-type-ppt',
    'pptx'  => 'ti ti-file-type-ppt',
    'txt'   => 'ti ti-file-text',

    // Archives
    'zip'   => 'ti ti-file-zip',
    'rar'   => 'ti ti-file-zip',
    '7z'    => 'ti ti-file-zip',

    // Video
    'mp4'   => 'ti ti-file-video',
    'avi'   => 'ti ti-file-video',
    'mov'   => 'ti ti-file-video',
    'mkv'   => 'ti ti-file-video',

    // Audio
    'mp3'   => 'ti ti-file-music',
    'wav'   => 'ti ti-file-music',
    'flac'  => 'ti ti-file-music',
    'm4a'   => 'ti ti-file-music',
];


        return $iconMap[$extension] ?? 'ti ti-file';
    }
}
