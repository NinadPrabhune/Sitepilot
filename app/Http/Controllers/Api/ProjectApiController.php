<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

/**
 * @group Projects
 * Endpoints for project management including CRUD operations and dashboard data
 */
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Workdo\Taskly\Entities\Project;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Workdo\Taskly\Entities\UserProject;
use App\Services\ProjectDashboardService;
use App\Services\ProjectUserService;
use Illuminate\Support\Facades\Auth as AuthFacade;

class ProjectApiController extends Controller
{
    
    /**
     * Get workspace users for project creation
     * GET /api/projects/create-data
     */
    public function createData(Request $request)
    {
        if (!Auth::user()->isAbleTo('project create')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        try {
            $validator = Validator::make($request->all(), [
                'workspace_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            $workspaceId = $request->input('workspace_id');
            $creatorId = creatorId();

            // Get workspace users (created by creator or current user)
            $workspace_users = User::where('created_by', $creatorId)
                ->orWhere('id', Auth::user()->id)
                ->select('name', 'email')
                ->get();

            return response()->json([
                'workspace_users' => $workspace_users
            ], 200);

        } catch (\Exception $e) {
            Log::error('Project createData error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve workspace users.'], 500);
        }
    }

    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('project manage')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

    try {

        $validator = Validator::make($request->all(), [
            'workspace_id' => 'required|integer',
            'site_id'      => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $user = Auth::user();

        // Get filters from request
        $workspaceId = $request->input('workspace_id');
        $siteId      = $request->input('site_id');

        // Base query
        $query = Project::where('is_active', 1);

        // If user is not company, restrict projects
        if ($user->type != 'company') {

            // Get assigned project IDs
            $projectIds = UserProject::where('user_id', $user->id)->pluck('project_id');

            if ($projectIds->isEmpty()) {
                // If no assigned projects
                $query->where('created_by', $user->id);
            } else {
                // Fetch only assigned projects
                $query->whereIn('id', $projectIds);
            }
        }

        // Apply filters
        if (!empty($workspaceId) && $workspaceId != 0) {
            $query->where('workspace', $workspaceId);
        }

        if (!empty($siteId) && $siteId != 0) {
            $query->where('id', $siteId);
        }

        $projects = $query->get();

        return response()->json(['projects' => $projects], 200);

    } catch (\Exception $e) {
        Log::error('Project index error: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to retrieve projects.'], 500);
    }
}
    
    
//    public function index(Request $request)
//    {
//        try {
//            
//            $validator = Validator::make($request->all(), [               
//                'workspace_id' => 'required|integer',                
//                'site_id' => 'required|integer'                
//            ]);
//
//            if ($validator->fails()) {
//                return response()->json(['error' => $validator->errors()->first()], 422);
//            }
//                    
//            
////            $projects = Project::all();
//            
//            // Get filters from request
//            $workspaceId = $request->input('workspace_id');
//            $siteId      = $request->input('site_id');
//
//            // Build query
//            $query = Project::where('is_active', 1);
//
//             // Apply filters if provided
//            if (!empty($workspaceId) && $workspaceId != 0) {
//                $query->where('workspace', $workspaceId);
//            }
//
//            if (!empty($siteId) && $siteId != 0) {
//                $query->where('id', $siteId);
//            }
//
//            $projects = $query->get();
//            
//            
//            return response()->json(['projects' => $projects], 200);
//        } catch (\Exception $e) {
//            Log::error('Project index error: ' . $e->getMessage());
//            return response()->json(['error' => 'Failed to retrieve projects.'], 500);
//        }
//    }

    /**
     * Store a newly created project.
     *
     * @bodyParam name string required Project name. Example: Construction Site A
     * @bodyParam status string optional Status. Example: active
     * @bodyParam description string required Project description. Example: Building construction project
     * @bodyParam start_date date optional Start date. Example: 2024-01-01
     * @bodyParam end_date date optional End date (must be after or equal to start_date). Example: 2024-12-31
     * @bodyParam budget number optional Budget amount. Example: 5000000.00
     * @bodyParam workspace integer required Workspace ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam is_active boolean optional Active status. Example: true
     * @bodyParam latitude string optional Latitude. Example: 19.0760
     * @bodyParam longitude string optional Longitude. Example: 72.8777
     * @bodyParam address string optional Address. Example: Mumbai, India
     * @response {"success": "Project created successfully.", "project": {...}}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('project create')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|string',
                'description' => 'required|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'budget' => 'nullable|numeric',
                'workspace' => 'required|integer',
                'created_by' => 'required|integer',
                'is_active' => 'nullable|boolean',                
                'latitude'=> 'nullable|string',
                'longitude'=>'nullable|string',
                'address'=>'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }
            $post = $request->all();
            $post['start_date']  = $post['end_date']  = date('Y-m-d');            
            $post['copylinksetting']   = '{"member":"on","client":"on","milestone":"off","progress":"off","basic_details":"on","activity":"off","attachment":"on","bug_report":"on","task":"off","invoice":"off","timesheet":"off" ,"password_protected":"off"}';
            
            // Handle users_list for project team members
            $userList = [];
            if (isset($post['users_list']) && is_array($post['users_list'])) {
                $userList = $post['users_list'];
            }
            
            // Add creator to user list
            $user = User::find(creatorId());
            if ($user) {
                $userList[] = $user->email;
            }
            
            // Add current authenticated user to user list
            $objUser = Auth::user();
            if ($objUser) {
                $userList[] = $objUser->email;
            }
            
            // Remove duplicates
            $userList = array_unique($userList);

            $project = Project::create($post);
            
            $arrData = [
                'user_id' => $post['created_by'],
                'project_id' => $project->id,
            ];

            // Handle user invitations from users_list
            foreach ($userList as $email) {
                $permission = 'Member';
                
                $registerUsers = User::where('active_workspace', $post['workspace'])->where('email', $email)->first();
                
                if ($registerUsers) {
                    if ($registerUsers->id == $objUser->id) {
                        $permission = 'Owner';
                    }
                } else {
                    $registerUsers = User::where('email', $email)->first();
                }
                
                if ($registerUsers) {
                    // Check if the record already exists
                    $exists = DB::table('user_projects')
                        ->where('user_id', $registerUsers->id)
                        ->where('project_id', $project->id)
                        ->exists();

                    // Insert only if it doesn't exist
                    if (!$exists) {
                        DB::table('user_projects')->insert([
                            'user_id' => $registerUsers->id,
                            'project_id' => $project->id,
                        ]);
                    }
                }
            }

            // Auto-assign all Admin and Company users to this project
            try {
                $projectUserService = new ProjectUserService();
                $assignmentResult = $projectUserService->bulkAssignAdminsToProject($project->id, $post['workspace']);
                
                if ($assignmentResult['assigned'] > 0) {
                    Log::info("Auto-assigned {$assignmentResult['assigned']} Admin/Company users to project {$project->id}");
                }
            } catch (\Exception $e) {
                // Log but don't fail the project creation
                Log::warning("Failed to auto-assign Admin/Company users to project {$project->id}: " . $e->getMessage());
            }

            return response()->json([
                'success' => 'Project created successfully.', 
                'project' => $project,
                'admin_users_assigned' => $assignmentResult['assigned'] ?? 0
            ], 201);
        } catch (\Exception $e) {
            Log::error('Project store error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create project.'], 500);
        }
    }

    public function show($id)
    {
        if (!Auth::user()->isAbleTo('project show')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        try {
            $project = Project::find($id);

            if (!$project) {
                return response()->json(['error' => 'Project not found.'], 404);
            }

            return response()->json(['project' => $project], 200);
        } catch (\Exception $e) {
            Log::error('Project show error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve project.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('project edit')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        try {
            $project = Project::find($id);

            if (!$project) {
                return response()->json(['error' => 'Project not found.'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|string',
                'description' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'budget' => 'nullable|numeric',
                'workspace' => 'required|integer',
                'created_by' => 'required|integer',
                'is_active' => 'nullable|boolean',
                'latitude'=> 'nullable|string',
                'longitude'=>'nullable|string',
                'address'=>'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            $post = $request->all();
            $post['start_date']  = $post['end_date']  = date('Y-m-d');            
            $post['copylinksetting']   = '{"member":"on","client":"on","milestone":"off","progress":"off","basic_details":"on","activity":"off","attachment":"on","bug_report":"on","task":"off","invoice":"off","timesheet":"off" ,"password_protected":"off"}';
            
            // Handle users_list for project team members
            $userList = [];
            if (isset($post['users_list']) && is_array($post['users_list'])) {
                $userList = $post['users_list'];
            }
            
            // Add creator to user list
            $user = User::find(creatorId());
            if ($user) {
                $userList[] = $user->email;
            }
            
            // Add current authenticated user to user list
            $objUser = Auth::user();
            if ($objUser) {
                $userList[] = $objUser->email;
            }
            
            // Remove duplicates
            $userList = array_unique($userList);
            
            $project->update($post);
            
            
            $arrData = [
                'user_id' => $post['created_by'],
                'project_id' => $project->id,
            ];

            // Handle user invitations from users_list
            foreach ($userList as $email) {
                $permission = 'Member';
                
                $registerUsers = User::where('active_workspace', $post['workspace'])->where('email', $email)->first();
                
                if ($registerUsers) {
                    if ($registerUsers->id == $objUser->id) {
                        $permission = 'Owner';
                    }
                } else {
                    $registerUsers = User::where('email', $email)->first();
                }
                
                if ($registerUsers) {
                    // Check if the record already exists
                    $exists = DB::table('user_projects')
                        ->where('user_id', $registerUsers->id)
                        ->where('project_id', $project->id)
                        ->exists();

                    // Insert only if it doesn't exist
                    if (!$exists) {
                        DB::table('user_projects')->insert([
                            'user_id' => $registerUsers->id,
                            'project_id' => $project->id,
                        ]);
                    }
                }
            }

            return response()->json(['success' => 'Project updated successfully.', 'project' => $project], 200);
        } catch (\Exception $e) {
            Log::error('Project update error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update project.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Check permission
            if (!Auth::user()->isAbleTo('project delete')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Permission denied.'
                ], 403);
            }

            $objUser = Auth::user();
            $project = Project::find($id);

            if (!$project) {
                return response()->json(['error' => 'Project not found.'], 404);
            }

            // Check if user has permission to delete this project
            if ($project->created_by != $objUser->id && $objUser->type != 'company') {
                return response()->json([
                    'status'  => 'error',
                    'message' => "You can't Delete Project!"
                ], 403);
            }
            
            // Check if material is linked in indents
            $existsInIndents = \DB::table('indents')
                ->where('site_id', $id)
                ->exists();

            // Check if material is linked in indents
            $existsInUserProjects = \DB::table('user_projects')
                ->where('project_id', $id)
                ->exists();
            
            // Check if material is linked in activities
            $existsInActivities = \DB::table('activities')
                ->where('site_id', $id)
                ->exists();

            // Check if material is linked in man_power_masters
            $existsInManPower = \DB::table('man_power_masters')
                ->where('site_id', $id)
                ->exists();

            // Check if material is linked in purchase_invoice_items
            $existsInPurchase = \DB::table('purchase_invoice_items')
                ->where('site_id', $id)
                ->exists();

            // Check if material is linked in assets_tools_and_equipment
            $existsInAssets = \DB::table('assets_tools_and_equipment')
                ->where('site_id', $id)
                ->exists();

            // Check if material is used in daily_consumption_details
            $existsInDailyConsumption = \DB::table('daily_consumption_details')
                ->where('site_id', $id)
                ->exists();
            
            // Check if material is used in material_transfers
            $existsInMaterialTransfers = \DB::table('material_transfers')
                ->where('site_id', $id)
                ->exists();

            if ($existsInUserProjects) {
                return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is assigned to Users'], 400);
            } 
            
            if ($existsInIndents) {
                return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is used in Indents.'], 400);
            } 
            
            if ($existsInManPower) {
                return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is used in Manpower.'], 400);
            } 
            
            if ($existsInActivities) {
                return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is used in Activity.'], 400);
            } 
            
            if ($existsInPurchase) {
                return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is used in Purchase Invoices.'], 400);
            } 
            
            if ($existsInAssets) {
                return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is used in Tools & Equipment records.'], 400);
            }
            
            if ($existsInDailyConsumption) {
                return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is used in Consumption Log records.'], 400);
            }
            
            if ($existsInMaterialTransfers) {
                return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is used in Material_transfers records.'], 400);
            }

            $project->delete();
            
            DB::table('user_projects')            
            ->where('project_id', $id)
            ->delete();
            

            return response()->json(['success' => 'Project deleted successfully.'], 200);
        } catch (\Exception $e) {
            Log::error('Project destroy error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete project.'], 500);
        }
    }

    /**
     * Get project dashboard data for mobile API
     * GET /api/projects/{project_id}/dashboard
     */
    public function dashboard(Request $request, $projectId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'workspace_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'error' => $validator->errors()->first()], 422);
            }

            $project = Project::find($projectId);

            if (!$project) {
                return response()->json(['status' => false, 'error' => 'Project not found.'], 404);
            }

            $workspaceId = $request->input('workspace_id');

            // Set the workspace context
            session(['workspace_id' => $workspaceId]);

            // Get dashboard data from service
            $dashboardService = new ProjectDashboardService($project);
            $dashboardData = $dashboardService->getDashboardData(true);

            // Get alerts
            $alerts = $dashboardService->getAlerts();

            return response()->json([
                'status' => true,
                'data' => $dashboardData,
                'alerts' => $alerts,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Project dashboard error: ' . $e->getMessage());
            return response()->json(['status' => false, 'error' => 'Failed to retrieve dashboard data.'], 500);
        }
    }
}
