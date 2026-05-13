<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Workspaces
 * Endpoints for workspace management including CRUD operations and domain configuration
 */
use App\Services\DefaultMasterDataService;
use App\Models\WorkSpace;
use App\Models\CustomDomainRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Workdo\Taskly\Entities\UserProject;
use Workdo\Taskly\Entities\Project;

class WorkSpaceApiController extends Controller {
    
    public function index()
    {
        if (!Auth::user()->isAbleTo('workspace manage')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        try {
            $user = Auth::user();

            // If user has workspace manage permission, return all workspaces
            if ($user->isAbleTo('workspace manage')) {
                $workspaces = WorkSpace::where('status', 'active')->get();
            } else {

                // Get project IDs assigned to the user
                $projectIds = UserProject::where('user_id', $user->id)->pluck('project_id');

                if ($projectIds->isEmpty()) {
                    // If no projects exist, return workspaces created by the user
                    $workspaces = WorkSpace::where('created_by', $user->id)
                        ->where('status', 'active')
                        ->get();
                } else {
                    // Get workspace IDs from projects
                    $workspaceIds = Project::whereIn('id', $projectIds)->pluck('workspace');

                    $workspaces = WorkSpace::whereIn('id', $workspaceIds)
                        ->where('status', 'active')
                        ->get();
                }
            }

            return response()->json(['workspaces' => $workspaces], 200);

        } catch (\Exception $e) {
            Log::error('Workspace index error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve workspaces.'], 500);
        }
    }

//    public function index() {
//        try {
//            $user = Auth::user();
//            $workspaces = WorkSpace::where('status', 'active')->get();
//            
//           
//
//            return response()->json(['workspaces' => $workspaces], 200);
//        } catch (\Exception $e) {
//            Log::error('Workspace index error: ' . $e->getMessage());
//            return response()->json(['error' => 'Failed to retrieve workspaces.'], 500);
//        }
//    }

    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('workspace create')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        try {
            Log::info('WorkSpace API store called', [
                'created_by' => $request->created_by,
                'hasFile' => $request->hasFile('logo'),
                'auth_user' => auth()->user() ? auth()->user()->id : null
            ]);
            
            // Get authenticated user or use created_by from request
            $authenticatedUser = auth()->user();
            $userId = $request->created_by ?? ($authenticatedUser ? $authenticatedUser->id : null);
            
            if (!$userId) {
                Log::warning('WorkSpace store: No user ID available');
                return response()->json(['error' => 'User authentication required. Provide created_by or authenticate.'], 401);
            }
            
            $user = User::find($userId);
            if (!$user) {
                Log::warning('WorkSpace store: User not found', ['user_id' => $userId]);
                return response()->json(['error' => 'User not found.', 'user_id' => $userId], 404);
            }
            
            // Get fillable fields from the model
            $fillableFields = (new WorkSpace())->getFillable();
            
            // Build validation rules dynamically
            $validationRules = [];
            foreach ($fillableFields as $field) {
                switch ($field) {
                    case 'name':
                        $validationRules[$field] = 'required|string|max:255';
                        break;                   
                    case 'created_by':
                        $validationRules[$field] = 'required|integer';
                        break;
                    case 'email':
                        $validationRules[$field] = 'nullable|email|max:255';
                        break;
                    case 'phone':
                    case 'pincode':
                        $validationRules[$field] = 'nullable|string|max:20';
                        break;
                    case 'gst_number':
                    case 'pan_number':
                    case 'ifsc_code':
                    case 'bank_name':
                    case 'account_number':
                        $validationRules[$field] = 'nullable|string|max:50';
                        break;
                    case 'logo':
                        $validationRules[$field] = 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048';
                        break;
                    
                    case 'enable_domain':
                    
                    default:
                        $validationRules[$field] = 'nullable|string|max:255';
                        break;
                }
            }
            
            // Add additional validation for domain-related fields
            $validationRules['domain_switch'] = 'nullable|string';
            $validationRules['domains'] = 'nullable|string';
            $validationRules['subdomain'] = 'nullable|string';

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                Log::warning('WorkSpace store validation failed', $validator->errors()->toArray());
                return response()->json(['error' => $validator->errors()->first(), 'details' => $validator->errors()], 422);
            }

            // Prepare workspace data dynamically from fillable fields
            $workspaceData = [];
            foreach ($fillableFields as $field) {
                if ($request->has($field)) {
                    $workspaceData[$field] = $request->input($field);
                }
            }
            
            // Add created_by to workspace data
            $workspaceData['created_by'] = $userId;
            $workspaceData['status'] = 'active';
            $workspaceData['is_disable'] = '1';
            $workspaceData['enable_domain'] = 'off';
            
            // Handle logo file upload separately
            if ($request->hasFile('logo')) {
                $fileName = time() . '_workspace_logo_api.' . $request->file('logo')->getClientOriginalExtension();
                $upload = upload_file($request, 'logo', $fileName, 'workspace');
                if ($upload['flag'] == 1) {
                    $workspaceData['logo'] = $upload['url'];
                } else {
                    return response()->json(['error' => 'Failed to upload logo: ' . ($upload['msg'] ?? 'Unknown error')], 422);
                }
            }

            // Create workspace with all fillable fields
            $workspace = WorkSpace::create($workspaceData);
            
            
            
            // ✅ Seed global master data (once, not per workspace)
            app(DefaultMasterDataService::class)->seedAll();

            $user->active_workspace = $workspace->id;
            $user->save();

            User::CompanySetting($user->id, $workspace->id);

            Log::info('WorkSpace created successfully', ['workspace_id' => $workspace->id, 'user_id' => $user->id]);

            return response()->json([
                        'success' => 'Workspace created successfully.',
                        'workspace' => $workspace
                            ], 201);
        } catch (\Exception $e) {
            Log::error('WorkSpace store error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to create workspace.', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id) {
        if (!Auth::user()->isAbleTo('workspace show')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        try {
            $user = Auth::user();
            $workspace = WorkSpace::where('id', $id)->where('status', 'active')->first();

            if (!$workspace) {
                return response()->json(['error' => 'Workspace not found.'], 404);
            }

            return response()->json(['workspace' => $workspace], 200);
        } catch (\Exception $e) {
            Log::error('Workspace show error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve workspace.'], 500);
        }
    }

    public function update(Request $request, $id) {
        if (!Auth::user()->isAbleTo('workspace edit')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        try {
            Log::info('WorkSpace API update called', [
                'workspace_id' => $id,
                'hasFile' => $request->hasFile('logo'),
                'name' => $request->name,
                'auth_user' => auth()->user() ? auth()->user()->id : null
            ]);
            
            $workspace = WorkSpace::where('id', $id)->where('status', 'active')->first();

            if (!$workspace) {
                return response()->json(['error' => 'Workspace not found.'], 404);
            }

            // Get fillable fields from the model
            $fillableFields = (new WorkSpace())->getFillable();
            
            // Build validation rules dynamically
            $validationRules = [];
            foreach ($fillableFields as $field) {
                switch ($field) {
                    case 'name':
                        $validationRules[$field] = 'required|string|max:255';
                        break;
                    case 'created_by':
                        $validationRules[$field] = 'required|integer';
                        break;
                    case 'email':
                        $validationRules[$field] = 'nullable|email|max:255';
                        break;
                    case 'phone':
                    case 'pincode':
                        $validationRules[$field] = 'nullable|string|max:20';
                        break;
                    case 'gst_number':
                    case 'pan_number':
                    case 'ifsc_code':
                    case 'bank_name':
                    case 'account_number':
                        $validationRules[$field] = 'nullable|string|max:50';
                        break;
                    case 'logo':
                        $validationRules[$field] = 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048';
                        break;
                    case 'status':
                        $validationRules[$field] = 'nullable|string';
                        break;
                    default:
                        $validationRules[$field] = 'nullable|string|max:255';
                        break;
                }
            }

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first(), 'details' => $validator->errors()], 422);
            }
            
            // Update workspace data dynamically from fillable fields
            foreach ($fillableFields as $field) {
                if (in_array($field, ['id', 'created_at', 'updated_at'])) {
                    continue;
                }
                
                if ($field === 'logo' && $request->hasFile('logo')) {
                    if ($workspace->logo) {
                        $filePath = public_path($workspace->logo);
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                    
                    $fileName = time() . '_workspace_logo_api.' . $request->file('logo')->getClientOriginalExtension();
                    $upload = upload_file($request, 'logo', $fileName, 'workspace');
                    if ($upload['flag'] == 1) {
                        $workspace->logo = $upload['url'];
                    } else {
                        return response()->json(['error' => 'Failed to upload logo: ' . ($upload['msg'] ?? 'Unknown error')], 422);
                    }
                } elseif ($request->has($field)) {
                    $workspace->$field = $request->input($field);
                }
            }
            
            // Generate base slug 
             $slug = \Str::slug($request->name);
            // Ensure uniqueness 
             $count = WorkSpace::where('slug', 'LIKE', "{$slug}%")->count(); 
             if ($count > 0) { $slug .= '-' . ($count + 1); }
            
            
            $workspace->slug = $slug;
            $workspace->save();

            // ✅ Seed global master data (idempotent - safe to call multiple times)
            app(DefaultMasterDataService::class)->seedAll();

            return response()->json(['success' => 'Workspace updated successfully.', 'workspace' => $workspace], 200);
        } catch (\Exception $e) {
            Log::error('Workspace update error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to update workspace.', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id) {
        
        
        try {

            if (!Auth::user()->isAbleTo('workspace delete')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Permission denied.'
                ], 403);
            }


           // Check if material is linked in indents
                $existsInprojects = \DB::table('projects')
                    ->where('workspace', $id)
                    ->exists();

                if ($existsInprojects) {
                    return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is used in Projects.'], 400);
                } 


//            $user = User::find($request->created_by);
//            $user = Auth::user();
            $workspace = WorkSpace::where('id', $id)->where('status', 'active')->first();

            if (!$workspace) {
                return response()->json(['error' => 'Workspace not found.'], 404);
            }

            $workspace->delete();

            return response()->json(['success' => 'Workspace deleted successfully.'], 200);
        } catch (\Exception $e) {
            Log::error('Workspace destroy error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete workspace.'], 500);
        }
    }
}
