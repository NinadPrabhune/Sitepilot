<?php

namespace App\Services;

use App\Models\ProjectFileNew;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Workdo\Taskly\Entities\UserProject;

class ProjectFileService
{
    /**
     * Configuration
     */
    private $config = [
        'max_file_size' => 104857600, // 100MB
        'allowed_extensions' => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
            'zip', 'rar', '7z', 'tar', 'gz',
            'mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv', 'webm',
            'mp3', 'wav', 'flac', 'm4a', 'aac', 'ogg',
            'txt', 'odt', 'ods', 'odp',
            'php', 'js', 'css', 'html', 'json', 'xml', 'sql', 'py', 'java',
            'sh', 'exe', 'dmg', 'iso'
        ],
        'storage_disk' => 'local',
        'base_path' => 'projects',
    ];

    /**
     * Get project storage root
     */
    public function getProjectRoot($projectId)
    {
        return "{$this->config['base_path']}/{$projectId}";
    }

    /**
     * Get full storage path
     */
    public function getFullPath($projectId, $relativePath = null)
    {
        $root = $this->getProjectRoot($projectId);
        
        if ($relativePath) {
            return $root . '/' . ltrim($relativePath, '/');
        }
        
        return $root;
    }

    /**
     * Check user has project access
     */
    public function userHasProjectAccess($userId, $projectId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            return false;
        }

        // Super admin
        if ($user->type === 'super admin') {
            return true;
        }

        // Check UserProject relationship
        return UserProject::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->exists();
    }

    /**
     * Validate file
     */
    public function validateFile(UploadedFile $file, $fileMaxSize = null)
    {
        $maxSize = $fileMaxSize ?? $this->config['max_file_size'];

        // Check file size
        if ($file->getSize() > $maxSize) {
            return 'File size exceeds maximum limit of ' . $this->formatBytes($maxSize);
        }

        // Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->config['allowed_extensions'])) {
            return 'File type .' . $extension . ' is not allowed';
        }

        return true;
    }

    /**
     * Upload file
     */
    public function uploadFile(
        UploadedFile $file,
        $projectId,
        $userId,
        $folderPath = '',
        $description = ''
    )
    {
        try {
            // Validate
            $validation = $this->validateFile($file);
            if ($validation !== true) {
                return ['error' => $validation];
            }

            // Create project directory if needed
            $projectRoot = $this->getProjectRoot($projectId);
            if (!Storage::disk($this->config['storage_disk'])->exists($projectRoot)) {
                Storage::disk($this->config['storage_disk'])->makeDirectory($projectRoot, 0755, true);
            }

            // Prepare folder path
            $fullFolderPath = $projectRoot;
            if ($folderPath) {
                $folderPath = trim($folderPath, '/');
                $fullFolderPath = "{$projectRoot}/{$folderPath}";

                if (!Storage::disk($this->config['storage_disk'])->exists($fullFolderPath)) {
                    Storage::disk($this->config['storage_disk'])->makeDirectory($fullFolderPath, 0755, true);
                }
            }

            // Generate unique filename
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $storedPath = $file->storeAs($fullFolderPath, $fileName, $this->config['storage_disk']);

            // Get full storage path
            $fullPath = Storage::disk($this->config['storage_disk'])->path($storedPath);
            $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

            // Create database record
            $projectFile = ProjectFileNew::create([
                'project_id' => $projectId,
                'user_id' => $userId,
                'name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'folder_path' => $folderPath ?? '',
                'is_folder' => false,
                'mime_type' => $file->getMimeType(),
                'file_size' => $fileSize,
                'original_name' => $file->getClientOriginalName(),
                'disk' => $this->config['storage_disk'],
                'description' => $description,
            ]);

            Log::info("File uploaded: {$projectFile->id} by user {$userId} to project {$projectId}");

            return [
                'success' => true,
                'file' => $projectFile,
                'message' => 'File uploaded successfully',
            ];

        } catch (\Exception $e) {
            Log::error("File upload error: {$e->getMessage()}");
            return ['error' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * Create folder
     */
    
    public function createFolder($projectId, $userId, $folderName, $parentPath = '')
{
    try {
        // Validate folder name
        if (empty($folderName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $folderName)) {
            return ['error' => 'Invalid folder name. Use only letters, numbers, hyphens and underscores'];
        }

        // Normalize parent path (no trailing slash)
        $parentPath = trim($parentPath, '/');

        // Build full path for the new folder
        $filePath = $parentPath === '' ? $folderName : $parentPath . '/' . $folderName;

        // Check if folder already exists in this parent
        $existing = ProjectFileNew::where('project_id', $projectId)
            ->where('folder_path', $parentPath)   // parent reference
            ->where('name', $folderName)
            ->where('is_folder', true)
            ->first();

        if ($existing) {
            return ['error' => 'Folder already exists'];
        }

        // Create in storage
        $projectRoot = $this->getProjectRoot($projectId);
        $fullPath = "{$projectRoot}/{$filePath}";

        if (!Storage::disk($this->config['storage_disk'])->exists($fullPath)) {
            Storage::disk($this->config['storage_disk'])->makeDirectory($fullPath, 0755, true);
        }

        // Create database record
        $folder = ProjectFileNew::create([
            'project_id'  => $projectId,
            'user_id'     => $userId,
            'name'        => $folderName,
            'folder_path' => $parentPath, // parent reference
            'file_path'   => $filePath,   // full path
            'is_folder'   => true,
            'is_archived' => false,
            'disk'        => $this->config['storage_disk'],
        ]);

        Log::info("Folder created: {$folder->id} in project {$projectId}");

        return [
            'success' => true,
            'folder'  => $folder,
            'message' => 'Folder created successfully',
        ];

    } catch (\Exception $e) {
        Log::error("Folder creation error: {$e->getMessage()}");
        return ['error' => 'Folder creation failed: ' . $e->getMessage()];
    }
}

    
    
//    public function createFolder($projectId, $userId, $folderName, $parentPath = '')
//    {
//        try {
//            // Validate folder name
//            if (empty($folderName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $folderName)) {
//                return ['error' => 'Invalid folder name. Use only letters, numbers, hyphens and underscores'];
//            }
//
//            // Check if folder already exists
//            $folderPath = $parentPath ? trim($parentPath, '/') . '/' . $folderName : $folderName;
//            
//            $existing = ProjectFileNew::where('project_id', $projectId)
//                ->where('folder_path', $parentPath)
//                ->where('name', $folderName)
//                ->where('is_folder', true)
//                ->first();
//
//            if ($existing) {
//                return ['error' => 'Folder already exists'];
//            }
//
//            // Create in storage
//            $projectRoot = $this->getProjectRoot($projectId);
//            $fullPath = "{$projectRoot}/{$folderPath}";
//            
//            if (!Storage::disk($this->config['storage_disk'])->exists($fullPath)) {
//                Storage::disk($this->config['storage_disk'])->makeDirectory($fullPath, 0755, true);
//            }
//
//            // Create database record
//            $folder = ProjectFileNew::create([
//                'project_id' => $projectId,
//                'user_id' => $userId,
//                'name' => $folderName,
//                'file_path' => $folderPath,
//                'folder_path' => $parentPath,
//                'is_folder' => true,
//                'disk' => $this->config['storage_disk'],
//            ]);
//
//            Log::info("Folder created: {$folder->id} in project {$projectId}");
//
//            return [
//                'success' => true,
//                'folder' => $folder,
//                'message' => 'Folder created successfully',
//            ];
//
//        } catch (\Exception $e) {
//            Log::error("Folder creation error: {$e->getMessage()}");
//            return ['error' => 'Folder creation failed: ' . $e->getMessage()];
//        }
//    }

    /**
     * Get files in folder
     */
    public function getFolderContents($projectId, $folderPath = '')
    {
        try {
            $query = ProjectFileNew::where('project_id', $projectId)
                ->where('folder_path', $folderPath ?? '')
                ->where('is_archived', false)
                ->orderBy('is_folder', 'desc')
                ->orderBy('name', 'asc');

            return $query->get();

        } catch (\Exception $e) {
            Log::error("Get folder contents error: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Get folder structure (tree)
     */
    
    public function getFolderStructure($projectId, $parentPath = '', $depth = 0, $maxDepth = 5)
{
    if ($depth > $maxDepth) {
        return [];
    }

    // Fetch all folders whose parent path matches
    $folders = ProjectFileNew::where('project_id', $projectId)
        ->where('folder_path', $parentPath ?? '')
        ->where('is_folder', true)
        ->where('is_archived', false)
        ->orderBy('name', 'asc')
        ->get();

    $structure = [];
    foreach ($folders as $folder) {
        // Ensure file_path is set when creating folders (e.g. "reports/finance")
        $structure[] = [
            'id'       => $folder->id,
            'name'     => $folder->name,
            'path'     => $folder->file_path, // full path
            'children' => $this->getFolderStructure(
                $projectId,
                $folder->file_path, // recurse using file_path
                $depth + 1,
                $maxDepth
            ),
        ];
    }

    return $structure;
}

    
    
//    public function getFolderStructure($projectId, $parentPath = '', $depth = 0, $maxDepth = 5)
//    {
//        if ($depth > $maxDepth) {
//            return [];
//        }
//
//        $folders = ProjectFileNew::where('project_id', $projectId)
//            ->where('folder_path', $parentPath)
//            ->where('is_folder', true)
//            ->where('is_archived', false)
//            ->orderBy('name', 'asc')
//            ->get();
//
//        $structure = [];
//        foreach ($folders as $folder) {
//            $structure[] = [
//                'id' => $folder->id,
//                'name' => $folder->name,
//                'path' => $folder->file_path,
//                'children' => $this->getFolderStructure($projectId, $folder->file_path, $depth + 1, $maxDepth),
//            ];
//        }
//
//        return $structure;
//    }

    /**
     * Rename file or folder
     */
    public function rename(ProjectFileNew $file, $newName)
    {
        try {
            // Validate name
            if (empty($newName) || strlen($newName) > 255) {
                return ['error' => 'Invalid filename'];
            }

            // Prevent directory traversal
            if (strpos($newName, '/') !== false || strpos($newName, '\\') !== false) {
                return ['error' => 'Invalid filename'];
            }

            // Get old path
            $oldPath = $file->file_path;
            
            if ($file->is_folder) {
                // New folder path
                $newPath = ($file->folder_path ? trim($file->folder_path, '/') . '/' : '') . $newName;
                
                // Update all children
                ProjectFileNew::where('project_id', $file->project_id)
                    ->where('folder_path', 'like', $oldPath . '%')
                    ->get()
                    ->each(function ($child) use ($oldPath, $newPath) {
                        $newChildPath = str_replace($oldPath, $newPath, $child->file_path);
                        $child->update([
                            'file_path' => $newChildPath,
                            'folder_path' => str_replace($oldPath, $newPath, $child->folder_path),
                        ]);
                    });

                // Move in storage
                $projectRoot = $this->getProjectRoot($file->project_id);
                $newStoragePath = "{$projectRoot}/{$newPath}";
                if (Storage::disk($this->config['storage_disk'])->exists($projectRoot . '/' . $oldPath)) {
                    Storage::disk($this->config['storage_disk'])->move(
                        $projectRoot . '/' . $oldPath,
                        $newStoragePath
                    );
                }

                $file->update([
                    'name' => $newName,
                    'file_path' => $newPath,
                ]);
            } else {
                // New file path
                $newPath = ($file->folder_path ? trim($file->folder_path, '/') . '/' : '') . $newName;
                
                // Move in storage
                $projectRoot = $this->getProjectRoot($file->project_id);
                if (Storage::disk($this->config['storage_disk'])->exists($projectRoot . '/' . $oldPath)) {
                    Storage::disk($this->config['storage_disk'])->move(
                        $projectRoot . '/' . $oldPath,
                        "{$projectRoot}/{$newPath}"
                    );
                }

                $file->update([
                    'name' => $newName,
                    'file_path' => $newPath,
                ]);
            }

            Log::info("File {$file->id} renamed from {$oldPath} to {$newPath}");

            return [
                'success' => true,
                'file' => $file->fresh(),
                'message' => 'Renamed successfully',
            ];

        } catch (\Exception $e) {
            Log::error("Rename error: {$e->getMessage()}");
            return ['error' => 'Rename failed: ' . $e->getMessage()];
        }
    }

    /**
     * Delete file or folder
     */
    public function delete(ProjectFileNew $file)
    {
        try {
            // Delete from storage
            $projectRoot = $this->getProjectRoot($file->project_id);
            $fullPath = "{$projectRoot}/" . $file->file_path;

            if ($file->is_folder) {
                // Delete folder and contents from storage
                if (Storage::disk($this->config['storage_disk'])->exists($fullPath)) {
                    Storage::disk($this->config['storage_disk'])->deleteDirectory($fullPath);
                }

                // Soft delete children
                ProjectFileNew::where('project_id', $file->project_id)
                    ->where('folder_path', 'like', $file->file_path . '%')
                    ->delete();
            } else {
                // Delete file from storage
                if (Storage::disk($this->config['storage_disk'])->exists($fullPath)) {
                    Storage::disk($this->config['storage_disk'])->delete($fullPath);
                }
            }

            // Soft delete from database
            $file->delete();

            Log::info("File {$file->id} deleted");

            return [
                'success' => true,
                'message' => 'Deleted successfully',
            ];

        } catch (\Exception $e) {
            Log::error("Delete error: {$e->getMessage()}");
            return ['error' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get project storage stats
     */
    public function getStorageStats($projectId)
    {
        try {
            $projectRoot = $this->getProjectRoot($projectId);

            $totalSize = 0;
            $files = ProjectFileNew::where('project_id', $projectId)
                ->where('is_folder', false)
                ->where('is_archived', false)
                ->get();

            foreach ($files as $file) {
                $totalSize += $file->file_size ?? 0;
            }

            $fileCount = $files->count();
            $folderCount = ProjectFileNew::where('project_id', $projectId)
                ->where('is_folder', true)
                ->where('is_archived', false)
                ->count();

            return [
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'file_count' => $fileCount,
                'folder_count' => $folderCount,
                'max_size' => $this->config['max_file_size'],
                'max_size_formatted' => $this->formatBytes($this->config['max_file_size']),
                'usage_percent' => $this->config['max_file_size'] > 0 
                    ? round(($totalSize / $this->config['max_file_size']) * 100, 2)
                    : 0,
            ];

        } catch (\Exception $e) {
            Log::error("Get storage stats error: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Format bytes to human readable
     */
    public function formatBytes($bytes)
    {
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
     * Search files
     */
    public function search($projectId, $query, $limit = 20)
    {
        try {
            return ProjectFileNew::where('project_id', $projectId)
                ->where('is_archived', false)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%");
                })
                ->orderBy('name', 'asc')
                ->limit($limit)
                ->get();

        } catch (\Exception $e) {
            Log::error("Search error: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Get file by ID with project check
     */
    public function getFile($projectId, $fileId)
    {
        return ProjectFileNew::where('project_id', $projectId)
            ->where('id', $fileId)
            ->first();
    }
}
