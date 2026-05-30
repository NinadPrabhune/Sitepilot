<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Project Documents
 * Endpoints for project document management including upload, download, and folder operations
 */
use App\Http\Resources\ProjectDocumentResource;
use App\Http\Resources\ProjectDocumentCollection;
use App\Models\ProjectDocument;
use App\Services\ProjectDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
 

class ProjectDocumentApiController extends Controller {

    protected $documentService;

    public function __construct(ProjectDocumentService $documentService) {
        $this->documentService = $documentService;
    }

    /**
     * List project documents
     *
     * Returns all documents in a project's folder.
     *
     * @authenticated
     * @requiredPermission project-document manage
     *
     * @urlParam projectId integer required Project ID. Example: 1
     * @bodyParam folder string optional Folder path to list files from. Example: /Contracts
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": [...], "meta": {"project_id": 1, "folder_path": "", "total_count": 5} }
     * @response status=401 scenario="Unauthorized"
     * { "error": "Unauthorized - Invalid or missing token", "status": 401 }
     * @response status=403 scenario="No access"
     * { "error": "Unauthorized - You do not have access to this project", "status": 403 }
     */
    public function index($projectId) {
        if (!Auth::user()->isAbleTo('project-document manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Get authenticated user via Sanctum
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                            'error' => 'Unauthorized - Invalid or missing token',
                            'status' => 401
                                ], 401);
            }

            // Authorization check
            if (!$this->documentService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                            'error' => 'Unauthorized - You do not have access to this project',
                            'status' => 403
                                ], 403);
            }

            // Get folder from query parameter
            $folderPath = request()->query('folder', '');

            $documents = $this->documentService->getProjectFiles($projectId, $folderPath);

            return response()->json([
                        'success' => true,
                        'status' => 200,
                        'data' => ProjectDocumentResource::collection($documents),
                        'meta' => [
                            'project_id' => $projectId,
                            'folder_path' => $folderPath,
                            'total_count' => $documents->count(),
                        ]
            ]);
        } catch (\Exception $e) {
            Log::error("API: List documents error: {$e->getMessage()}");
            return response()->json([
                        'error' => 'Failed to retrieve documents',
                        'status' => 500
                            ], 500);
        }
    }

    /**
     * Get folder structure
     *
     * Returns the folder hierarchy of a project.
     *
     * @authenticated
     * @requiredPermission project-document manage
     *
     * @urlParam projectId integer required Project ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": {"root_files": [...], "folders": [...], "folder_count": 3} }
     * @response status=401 scenario="Unauthorized"
     * { "error": "Unauthorized - Invalid or missing token", "status": 401 }
     */
    public function getFolderStructure($projectId) {
        if (!Auth::user()->isAbleTo('project-document manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Get authenticated user via Sanctum
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                            'error' => 'Unauthorized - Invalid or missing token',
                            'status' => 401
                                ], 401);
            }

            if (!$this->documentService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                            'error' => 'Unauthorized',
                            'status' => 403
                                ], 403);
            }

            $structure = $this->documentService->getProjectFolderStructure($projectId);

            if (isset($structure['error'])) {
                return response()->json([
                            'error' => $structure['error'],
                            'status' => 500
                                ], 500);
            }

            return response()->json([
                        'success' => true,
                        'status' => 200,
                        'data' => [
                            'root_files' => ProjectDocumentResource::collection($structure['root']),
                            'folders' => $structure['folders'], // just folder names
                            'folder_list' => $structure['folders'],
                            'folder_count' => $structure['folder_count']
                        ]
            ]);
        } catch (\Exception $e) {
            Log::error("API: Get folder structure error: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                        'error' => 'Failed to retrieve folder structure',
                        'status' => 500
                            ], 500);
        }
    }

    /**
     * Get flat folder structure
     *
     * Returns a non-nested list of all folders in a project.
     *
     * @authenticated
     *
     * @urlParam projectId integer required Project ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": {"root_files": [...], "folders": [...], "folder_count": 3} }
     * @response status=403 scenario="Unauthorized"
     * { "error": "Unauthorized", "status": 403 }
     */
    public function getFolderStructureFlat($projectId) {
        $user = auth()->user();
        if (!$user || !$this->documentService->userHasProjectAccess($user->id, $projectId)) {
            return response()->json(['error' => 'Unauthorized', 'status' => 403], 403);
        }

        $structure = $this->documentService->getProjectFolderStructureFlat($projectId);

        return response()->json([
                    'success' => true,
                    'status' => 200,
                    'data' => [
                        'root_files' => ProjectDocumentResource::collection($structure['root']),
                        'folders' => $structure['folders'],
                        'folder_list' => $structure['folders'],
                        'folder_count' => $structure['folder_count'],
                    ]
        ]);
    }

    /**
     * Get nested folder structure
     *
     * Returns recursive folder tree for a project. (Internal/recursive method.)
     *
     * @urlParam projectId integer required Project ID. Example: 1
     * @urlParam parentPath string optional Parent folder path. Example: /Contracts
     *
     * @return array
     */
    public function getProjectFolderStructureNested($projectId, $parentPath = null) {
        $items = ProjectDocument::where('project_id', $projectId)
                ->where('folder_path', $parentPath) // null for root
                ->get();

        $structure = [
            'files' => [],
            'folders' => []
        ];

        foreach ($items as $item) {
            if ($item->file_type === 'folder') {
                $structure['folders'][$item->file_name] = $this->getProjectFolderStructureNested(
                        $projectId,
                        $item->file_name // use folder_name as folder_path for children
                );
            } else {
                $structure['files'][] = $item;
            }
        }

        return $structure;
    }

    /**
     * Upload document
     *
     * Uploads a file to a project folder.
     *
     * @authenticated
     * @requiredPermission project-document create
     *
     * @urlParam projectId integer required Project ID. Example: 1
     * @bodyParam file file required The file to upload.
     * @bodyParam folder_path string optional Target folder path. Example: /Contracts
     * @bodyParam description string optional File description. Example: Signed contract
     *
     * @response status=201 scenario="Uploaded"
     * { "success": true, "message": "File uploaded successfully", "data": {...} }
     * @response status=422 scenario="Validation error"
     * { "error": "...", "status": 422 }
     * @response status=401 scenario="Unauthorized"
     * { "error": "Unauthorized - Invalid or missing token", "status": 401 }
     */
    public function upload(Request $request, $projectId) {
        if (!Auth::user()->isAbleTo('project-document create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Get authenticated user via Sanctum
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                            'error' => 'Unauthorized - Invalid or missing token',
                            'status' => 401
                                ], 401);
            }

            $validated = $request->validate([
                'file' => 'required|file',
                'folder_path' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:500',
            ]);

            // Authorization check
            if (!$this->documentService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                            'error' => 'Unauthorized',
                            'status' => 403
                                ], 403);
            }

            $result = $this->documentService->uploadFile(
                    $request->file('file'),
                    $projectId,
                    $user->id,
                    $validated['folder_path'] ?? ''
            );

            if (isset($result['error'])) {
                return response()->json([
                            'error' => $result['error'],
                            'status' => 422
                                ], 422);
            }

            // Add description if provided
            if (!empty($validated['description'])) {
                $result['document']->update(['description' => $validated['description']]);
            }

            return response()->json([
                        'success' => true,
                        'status' => 201,
                        'message' => 'File uploaded successfully',
                        'data' => new ProjectDocumentResource($result['document'])
                            ], 201);
        } catch (\Exception $e) {
            Log::error("API: File upload error: {$e->getMessage()}");
            return response()->json([
                        'error' => 'File upload failed: ' . $e->getMessage(),
                        'status' => 500
                            ], 500);
        }
    }

    /**
     * Download document
     *
     * Downloads a document file from a project.
     *
     * @authenticated
     * @requiredPermission project-document show
     *
     * @urlParam projectId integer required Project ID. Example: 1
     * @urlParam documentId integer required Document ID. Example: 5
     *
     * @response status=200 scenario="Success"
     * (file download)
     * @response status=404 scenario="Not found"
     * { "error": "...", "status": 404 }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function download($projectId, $documentId) {
        if (!Auth::user()->isAbleTo('project-document show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Get authenticated user via Sanctum
            $user = auth()->user();
            if (!$user || !$this->documentService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                            'error' => 'Unauthorized',
                            'status' => 403
                                ], 403);
            }

            $result = $this->documentService->downloadDocument($documentId, $projectId);

            if (isset($result['error'])) {
                return response()->json([
                            'error' => $result['error'],
                            'status' => 404
                                ], 404);
            }

            return response()->download($result['file'], $result['name']);
        } catch (\Exception $e) {
            Log::error("API: Download error: {$e->getMessage()}");
            return response()->json([
                        'error' => 'Download failed',
                        'status' => 500
                            ], 500);
        }
    }

    /**
     * Get document details
     *
     * Returns metadata for a single document.
     *
     * @authenticated
     * @requiredPermission project-document show
     *
     * @urlParam projectId integer required Project ID. Example: 1
     * @urlParam documentId integer required Document ID. Example: 5
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": {...} }
     * @response status=404 scenario="Not found"
     * { "error": "Document not found", "status": 404 }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function show($projectId, $documentId) {
        if (!Auth::user()->isAbleTo('project-document show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Get authenticated user via Sanctum
            $user = auth()->user();
            if (!$user || !$this->documentService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                            'error' => 'Unauthorized',
                            'status' => 403
                                ], 403);
            }

            $document = ProjectDocument::where('id', $documentId)
                    ->where('project_id', $projectId)
                    ->first();

            if (!$document) {
                return response()->json([
                            'error' => 'Document not found',
                            'status' => 404
                                ], 404);
            }

            return response()->json([
                        'success' => true,
                        'status' => 200,
                        'data' => new ProjectDocumentResource($document)
            ]);
        } catch (\Exception $e) {
            Log::error("API: Show document error: {$e->getMessage()}");
            return response()->json([
                        'error' => 'Failed to retrieve document',
                        'status' => 500
                            ], 500);
        }
    }

    /**
     * Update document
     *
     * Renames or updates the description of a document.
     *
     * @authenticated
     * @requiredPermission project-document edit
     *
     * @urlParam projectId integer required Project ID. Example: 1
     * @urlParam documentId integer required Document ID. Example: 5
     * @bodyParam file_name string optional New file name. Example: revised-contract.pdf
     * @bodyParam description string optional New description. Example: Revised version
     *
     * @response status=200 scenario="Updated"
     * { "success": true, "message": "Document updated successfully", "data": {...} }
     * @response status=404 scenario="Not found"
     * { "error": "Document not found", "status": 404 }
     * @response status=401 scenario="Unauthorized"
     * { "error": "Unauthorized - Invalid or missing token", "status": 401 }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function update(Request $request, $projectId, $documentId) {
        if (!Auth::user()->isAbleTo('project-document edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Get authenticated user via Sanctum
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                            'error' => 'Unauthorized - Invalid or missing token',
                            'status' => 401
                                ], 401);
            }

            $validated = $request->validate([
                'file_name' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:500',
            ]);

            if (!$this->documentService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                            'error' => 'Unauthorized',
                            'status' => 403
                                ], 403);
            }

            $document = ProjectDocument::where('id', $documentId)
                    ->where('project_id', $projectId)
                    ->first();

            if (!$document) {
                return response()->json([
                            'error' => 'Document not found',
                            'status' => 404
                                ], 404);
            }

            $updateData = [];

            if (!empty($validated['file_name'])) {
                $updateData['file_name'] = $validated['file_name'];
            }

            if (isset($validated['description'])) {
                $updateData['description'] = $validated['description'];
            }

            if (!empty($updateData)) {
                $document->update($updateData);
            }

            return response()->json([
                        'success' => true,
                        'status' => 200,
                        'message' => 'Document updated successfully',
                        'data' => new ProjectDocumentResource($document->fresh())
            ]);
        } catch (\Exception $e) {
            Log::error("API: Update document error: {$e->getMessage()}");
            return response()->json([
                        'error' => 'Failed to update document',
                        'status' => 500
                            ], 500);
        }
    }

    /**
     * Delete document
     *
     * Deletes a document from a project.
     *
     * @authenticated
     * @requiredPermission project-document delete
     *
     * @urlParam projectId integer required Project ID. Example: 1
     * @urlParam documentId integer required Document ID. Example: 5
     *
     * @response status=200 scenario="Deleted"
     * { "success": true, "message": "Document deleted successfully" }
     * @response status=422 scenario="Error"
     * { "error": "...", "status": 422 }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function delete($projectId, $documentId) {
        if (!Auth::user()->isAbleTo('project-document delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $user = auth()->user();
            if (!$user || !$this->documentService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                            'error' => 'Unauthorized',
                            'status' => 403
                                ], 403);
            }

            $result = $this->documentService->deleteDocument($documentId, $projectId);

            if (isset($result['error'])) {
                return response()->json([
                            'error' => $result['error'],
                            'status' => 422
                                ], 422);
            }

            return response()->json([
                        'success' => true,
                        'status' => 200,
                        'message' => 'Document deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("API: Delete error: {$e->getMessage()}");
            return response()->json([
                        'error' => 'Failed to delete document',
                        'status' => 500
                            ], 500);
        }
    }

    /**
     * Create folder
     *
     * Creates a new folder in a project.
     *
     * @authenticated
     * @requiredPermission project-document create
     *
     * @urlParam projectId integer required Project ID. Example: 1
     * @bodyParam folder_name string required Folder name. Example: Contracts
     *
     * @response status=201 scenario="Created"
     * { "success": true, "status": 201, "message": "Folder created successfully", "data": {"folder_path": "Contracts"} }
     * @response status=422 scenario="Validation error"
     * { "error": "...", "status": 422 }
     * @response status=401 scenario="Unauthorized"
     * { "error": "Unauthorized - Invalid or missing token", "status": 401 }
     * @response status=403 scenario="Permission denied"
     * { "status": 0, "message": "Permission denied" }
     */
    public function createFolder(Request $request, $projectId) {
        if (!Auth::user()->isAbleTo('project-document create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                            'error' => 'Unauthorized - Invalid or missing token',
                            'status' => 401
                                ], 401);
            }

            $validated = $request->validate([
                'folder_name' => 'required|string|max:255',
            ]);

            if (!$this->documentService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                            'error' => 'Unauthorized',
                            'status' => 403
                                ], 403);
            }

            $result = $this->documentService->createFolder(
                    $projectId,
                    $user->id,
                    $validated['folder_name']
            );

            if (isset($result['error'])) {
                return response()->json([
                            'error' => $result['error'],
                            'status' => 422
                                ], 422);
            }

            return response()->json([
                        'success' => true,
                        'status' => 201,
                        'message' => 'Folder created successfully',
                        'data' => [
                            'folder_path' => $result['folder']
                        ]
                            ], 201);
        } catch (\Exception $e) {
            Log::error("API: Create folder error: {$e->getMessage()}");
            return response()->json([
                        'error' => 'Failed to create folder',
                        'status' => 500
                            ], 500);
        }
    }

    /**
     * Get storage statistics
     *
     * Returns storage usage statistics for a project.
     *
     * @authenticated
     *
     * @urlParam projectId integer required Project ID. Example: 1
     *
     * @response status=200 scenario="Success"
     * { "success": true, "data": {"total_size": 1048576, "file_count": 10, ...} }
     * @response status=403 scenario="Unauthorized"
     * { "error": "Unauthorized", "status": 403 }
     */
    public function getStats($projectId) {
        try {
            $user = auth()->user();
            if (!$user || !$this->documentService->userHasProjectAccess($user->id, $projectId)) {
                return response()->json([
                            'error' => 'Unauthorized',
                            'status' => 403
                                ], 403);
            }

            $stats = $this->documentService->getProjectStorageStats($projectId);

            if (isset($stats['error'])) {
                return response()->json([
                            'error' => $stats['error'],
                            'status' => 500
                                ], 500);
            }

            return response()->json([
                        'success' => true,
                        'status' => 200,
                        'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error("API: Get stats error: {$e->getMessage()}");
            return response()->json([
                        'error' => 'Failed to retrieve statistics',
                        'status' => 500
                            ], 500);
        }
    }
}
