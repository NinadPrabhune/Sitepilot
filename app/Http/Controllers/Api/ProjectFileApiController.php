<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Project Files
 * Endpoints for project file management with folder structure support
 */
use App\Models\ProjectFileNew;
use App\Services\ProjectFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProjectFileApiController extends Controller
{
    protected $fileService;

    public function __construct(ProjectFileService $fileService)
    {
        $this->fileService = $fileService;
        $this->middleware('auth:sanctum');
    }

    /**
     * List project files
     *
     * Returns files in a project folder.
     *
     * @authenticated
     * @requiredPermission project-file manage
     *
     * @bodyParam project_id integer required Project ID. Example: 1
     * @bodyParam folder string optional Folder path. Example: /Contracts
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": [...], "folder_path": "/" }
     * @response status=403 scenario="Unauthorized"
     * { "success": false, "message": "Unauthorized" }
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('project-file manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $request->validate([
                'project_id' => 'required|integer',
                'folder' => 'nullable|string',
            ]);

            $projectId = $request->input('project_id');
            $folderPath = $request->input('folder', '');

            $user = Auth::user();

            // Check access
            if (!$this->fileService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $files = $this->fileService->getFolderContents($projectId, $folderPath);

            return response()->json([
                'success' => true,
                'data' => $files->map(function ($file) {
                    return $this->formatFileForApi($file);
                }),
                'folder_path' => $folderPath,
            ]);

        } catch (\Exception $e) {
            Log::error("API get files error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve files',
            ], 500);
        }
    }

    /**
     * Get file details
     *
     * Returns metadata for a single file.
     *
     * @authenticated
     * @requiredPermission project-file show
     *
     * @urlParam id integer required File ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": {"id": 1, "name": "document.pdf", ...} }
     * @response status=404 scenario="Not found"
     * { "success": false, "message": "File not found" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function show($id)
    {
        if (!Auth::user()->isAbleTo('project-file show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $file = ProjectFileNew::find($id);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            $user = Auth::user();

            // Check access
            if (!$this->fileService->userHasProjectAccess($user->id, $file->project_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatFileForApi($file),
            ]);

        } catch (\Exception $e) {
            Log::error("API get file error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve file',
            ], 500);
        }
    }

    /**
     * Upload file
     *
     * Uploads a file to a project.
     *
     * @authenticated
     * @requiredPermission project-file create
     *
     * @bodyParam file file required The file to upload.
     * @bodyParam project_id integer required Project ID. Example: 1
     * @bodyParam folder string optional Target folder path. Example: /Contracts
     * @bodyParam description string optional File description. Example: Signed contract
     *
     * @response status=201 scenario="Uploaded"
     * { "success": true, "message": "File uploaded successfully", "data": {...} }
     * @response status=422 scenario="Validation error"
     * { "success": false, "message": "..." }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('project-file create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $request->validate([
                'file' => 'required|file',
                'project_id' => 'required|integer',
                'folder' => 'nullable|string',
                'description' => 'nullable|string',
            ]);

            $user = Auth::user();
            $projectId = $request->input('project_id');

            // Check access
            if (!$this->fileService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $result = $this->fileService->uploadFile(
                $request->file('file'),
                $projectId,
                $user->id,
                $request->input('folder', ''),
                $request->input('description', '')
            );

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $this->formatFileForApi($result['file']),
            ], 201);

        } catch (\Exception $e) {
            Log::error("API upload error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Upload failed',
            ], 500);
        }
    }

    /**
     * Create folder
     *
     * Creates a new folder in a project.
     *
     * @authenticated
     *
     * @bodyParam name string required Folder name. Example: Contracts
     * @bodyParam project_id integer required Project ID. Example: 1
     * @bodyParam folder string optional Parent folder path. Example: /
     *
     * @response status=201 scenario="Created"
     * { "success": true, "message": "Folder created successfully", "data": {...} }
     * @response status=422 scenario="Validation error"
     * { "success": false, "message": "..." }
     * @response status=403 scenario="Unauthorized"
     * { "success": false, "message": "Unauthorized" }
     */
    public function createFolder(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'project_id' => 'required|integer',
                'folder' => 'nullable|string',
            ]);

            $user = Auth::user();
            $projectId = $request->input('project_id');

            // Check access
            if (!$this->fileService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $result = $this->fileService->createFolder(
                $projectId,
                $user->id,
                $request->input('name'),
                $request->input('folder', '')
            );

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Folder created successfully',
                'data' => $this->formatFileForApi($result['folder']),
            ], 201);

        } catch (\Exception $e) {
            Log::error("API create folder error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Creation failed',
            ], 500);
        }
    }

    /**
     * Download file
     *
     * Downloads a file from a project.
     *
     * @authenticated
     *
     * @urlParam id integer required File ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * (file download)
     * @response status=404 scenario="Not found"
     * { "success": false, "message": "File not found" }
     * @response status=403 scenario="Unauthorized"
     * { "success": false, "message": "Unauthorized" }
     */
    public function download($id)
    {
        try {
            $file = ProjectFileNew::find($id);

            if (!$file || $file->is_folder) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            $user = Auth::user();

            // Check access
            if (!$this->fileService->userHasProjectAccess($user->id, $file->project_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $file->recordDownload();

            return Storage::disk($file->disk)->download(
                $file->file_path,
                $file->name
            );

        } catch (\Exception $e) {
            Log::error("API download error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Download failed',
            ], 500);
        }
    }

    /**
     * Update file
     *
     * Renames or updates file metadata.
     *
     * @authenticated
     * @requiredPermission project-file edit
     *
     * @urlParam id integer required File ID. Example: 1
     * @bodyParam name string optional New file name. Example: revised-document.pdf
     * @bodyParam description string optional New description. Example: Revised version
     * @bodyParam is_public boolean optional Make file public. Example: true
     *
     * @response status=200 scenario="Updated"
     * { "success": true, "message": "File updated successfully", "data": {...} }
     * @response status=404 scenario="Not found"
     * { "success": false, "message": "File not found" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('project-file edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $request->validate([
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'is_public' => 'nullable|boolean',
            ]);

            $file = ProjectFileNew::find($id);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            $user = Auth::user();

            // Check authorization
            $this->authorize('update', $file);

            // Handle rename
            if ($request->has('name') && $request->input('name') !== $file->name) {
                $result = $this->fileService->rename($file, $request->input('name'));
                if (isset($result['error'])) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['error'],
                    ], 422);
                }
                $file = $result['file'];
            }

            // Update other fields
            if ($request->has('description')) {
                $file->update(['description' => $request->input('description')]);
            }

            if ($request->has('is_public')) {
                $file->update(['is_public' => $request->boolean('is_public')]);
            }

            return response()->json([
                'success' => true,
                'message' => 'File updated successfully',
                'data' => $this->formatFileForApi($file->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error("API update error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Update failed',
            ], 500);
        }
    }

    /**
     * Delete file
     *
     * Deletes a file from a project.
     *
     * @authenticated
     * @requiredPermission project-file delete
     *
     * @urlParam id integer required File ID. Example: 1
     *
     * @response status=200 scenario="Deleted"
     * { "success": true, "message": "File deleted successfully" }
     * @response status=404 scenario="Not found"
     * { "success": false, "message": "File not found" }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function destroy($id)
    {
        if (!Auth::user()->isAbleTo('project-file delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $file = ProjectFileNew::find($id);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            // Check authorization
            $this->authorize('delete', $file);

            $result = $this->fileService->delete($file);

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error("API delete error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Delete failed',
            ], 500);
        }
    }

    /**
     * Get folder tree
     *
     * Returns the folder hierarchy for a project.
     *
     * @authenticated
     *
     * @bodyParam project_id integer required Project ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": {...} }
     * @response status=403 scenario="Unauthorized"
     * { "success": false, "message": "Unauthorized" }
     */
    public function getTree(Request $request)
    {
        try {
            $request->validate([
                'project_id' => 'required|integer',
            ]);

            $projectId = $request->input('project_id');
            $user = Auth::user();

            // Check access
            if (!$this->fileService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $tree = $this->fileService->getFolderStructure($projectId);

            return response()->json([
                'success' => true,
                'data' => $tree,
            ]);

        } catch (\Exception $e) {
            Log::error("API get tree error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve folder structure',
            ], 500);
        }
    }

    /**
     * Get storage stats
     *
     * Returns storage usage statistics for a project.
     *
     * @authenticated
     *
     * @bodyParam project_id integer required Project ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": {"total_size": 1048576, "file_count": 10, ...} }
     * @response status=403 scenario="Unauthorized"
     * { "success": false, "message": "Unauthorized" }
     */
    public function getStats(Request $request)
    {
        try {
            $request->validate([
                'project_id' => 'required|integer',
            ]);

            $projectId = $request->input('project_id');
            $user = Auth::user();

            // Check access
            if (!$this->fileService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $stats = $this->fileService->getStorageStats($projectId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error("API get stats error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve storage stats',
            ], 500);
        }
    }

    /**
     * Search files
     *
     * Searches files within a project by name.
     *
     * @authenticated
     *
     * @bodyParam project_id integer required Project ID. Example: 1
     * @bodyParam query string required Search query (min 2 chars). Example: contract
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": [...] }
     * @response status=422 scenario="Validation error"
     * { "success": false, "message": "..." }
     * @response status=403 scenario="Unauthorized"
     * { "success": false, "message": "Unauthorized" }
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'project_id' => 'required|integer',
                'query' => 'required|string|min:2',
            ]);

            $projectId = $request->input('project_id');
            $query = $request->input('query');
            $user = Auth::user();

            // Check access
            if (!$this->fileService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $results = $this->fileService->search($projectId, $query);

            return response()->json([
                'success' => true,
                'data' => $results->map(function ($file) {
                    return $this->formatFileForApi($file);
                }),
            ]);

        } catch (\Exception $e) {
            Log::error("API search error: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
            ], 500);
        }
    }

    /**
     * Format file for API response
     */
    private function formatFileForApi(ProjectFileNew $file)
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'is_folder' => $file->is_folder,
            'file_path' => $file->file_path,
            'folder_path' => $file->folder_path,
            'mime_type' => $file->mime_type,
            'file_size' => $file->file_size,
            'file_size_formatted' => $file->getHumanFileSize(),
            'description' => $file->description,
            'is_public' => $file->is_public,
            'is_archived' => $file->is_archived,
            'download_count' => $file->download_count,
            'icon' => $file->getFileIcon(),
            'uploaded_by' => $file->uploadedBy ? [
                'id' => $file->uploadedBy->id,
                'name' => $file->uploadedBy->name,
            ] : null,
            'created_at' => $file->created_at,
            'updated_at' => $file->updated_at,
        ];
    }
}
