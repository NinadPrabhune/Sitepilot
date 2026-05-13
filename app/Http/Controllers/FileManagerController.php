<?php

namespace App\Http\Controllers;

use App\Models\ProjectFileNew;
use App\Services\ProjectFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\UserProject;

class FileManagerController extends Controller
{
    protected $fileService;

    public function __construct(ProjectFileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Display file manager dashboard
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $activeProjectId = getActiveProject();

            // Get active project
            $activeProject = Project::find($activeProjectId);
            
            if (!$activeProject) {
                return redirect()->route('dashboard')
                    ->with('error', __('Please select a project first'));
            }

            // Check access
            if (!$this->fileService->userHasProjectAccess($user->id, $activeProjectId)) {
                return redirect()->route('dashboard')
                    ->with('error', __('You do not have access to this project'));
            }

            // Get user's projects
            $userProjects = UserProject::where('user_id', $user->id)
                ->with('project')
                ->get()
                ->pluck('project')
                ->filter();

            // Get root files/folders
            $folderPath = $request->query('folder', '');
            $contents = $this->fileService->getFolderContents($activeProjectId, $folderPath);

            // Get breadcrumbs
            $breadcrumbs = $this->getBreadcrumbs($activeProjectId, $folderPath);

            // Get folder structure for sidebar
            $folderStructure = $this->fileService->getFolderStructure($activeProjectId);

            // Get storage stats
            $storageStats = $this->fileService->getStorageStats($activeProjectId);

            return view('file-manager.index', [
                'activeProject' => $activeProject,
                'activeProjectId' => $activeProjectId,
                'userProjects' => $userProjects,
                'contents' => $contents,
                'currentFolder' => $folderPath,
                'breadcrumbs' => $breadcrumbs,
                'folderStructure' => $folderStructure,
                'storageStats' => $storageStats,
            ]);

        } catch (\Exception $e) {
            Log::error("File manager error: {$e->getMessage()}");
            return redirect()->back()->with('error', __('Failed to load file manager'));
        }
    }

    /**
     * Switch project
     */
    public function switchProject(Request $request, $projectId)
    {
        try {
            $user = Auth::user();

            // Verify user has access
            if (!$this->fileService->userHasProjectAccess($user->id, $projectId)) {
                return redirect()->back()->with('error', __('Unauthorized'));
            }

            // Update active project
            $user->update(['active_project' => $projectId]);

            return redirect()->route('file-manager.index')
                ->with('success', __('Project switched successfully'));

        } catch (\Exception $e) {
            Log::error("Switch project error: {$e->getMessage()}");
            return redirect()->back()->with('error', __('Failed to switch project'));
        }
    }

    /**
     * Upload file
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'folder' => 'nullable|string',
            ]);

            $user = Auth::user();
            $activeProjectId = getActiveProject();
            $folderPath = $request->input('folder', '');

            // Verify access
            if (!$this->fileService->userHasProjectAccess($user->id, $activeProjectId)) {
                return response()->json(['error' => __('Unauthorized')], 403);
            }

            $result = $this->fileService->uploadFile(
                $request->file('file'),
                $activeProjectId,
                $user->id,
                $folderPath,
                $request->input('description')
            );

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 422);
            }

            return response()->json([
                'success' => true,
                'file' => $result['file'],
                'message' => __('File uploaded successfully'),
            ]);

        } catch (\Exception $e) {
            Log::error("Upload error: {$e->getMessage()}");
            return response()->json(['error' => __('Upload failed')], 500);
        }
    }

    /**
     * Create folder
     */
    public function createFolder(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'folder' => 'nullable|string',
            ]);

            $user = Auth::user();
            $activeProjectId = getActiveProject();

            // Verify access
            if (!$this->fileService->userHasProjectAccess($user->id, $activeProjectId)) {
                return response()->json(['error' => __('Unauthorized')], 403);
            }

            $result = $this->fileService->createFolder(
                $activeProjectId,
                $user->id,
                $request->input('name'),
                $request->input('folder', '')
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
            return response()->json(['error' => __('Creation failed')], 500);
        }
    }

    /**
     * Download file
     */
    public function download($fileId)
    {
        try {
            $user = Auth::user();
            $activeProjectId = getActiveProject();

            // Verify access
            if (!$this->fileService->userHasProjectAccess($user->id, $activeProjectId)) {
                return redirect()->back()->with('error', __('Unauthorized'));
            }

            $file = $this->fileService->getFile($activeProjectId, $fileId);

            if (!$file || $file->is_folder) {
                return redirect()->back()->with('error', __('File not found'));
            }

            // Check authorization
            $this->authorize('download', $file);

            // Record download
            $file->recordDownload();

            return Storage::disk($file->disk)->download(
                $file->file_path,
                $file->name
            );

        } catch (\Exception $e) {
            Log::error("Download error: {$e->getMessage()}");
            return redirect()->back()->with('error', __('Download failed'));
        }
    }

    /**
     * Rename file or folder
     */
    public function rename(Request $request, $fileId)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $user = Auth::user();
            $activeProjectId = getActiveProject();

            $file = $this->fileService->getFile($activeProjectId, $fileId);

            if (!$file) {
                return response()->json(['error' => __('File not found')], 404);
            }

            // Check authorization
            $this->authorize('rename', $file);

            $result = $this->fileService->rename($file, $request->input('name'));

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 422);
            }

            return response()->json([
                'success' => true,
                'file' => $result['file'],
                'message' => __('Renamed successfully'),
            ]);

        } catch (\Exception $e) {
            Log::error("Rename error: {$e->getMessage()}");
            return response()->json(['error' => __('Rename failed')], 500);
        }
    }

    /**
     * Delete file or folder
     */
    public function delete($fileId)
    {
        try {
            $user = Auth::user();
            $activeProjectId = getActiveProject();

            $file = $this->fileService->getFile($activeProjectId, $fileId);

            if (!$file) {
                return response()->json(['error' => __('File not found')], 404);
            }

            // Check authorization
            $this->authorize('delete', $file);

            $result = $this->fileService->delete($file);

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 422);
            }

            return response()->json([
                'success' => true,
                'message' => __('Deleted successfully'),
            ]);

        } catch (\Exception $e) {
            Log::error("Delete error: {$e->getMessage()}");
            return response()->json(['error' => __('Delete failed')], 500);
        }
    }

    /**
     * Make file public
     */
    public function makePublic($fileId)
    {
        try {
            $user = Auth::user();
            $activeProjectId = getActiveProject();

            $file = $this->fileService->getFile($activeProjectId, $fileId);

            if (!$file) {
                return response()->json(['error' => __('File not found')], 404);
            }

            $this->authorize('makePublic', $file);

            $file->update(['is_public' => true]);

            return response()->json([
                'success' => true,
                'message' => __('File is now public'),
            ]);

        } catch (\Exception $e) {
            Log::error("Make public error: {$e->getMessage()}");
            return response()->json(['error' => __('Operation failed')], 500);
        }
    }

    /**
     * Archive file
     */
    public function archive($fileId)
    {
        try {
            $user = Auth::user();
            $activeProjectId = getActiveProject();

            $file = $this->fileService->getFile($activeProjectId, $fileId);

            if (!$file) {
                return response()->json(['error' => __('File not found')], 404);
            }

            $this->authorize('archive', $file);

            $file->archive();

            return response()->json([
                'success' => true,
                'message' => __('File archived successfully'),
            ]);

        } catch (\Exception $e) {
            Log::error("Archive error: {$e->getMessage()}");
            return response()->json(['error' => __('Operation failed')], 500);
        }
    }

    /**
     * Get breadcrumbs
     */
    private function getBreadcrumbs($projectId, $folderPath)
    {
        $breadcrumbs = [
            ['name' => __('Root'), 'path' => '']
        ];

        if (empty($folderPath)) {
            return $breadcrumbs;
        }

        $parts = explode('/', trim($folderPath, '/'));
        $currentPath = '';

        foreach ($parts as $part) {
            if (empty($part)) continue;
            
            $currentPath .= $part . '/';
            $breadcrumbs[] = [
                'name' => $part,
                'path' => rtrim($currentPath, '/'),
            ];
        }

        return $breadcrumbs;
    }
}
