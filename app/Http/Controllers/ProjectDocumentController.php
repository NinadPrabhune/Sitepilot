<?php

namespace App\Http\Controllers;

use App\Models\ProjectDocument;
use App\Services\ProjectDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Workdo\Taskly\Entities\Project;

class ProjectDocumentController extends Controller
{
    protected $documentService;

    public function __construct(ProjectDocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display project documents page
     */
    public function index()
    {
        try {
            $user = Auth::user();
            
//            dd($user->id);
            
            // Get all projects user has access to
            $userProjects = \Workdo\Taskly\Entities\UserProject::where('user_id', $user->id)
                ->with('project')
                ->get()
                ->pluck('project');

//            dd($userProjects);
            // Get active project
            $activeProjectId = getActiveProject();
            $activeProject = Project::find($activeProjectId);

            // Get documents for active project if exists
            $documents = collect();
            $projectRootPath = null;
            
            if ($activeProject) {
                $documents = ProjectDocument::where('project_id', $activeProjectId)
                    ->where(function($query) {
                        $query->where('folder_path', '')
                              ->orWhereNull('folder_path');
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();
                
                $projectRootPath = $this->documentService->getProjectRootPath($activeProjectId);
            }

            // Get folder structure
            $folderStructure = $activeProject ? 
                $this->documentService->getProjectFolderStructure($activeProjectId) : 
                [];

            // Get storage stats
            $storageStats = $activeProject ? 
                $this->documentService->getProjectStorageStats($activeProjectId) : 
                [];

            return view('project-documents.index', [
                'projects' => $userProjects,
                'activeProject' => $activeProject,
                'activeProjectId' => $activeProjectId,
                'documents' => $documents,
                'folderStructure' => $folderStructure,
                'storageStats' => $storageStats,
                'projectRootPath' => $projectRootPath,
            ]);

        } catch (\Exception $e) {
            Log::error("Error loading project documents: {$e->getMessage()}");
            return redirect()->back()->with('error', __('Failed to load project documents'));
        }
    }

    /**
     * Get documents in a specific folder
     */
    public function getFolder($projectId)
    {
        try {
            // Check authorization
            if (!$this->documentService->userHasProjectAccess(Auth::id(), $projectId)) {
                return response()->json(['error' => __('Unauthorized')], 403);
            }

            // Get folder path from query parameter
            $folderPath = request()->query('folder_path', '');

            $documents = $this->documentService->getProjectFiles($projectId, $folderPath);

            return response()->json([
                'success' => true,
                'documents' => $documents,
                'folder_path' => $folderPath,
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting folder: {$e->getMessage()}");
            return response()->json(['error' => __('Failed to retrieve folder')], 500);
        }
    }

    /**
     * Upload a file
     */
    public function upload(Request $request)
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file',
                'project_id' => 'required|integer|exists:projects,id',
                'folder_path' => 'nullable|string',
            ]);
            
            
   

            $projectId = $validated['project_id'];
            $fileSize = $request->file('file')->getSize();

            // Calculate current usage
            $currentUsage = ProjectDocument::where('project_id', $projectId)->sum('file_size');

            $maxQuota = 300 * 1024 * 1024; // 300MB in bytes

            if (($currentUsage + $fileSize) > $maxQuota) {
                return response()->json([
                    'success' => false,
                    'error' => __('Project storage limit exceeded (300MB).'),
                ], 422);
            }


            

            // Check authorization
            if (!$this->documentService->userHasProjectAccess(Auth::id(), $validated['project_id'])) {
                return response()->json(['error' => __('Unauthorized')], 403);
            }

            $result = $this->documentService->uploadFile(
                $request->file('file'),
                $validated['project_id'],
                Auth::id(),
                $validated['folder_path'] ?? ''
            );

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 422);
            }

            return response()->json([
                'success' => true,
                'document' => $result['document'],
                'message' => __('File uploaded successfully'),
            ]);

        } catch (\Exception $e) {
            Log::error("File upload error: {$e->getMessage()}");
            return response()->json(['error' => __('File upload failed')], 500);
        }
    }

    /**
     * Download a document
     */
    public function download($projectId, $documentId)
    {
        try {
            // Check authorization
            if (!$this->documentService->userHasProjectAccess(Auth::id(), $projectId)) {
                return redirect()->back()->with('error', __('Unauthorized'));
            }

            $result = $this->documentService->downloadDocument($documentId, $projectId);

            if (isset($result['error'])) {
                return redirect()->back()->with('error', $result['error']);
            }

            return response()->download($result['file'], $result['name']);

        } catch (\Exception $e) {
            Log::error("Download error: {$e->getMessage()}");
            return redirect()->back()->with('error', __('Download failed'));
        }
    }

    /**
     * Delete a document
     */
    public function delete(Request $request, $projectId, $documentId)
    {
        try {
            // Check authorization
            if (!$this->documentService->userHasProjectAccess(Auth::id(), $projectId)) {
                return response()->json(['error' => __('Unauthorized')], 403);
            }

            $result = $this->documentService->deleteDocument($documentId, $projectId);

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 422);
            }

            return response()->json(['success' => true, 'message' => __('Document deleted successfully')]);

        } catch (\Exception $e) {
            Log::error("Delete error: {$e->getMessage()}");
            return response()->json(['error' => __('Delete failed')], 500);
        }
    }

    /**
     * Rename a document
     */
    public function rename(Request $request, $projectId, $documentId)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // Check authorization
            if (!$this->documentService->userHasProjectAccess(Auth::id(), $projectId)) {
                return response()->json(['error' => __('Unauthorized')], 403);
            }

            $result = $this->documentService->renameDocument($documentId, $projectId, $validated['name']);

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 422);
            }

            return response()->json([
                'success' => true,
                'document' => $result['document'],
                'message' => __('Document renamed successfully'),
            ]);

        } catch (\Exception $e) {
            Log::error("Rename error: {$e->getMessage()}");
            return response()->json(['error' => __('Rename failed')], 500);
        }
    }

    /**
     * Create a folder
     */
    public function createFolder(Request $request, $projectId)
    {
        try {
            $validated = $request->validate([
                'folder_name' => 'required|string|max:255',
            ]);

            // Check authorization
            if (!$this->documentService->userHasProjectAccess(Auth::id(), $projectId)) {
                return response()->json(['error' => __('Unauthorized')], 403);
            }

            $result = $this->documentService->createFolder(
                $projectId,
                Auth::id(),
                $validated['folder_name']
            );

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 422);
            }

            return response()->json([
                'success' => true,
                'folder' => $result['folder'],
                'message' => __('Folder created successfully'),
            ]);

        } catch (\Exception $e) {
            Log::error("Create folder error: {$e->getMessage()}");
            return response()->json(['error' => __('Failed to create folder')], 500);
        }
    }

    /**
     * Switch active project
     */
    public function switchProject(Request $request, $projectId)
    {
        try {
            // Check authorization
            if (!$this->documentService->userHasProjectAccess(Auth::id(), $projectId)) {
                return response()->json(['error' => __('Unauthorized')], 403);
            }

            // Update user's active project
            $user = Auth::user();
            $user->update(['active_project' => $projectId]);

            return response()->json([
                'success' => true,
                'message' => __('Active project switched successfully'),
            ]);

        } catch (\Exception $e) {
            Log::error("Switch project error: {$e->getMessage()}");
            return response()->json(['error' => __('Failed to switch project')], 500);
        }
    }

    /**
     * Get project statistics
     */
    public function getStats($projectId)
    {
        try {
            // Check authorization
            if (!$this->documentService->userHasProjectAccess(Auth::id(), $projectId)) {
                return response()->json(['error' => __('Unauthorized')], 403);
            }

            $stats = $this->documentService->getProjectStorageStats($projectId);

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error("Get stats error: {$e->getMessage()}");
            return response()->json(['error' => __('Failed to retrieve statistics')], 500);
        }
    }
}
