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
     * List all documents in a project
     * GET /api/projects/{projectId}/documents
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
     * Get folder structure of a project
     * GET /api/projects/{projectId}/documents/structure
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
     * Upload a file to a project
     * POST /api/projects/{projectId}/documents/upload
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
     * Download a document
     * GET /api/projects/{projectId}/documents/{documentId}/download
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
     * GET /api/projects/{projectId}/documents/{documentId}
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
     * Update document (rename/description)
     * PUT /api/projects/{projectId}/documents/{documentId}
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
     * Delete a document
     * DELETE /api/projects/{projectId}/documents/{documentId}
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
     * Create a folder
     * POST /api/projects/{projectId}/documents/folders
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
     * Get project storage statistics
     * GET /api/projects/{projectId}/documents/stats
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
