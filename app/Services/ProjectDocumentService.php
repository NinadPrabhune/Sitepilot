<?php

namespace App\Services;

use App\Models\ProjectDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProjectDocumentService {

    /**
     * Configuration for file handling
     */
    private $config = [
//        'max_file_size' => 104857600, // 100MB in bytes
        
        'max_file_size' => 52428800, // 50MB in bytes
        
//        'max_file_size' => 20971520, // 20MB in bytes


        
        'allowed_extensions' => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'zip', 'rar', '7z',
            'mp4', 'avi', 'mov', 'mkv',
            'mp3', 'wav', 'flac', 'm4a',
            'txt', 'csv'
        ],
        'storage_disk' => 'local',
        'base_storage_path' => 'projects',
    ];

    /**
     * Initialize service
     */
    public function __construct() {
        // Service initialization if needed
    }

    /**
     * Get project document root directory
     */
    public function getProjectRootPath($projectId) {
        return "{$this->config['base_storage_path']}/{$projectId}";
    }

    /**
     * Get full file storage path
     */
    public function getFullPath($projectId, $filePath = null) {
        $rootPath = $this->getProjectRootPath($projectId);

        if ($filePath) {
            return "{$rootPath}/{$filePath}";
        }

        return $rootPath;
    }

    /**
     * Upload a file to project directory
     *
     * @param UploadedFile $file
     * @param int $projectId
     * @param int $userId
     * @param string $folderPath
     * @return array|false
     */
    public function uploadFile(UploadedFile $file, $projectId, $userId, $folderPath = '') {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if ($validation !== true) {
                return ['error' => $validation];
            }

            // Prepare storage path - fix double slash issue
            $projectPath = $this->getProjectRootPath($projectId);
            $storagePath = $projectPath;
            if ($folderPath) {
                $storagePath = "{$projectPath}/{$folderPath}";
            }

            // Create directory if not exists
            if (!Storage::disk($this->config['storage_disk'])->exists($storagePath)) {
                Storage::disk($this->config['storage_disk'])->makeDirectory($storagePath, 0755, true);
            }

            // Store file with unique name
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $storedFilePath = $file->storeAs($storagePath, $fileName, $this->config['storage_disk']);

            // Get file info - use the returned stored path
            $fullPath = Storage::disk($this->config['storage_disk'])->path($storedFilePath);

            // Get file size
            $fileSize = 0;
            if (file_exists($fullPath)) {
                $fileSize = filesize($fullPath);
            } else {
                // Fallback: try to get size from the original file
                $fileSize = $file->getSize();
            }

            $fileType = $file->getMimeType();

            // Save metadata to database
            $document = ProjectDocument::create([
                'project_id' => $projectId,
                'user_id' => $userId,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storedFilePath,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'storage_disk' => $this->config['storage_disk'],
                'folder_path' => $folderPath ?: null,
            ]);

            Log::info("File uploaded successfully", [
                'project_id' => $projectId,
                'document_id' => $document->id,
                'file_name' => $file->getClientOriginalName(),
                'stored_path' => $storedFilePath,
                'file_size' => $fileSize,
            ]);

            return [
                'success' => true,
                'document' => $document,
                'message' => 'File uploaded successfully',
            ];
        } catch (\Exception $e) {
            Log::error("File upload failed: {$e->getMessage()}", [
                'project_id' => $projectId,
                'user_id' => $userId,
                'exception' => $e->getTraceAsString(),
            ]);

            return [
                'error' => 'Failed to upload file: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file) {
        // Check file size
        if ($file->getSize() > $this->config['max_file_size']) {
            return "File size exceeds maximum limit of " . $this->formatBytes($this->config['max_file_size']);
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->config['allowed_extensions'])) {
            return "File type '{$extension}' is not allowed";
        }

        return true;
    }

    /**
     * Get files in a project folder
     */
    public function getProjectFiles($projectId, $folderPath = '') {
        $query = ProjectDocument::where('project_id', $projectId);

        if ($folderPath) {
            $query->where('folder_path', $folderPath);
        } else {
            $query->whereNull('folder_path');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Delete a document
     */
    public function deleteDocument($documentId, $projectId) {
        try {
            $document = ProjectDocument::where('id', $documentId)
                    ->where('project_id', $projectId)
                    ->first();

            if (!$document) {
                return ['error' => 'Document not found'];
            }

            // Delete file from storage
            if (Storage::disk($document->storage_disk)->exists($document->file_path)) {
                Storage::disk($document->storage_disk)->delete($document->file_path);
            }

            // Delete from database
            $document->delete();

            Log::info("Document deleted successfully", [
                'document_id' => $documentId,
                'project_id' => $projectId,
            ]);

            return [
                'success' => true,
                'message' => 'Document deleted successfully',
            ];
        } catch (\Exception $e) {
            Log::error("Document deletion failed: {$e->getMessage()}", [
                'document_id' => $documentId,
            ]);

            return ['error' => 'Failed to delete document'];
        }
    }

    /**
     * Download a document
     */
    public function downloadDocument($documentId, $projectId) {
        try {
            $document = ProjectDocument::where('id', $documentId)
                    ->where('project_id', $projectId)
                    ->first();

            if (!$document) {
                return ['error' => 'Document not found'];
            }

            // Use Storage facade to get the correct path
            $storagePath = Storage::disk($document->storage_disk)->path($document->file_path);

            if (!file_exists($storagePath)) {
                return ['error' => 'File not found on server'];
            }

            Log::info("Document downloaded", [
                'document_id' => $documentId,
                'project_id' => $projectId,
                'file_path' => $document->file_path,
            ]);

            return [
                'success' => true,
                'file' => $storagePath,
                'name' => $document->file_name,
            ];
        } catch (\Exception $e) {
            Log::error("Document download failed: {$e->getMessage()}");
            return ['error' => 'Failed to download document'];
        }
    }

    /**
     * Rename a document
     */
    public function renameDocument($documentId, $projectId, $newName) {
        try {
            $document = ProjectDocument::where('id', $documentId)
                    ->where('project_id', $projectId)
                    ->first();

            if (!$document) {
                return ['error' => 'Document not found'];
            }

            // Validate new name
            if (empty($newName) || strlen($newName) > 255) {
                return ['error' => 'Invalid file name'];
            }

            $oldName = $document->file_name;
            $document->update(['file_name' => $newName]);

            Log::info("Document renamed", [
                'document_id' => $documentId,
                'old_name' => $oldName,
                'new_name' => $newName,
            ]);

            return [
                'success' => true,
                'document' => $document,
                'message' => 'Document renamed successfully',
            ];
        } catch (\Exception $e) {
            Log::error("Document rename failed: {$e->getMessage()}");
            return ['error' => 'Failed to rename document'];
        }
    }

    /**
     * Get folder structure for a project
     */
    public function getProjectFolderStructure($projectId) {
        try {
            // Root-level files (no folder_path)
            $rootFiles = ProjectDocument::where('project_id', $projectId)
                    ->whereNull('folder_path')
                    ->get();

            // Top-level folders (file_type = folder)
            $folders = ProjectDocument::where('project_id', $projectId)
                    ->where('file_type', 'folder')
                    ->whereNull('folder_path')
                    ->pluck('file_name')
                    ->toArray();

            return [
                'root' => $rootFiles,
                'folders' => $folders,
                'folder_count' => count($folders),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get folder structure: {$e->getMessage()}");
            return [
                'error' => 'Failed to retrieve folder structure',
                'root' => collect([]),
                'folders' => [],
                'folder_count' => 0,
            ];
        }
    }
    
    
    public function getProjectFolderStructureNested($projectId, $parentPath = null)
    {
        try {
            // Get all items directly under this parent path
            $items = ProjectDocument::where('project_id', $projectId)
                ->where('folder_path', $parentPath) // null for root
                ->get();

            $structure = [
                'files' => [],
                'folders' => []
            ];

            foreach ($items as $item) {
                if ($item->file_type === 'folder') {
                    // Recursively build subfolder contents
                    $structure['folders'][$item->file_name] = $this->getProjectFolderStructureNested(
                        $projectId,
                        $item->file_path
                    );
                } else {
                    // Regular file
                    $structure['files'][] = $item;
                }
            }

            return $structure;

        } catch (\Exception $e) {
            Log::error("Failed to get nested folder structure: {$e->getMessage()}");
            return ['error' => 'Failed to retrieve folder structure', 'files' => [], 'folders' => []];
        }
    }


    /**
     * Create a folder (logical structure in database)
     */
    public function createFolder($projectId, $userId, $folderPath) {
        try {
            if (empty($folderPath) || strlen($folderPath) > 255) {
                return ['error' => 'Invalid folder name'];
            }

            // Prevent directory traversal
            if (strpos($folderPath, '..') !== false || strpos($folderPath, '/') !== false) {
                return ['error' => 'Invalid folder name'];
            }

            // Check if folder already exists
            $exists = ProjectDocument::where('project_id', $projectId)
                    ->where('folder_path', $folderPath)
                    ->where('file_type', 'folder')
                    ->exists();

            if ($exists) {
                return ['error' => 'Folder already exists'];
            }

            // Create directory on storage
            $projectPath = $this->getProjectRootPath($projectId);
            $fullFolderPath = "{$projectPath}/{$folderPath}";

            if (!Storage::disk($this->config['storage_disk'])->exists($fullFolderPath)) {
                Storage::disk($this->config['storage_disk'])->makeDirectory($fullFolderPath, 0755, true);
            }

            // Create folder record in database for UI display
            $document = ProjectDocument::create([
                'project_id' => $projectId,
                'user_id' => $userId,
                'file_name' => $folderPath,
                'file_path' => $fullFolderPath,
                'file_type' => 'folder',
                'file_size' => 0,
                'folder_path' => null, // Root level folder
                'storage_disk' => $this->config['storage_disk'],
            ]);

            Log::info("Folder created", [
                'project_id' => $projectId,
                'folder_path' => $folderPath,
                'document_id' => $document->id,
            ]);

            return [
                'success' => true,
                'folder' => $folderPath,
                'document' => $document,
                'message' => 'Folder created successfully',
            ];
        } catch (\Exception $e) {
            Log::error("Folder creation failed: {$e->getMessage()}");
            return ['error' => 'Failed to create folder'];
        }
    }

    /**
     * Check if user has access to project documents
     */
    public function userHasProjectAccess($userId, $projectId) {
        $user = auth()->user() ?: \App\Models\User::find($userId);

        if (!$user) {
            return false;
        }

        // Super admin has access to everything
        if ($user->type === 'super admin') {
            return true;
        }

        // Company users have full access to all project documents
        if ($user->type === 'company') {
            return true;
        }

        // Users with admin roles have full access
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if user is assigned to the project
        // Using Workdo\Taskly's UserProject relationship
        $hasAccess = \Workdo\Taskly\Entities\UserProject::where('user_id', $userId)
                ->where('project_id', $projectId)
                ->exists();

        return $hasAccess;
    }

    /**
     * Format bytes to human readable format
     */
    public function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $i < count($units) - 1; $i++) {
            if ($bytes < 1024) {
                return round($bytes, $precision) . ' ' . $units[$i];
            }
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . end($units);
    }

    /**
     * Get storage statistics for a project
     */
    public function getProjectStorageStats($projectId) {
        try {
            // Count only files, not folders
            $documents = ProjectDocument::where('project_id', $projectId)
                    ->where('file_type', '!=', 'folder')
                    ->get();

            // Count folders separately
            $folders = ProjectDocument::where('project_id', $projectId)
                    ->where('file_type', 'folder')
                    ->count();

            $totalSize = $documents->sum('file_size');
            $totalFiles = $documents->count();
            $filesByType = $documents->groupBy('file_type')->map->count();

            return [
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'total_files' => $totalFiles,
                'total_folders' => $folders,
                'files_by_type' => $filesByType,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get storage stats: {$e->getMessage()}");
            return ['error' => 'Failed to retrieve storage statistics'];
        }
    }

    /**
     * Get configuration
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * Set custom configuration
     */
    public function setConfig(array $customConfig) {
        $this->config = array_merge($this->config, $customConfig);
    }
}
