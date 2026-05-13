<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Workdo\Taskly\Entities\Project;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ProjectFileNew extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'project_files_new';

    protected $fillable = [
        'project_id',
        'user_id',
        'name',
        'file_path',
        'folder_path',
        'is_folder',
        'mime_type',
        'file_size',
        'original_name',
        'disk',
        'description',
        'tags',
        'is_public',
        'is_archived',
        'downloaded_at',
        'download_count',
    ];

    protected $casts = [
        'is_folder' => 'boolean',
        'is_public' => 'boolean',
        'is_archived' => 'boolean',
        'file_size' => 'integer',
        'downloaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    /**
     * Get the project this file belongs to
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who uploaded this file
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get child files/folders in this directory
     */
    public function children()
    {
        return $this->hasMany(ProjectFile::class, 'folder_path', 'file_path');
    }

    /**
     * Get parent directory if this is a file
     */
    public function parent()
    {
        return $this->belongsTo(ProjectFile::class, 'folder_path', 'file_path');
    }

    /**
     * Scopes
     */

    /**
     * Scope: Only files (not directories)
     */
    public function scopeFiles($query)
    {
        return $query->where('is_folder', false);
    }

    /**
     * Scope: Only directories
     */
    public function scopeFolders($query)
    {
        return $query->where('is_folder', true);
    }

    /**
     * Scope: In specific project
     */
    public function scopeInProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: In specific folder
     */
    public function scopeInFolder($query, $folderPath = '')
    {
        return $query->where('folder_path', $folderPath ?? '');
    }

    /**
     * Scope: Active files only
     */
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope: Not archived
     */
    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope: Public files
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope: Search by name or description
     */
    public function scopeSearch($query, $search)
    {
        return $query->whereRaw('MATCH(name, description) AGAINST(? IN BOOLEAN MODE)', [$search . '*']);
    }

    /**
     * File handling methods
     */

    /**
     * Get human-readable file size
     */
    public function getHumanFileSize()
    {
        if ($this->is_folder || !$this->file_size) {
            return '—';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $i < count($units) - 1; $i++) {
            if ($bytes < 1024) {
                return round($bytes, 2) . ' ' . $units[$i];
            }
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . end($units);
    }

    /**
     * Get file extension
     */
    public function getExtension()
    {
        if ($this->is_folder) {
            return null;
        }
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /**
     * Get file icon class based on file type
     */
    public function getFileIcon()
    {
        if ($this->is_folder) {
            return 'ti ti-folder';
        }

        $extension = $this->getExtension();

        $iconMap = [
            // Images
            'jpg' => 'ti ti-photo',
            'jpeg' => 'ti ti-photo',
            'png' => 'ti ti-photo',
            'gif' => 'ti ti-photo',
            'svg' => 'ti ti-photo',
            'webp' => 'ti ti-photo',
            'bmp' => 'ti ti-photo',
            'ico' => 'ti ti-photo',
            
            // Documents
            'pdf' => 'ti ti-file-pdf',
            'doc' => 'ti ti-file-word',
            'docx' => 'ti ti-file-word',
            'xls' => 'ti ti-file-spreadsheet',
            'xlsx' => 'ti ti-file-spreadsheet',
            'csv' => 'ti ti-file-spreadsheet',
            'ppt' => 'ti ti-presentation',
            'pptx' => 'ti ti-presentation',
            'txt' => 'ti ti-file-text',
            'odt' => 'ti ti-file-word',
            'ods' => 'ti ti-file-spreadsheet',
            'odp' => 'ti ti-presentation',
            
            // Archives
            'zip' => 'ti ti-file-zip',
            'rar' => 'ti ti-file-zip',
            '7z' => 'ti ti-file-zip',
            'tar' => 'ti ti-file-zip',
            'gz' => 'ti ti-file-zip',
            
            // Video
            'mp4' => 'ti ti-file-video',
            'avi' => 'ti ti-file-video',
            'mov' => 'ti ti-file-video',
            'mkv' => 'ti ti-file-video',
            'wmv' => 'ti ti-file-video',
            'flv' => 'ti ti-file-video',
            'webm' => 'ti ti-file-video',
            
            // Audio
            'mp3' => 'ti ti-file-music',
            'wav' => 'ti ti-file-music',
            'flac' => 'ti ti-file-music',
            'm4a' => 'ti ti-file-music',
            'aac' => 'ti ti-file-music',
            'ogg' => 'ti ti-file-music',
            
            // Code
            'php' => 'ti ti-file-code',
            'js' => 'ti ti-file-code',
            'css' => 'ti ti-file-code',
            'html' => 'ti ti-file-code',
            'json' => 'ti ti-file-code',
            'xml' => 'ti ti-file-code',
            'sql' => 'ti ti-file-code',
            'py' => 'ti ti-file-code',
            'java' => 'ti ti-file-code',
            
            // Other
            'exe' => 'ti ti-file-alert',
            'sh' => 'ti ti-file-code',
            'dmg' => 'ti ti-file',
            'iso' => 'ti ti-file',
        ];

        return $iconMap[$extension] ?? 'ti ti-file';
    }

    /**
     * Get the storage disk instance
     */
    public function getStorageDisk()
    {
        return Storage::disk($this->disk);
    }

    /**
     * Check if file exists in storage
     */
    public function fileExists()
    {
        if ($this->is_folder) {
            return true;
        }
        return $this->getStorageDisk()->exists($this->file_path);
    }

    /**
     * Get file content
     */
    public function getContent()
    {
        if (!$this->fileExists() || $this->is_folder) {
            return null;
        }
        return $this->getStorageDisk()->get($this->file_path);
    }

    /**
     * Record download
     */
    public function recordDownload()
    {
        $this->update([
            'download_count' => $this->download_count + 1,
            'downloaded_at' => now(),
        ]);
    }

    /**
     * Get full storage path
     */
    public function getFullPath()
    {
        return $this->file_path;
    }

    /**
     * Get breadcrumb path
     */
    public function getBreadcrumbs()
    {
        $breadcrumbs = [];
        
        if (empty($this->folder_path)) {
            return $breadcrumbs;
        }

        $parts = explode('/', trim($this->folder_path, '/'));
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= $part . '/';
            $breadcrumbs[] = [
                'name' => $part,
                'path' => rtrim($currentPath, '/'),
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Archive the file
     */
    public function archive()
    {
        $this->update(['is_archived' => true]);
        return $this;
    }

    /**
     * Restore archived file
     */
    public function restore()
    {
        $this->update(['is_archived' => false]);
        return parent::restore();
    }

    /**
     * Delete file from storage
     */
    public function deleteFromStorage()
    {
        if ($this->fileExists()) {
            $this->getStorageDisk()->delete($this->file_path);
        }

        // If folder, delete all child files recursively
        if ($this->is_folder) {
            $children = self::where('folder_path', 'like', $this->file_path . '%')
                ->get();
            
            foreach ($children as $child) {
                $child->deleteFromStorage();
                $child->forceDelete();
            }
        }
    }

    /**
     * Get path relative to project root
     */
    public function getRelativePath()
    {
        if ($this->is_folder) {
            return $this->file_path;
        }
        return dirname($this->file_path) . '/' . $this->name;
    }

    /**
     * Check if file is an image
     */
    public function isImage()
    {
        $extension = $this->getExtension();
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico']);
    }

    /**
     * Check if file is a document
     */
    public function isDocument()
    {
        $extension = $this->getExtension();
        return in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'odt', 'ods', 'odp', 'csv']);
    }

    /**
     * Check if file is a video
     */
    public function isVideo()
    {
        $extension = $this->getExtension();
        return in_array($extension, ['mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv', 'webm']);
    }

    /**
     * Check if file is an archive
     */
    public function isArchive()
    {
        $extension = $this->getExtension();
        return in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz']);
    }

    /**
     * Get tags as array
     */
    public function getTagsArray()
    {
        if (empty($this->tags)) {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->tags)));
    }

    /**
     * Set tags from array
     */
    public function setTagsFromArray($tags)
    {
        if (is_array($tags)) {
            $this->tags = implode(',', array_filter($tags));
        } else {
            $this->tags = $tags;
        }
        return $this;
    }

    /**
     * Get last modified timestamp formatted
     */
    public function getLastModifiedFormatted()
    {
        return $this->updated_at->format('M d, Y H:i');
    }

    /**
     * Get created timestamp formatted
     */
    public function getCreatedAtFormatted()
    {
        return $this->created_at->format('M d, Y H:i');
    }
}
