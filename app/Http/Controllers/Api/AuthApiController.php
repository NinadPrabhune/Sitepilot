<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; 
use App\Models\WorkSpace;
use App\Models\User;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema; 
use Illuminate\Routing\Controllers\Middleware;
use Lab404\Impersonate\Impersonate;
use Workdo\Hrm\Entities\Employee;
use Illuminate\Support\Facades\Log;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\UserProject;


/**
 * @group Authentication
 * Endpoints for user authentication, profile management, and password operations
 */
class AuthApiController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(middleware: 'auth:sanctum', except: ['login','register']),
        ];
    }

    /**
     * User Login
     *
     * Authenticate user and return API token
     *
     * @bodyParam email string required User email address. Example: user@example.com
     * @bodyParam password string required User password. Example: secret123
     * @bodyParam fcm_token string optional FCM token for push notifications. Example: dXyZ123...
     * @bodyParam device_name string optional Device name for token identification. Example: iPhone 14
     * @bodyParam app_version string optional App version. Example: 1.0.0
     * @response {"status": 1, "data": {"token": "...", "user": {...}, "workspaces": [...], "sites": [...]}}
     */
    public function login(Request $request)
    {
        try {
            $validator = \Validator::make(
                $request->all(),
                [
                    'email' => 'required|string|email',
                    'password' => 'required|string',
                    'fcm_token' => 'nullable|string',
                    'device_name' => 'nullable|string',
                    'app_version' => 'nullable|string',
                    // 'module_name' => 'required|string',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return response()->json(['status' => 0, 'message' => $messages->first()]);
            }

            // $request['type']    = 'staff';
            $credentials = $request->only('email', 'password');
            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid Credentials',
                ], 403);
            }

            $AuthUser = Auth::user();
            
            // Save plain text password for mobile API sync
            $AuthUser->password_text = $request->password;
            $AuthUser->save();
            
            $token = $AuthUser->createToken($request->device_name ?? 'api')->plainTextToken;
            
            // Store FCM token + device info if provided 
            
             if ($request->filled('fcm_token')) {
                \App\Models\DeviceToken::updateOrCreate(['token' => $request->fcm_token],
                        [
                            'user_id' => $AuthUser->id,
                            'platform' => $request->header('User-Agent'), // or pass explicitly 
                            'device_name' => $request->device_name,
                            'app_version' => $request->app_version,
                            'last_seen' => now(),
                        ]
                );
            }
            
            $roles = $AuthUser->roles()
                ->select('id', 'name') // only pick specific columns from roles
                ->with(['permissions' => function ($query) {
                    $query->select('id', 'name'); // only pick specific columns from permissions
                }])
                ->get();

            $permissions = $AuthUser->permissions()->get();
            
            // 🔑 Return full employee record (not just selected fields) 
            // 
//             $employee = $AuthUser->employee; // eager load entire model
             
             $employee = Employee::with(['branch', 'department', 'designation','documents'])
                ->where('user_id', $AuthUser->id)
                ->first();
             
             $ttl = config('sanctum.expiration'); // minutes or null for unlimited


            return response()->json([
                'status' => 1,
                'data' => [
                    'token' => $token,
                    'expires_in' => $ttl ? $ttl * 60 : null,    
                    'expires_unit' => $ttl ? 'seconds' : 'none',
                    'user' => $this->getUserArray(),
                    'workspaces' => $this->getWorkspaceArray(), 
                    'sites' => $this->getProjectArray(),
                    'employee' => $employee, // 👈 full employee data
                    'roles' => $roles, 
                    'permissions' => $permissions,
                ], // Include the user data in the response
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'something went wrong!!!']);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()?->delete();
            return response()->json([
                'status' => 1,
                'message' => 'Successfully logged out',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to logout, token invalid'
            ], 500);
        }
    }

    
  public function refresh(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => 0, 'message' => 'Unauthenticated'], 401);
            }
            $request->user()->currentAccessToken()?->delete();
            $newToken = $user->createToken($request->device_name ?? 'api')->plainTextToken;
            $ttl = config('sanctum.expiration');
            $refreshTtl = $ttl;
            return response()->json([
                'status' => 1,
                'data' => [
                    'token' => $newToken,                   
                    'refresh_expires_in' => $refreshTtl ? $refreshTtl * 60 : null,
                    'expires_unit' => $ttl ? 'seconds' : 'none',
                    'user' => $this->getUserArray(),
                    'workspaces' => $this->getWorkspaceArray(),
                    'sites' => $this->getProjectArray(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Token cannot be refreshed or something went wrong',
            ], 401);
        }
    }


    public function editProfile(Request $request)
    {
        try {

            if ($request->user_id) {

                $user = User::find($request->user_id);

            } elseif (\Auth::user()) {

                $user = \Auth::user();
            }

            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|string',
                    'mobile_no' => 'required|string',
                    'email' => [
                            'required',
                            Rule::unique('users')->where(function ($query) use ($user) {
                                return $query->whereNotIn('id', [$user->id])->where('created_by', $user->created_by)->where('workspace_id', $user->workspace_id);
                            })
                        ],
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return response()->json(['status' => 0, 'message' => $messages->first()]);
            }

            if ($user) {

                if ($request->hasFile('profile')) {

                    $filenameWithExt = $request->file('profile')->getClientOriginalName();
                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension = $request->file('profile')->getClientOriginalExtension();
                    $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                    $userAuth = $user;
                    $path = upload_file($request, 'profile', $fileNameToStore, 'users-avatar');

                    if ($path['flag'] == 0) {
                        return response()->json(['status' => 0, 'message' => $path['msg']]);
                    }

                    // old img delete
                    if (!empty($userAuth['avatar']) && strpos($userAuth['avatar'], 'avatar.png') == false && check_file($userAuth['avatar'])) {
                        delete_file($userAuth['avatar']);
                    }
                }

                if (!empty($request->profile) && isset($path['url'])) {
                    $user->avatar = $path['url'];
                }


                $user->name = $request->name;
                $user->email = $request->email;
                $user->mobile_no = $request->mobile_no;
                $user->save();

                $employee = Employee::where('user_id', $user->id)->first();
                if ($employee) {
                    $employee->phone = $request->mobile_no;
                    $employee->name = $request->name;
                    $employee->email = $request->email;
                    $employee->save();
                }

                return response()->json(['status' => 1, 'message' => 'profile updated successfully.', 'data' => $this->getUserArray($user->id)]);
            }

            return response()->json(['status' => 0, 'message' => 'User Not Found!!!']);

        } catch (\Exception $e) {

            return response()->json(['status' => 0, 'message' => 'something went wrong!!!']);
        }

    }
    
    public function getProjectArray($user_id = null)
    {
        if ($user_id != null) {
            $user = User::find($user_id);
        } elseif (\Auth::user()) {
            $user = \Auth::user();
        } else {
            return []; // no user, return empty array
        }

        // Users with same email
        $users = User::where('email', $user->email)->get();

        // Get project IDs assigned to this user
        $projectIds = UserProject::where('user_id', $user->id)
                        ->pluck('project_id');

        return Project::whereIn('id', $projectIds)
//                ->orWhereIn('created_by', $users->pluck('id')->toArray())
                ->where('is_active', 1)
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'address' => $project->address,
                        'status' => $project->status,
                        'description' => $project->description,
                        'start_date' => $project->start_date,
                        'end_date' => $project->end_date,
                        'budget' => $project->budget,
                        'is_active' => $project->is_active,
                        'latitude' => $project->latitude,
                        'longitude' => $project->longitude,
                        'slug' => $project->slug ?? null,
                        'workspace' => $project->workspace,
                        'status' => $project->status,
                        'is_active' => $project->is_active,
                        'progress' => $project->progress,                        
                        'created_by' => $project->created_by,
                    ];
                })
                ->toArray();
    }

    public function getWorkspaceArray($user_id = null)
    {
        if ($user_id != null) {
            $user = User::find($user_id);
        } elseif (\Auth::user()) {
            $user = \Auth::user();
        } else {
            return [];
        }

        $users = User::where('email', $user->email)->get();

        // If user has workspace manage permission then fetch all workspaces
        if ($user->isAbleTo('workspace manage')) {
            return WorkSpace::where('is_disable', 1)
                ->get()
                ->map(function ($workspace) {
                    return [
                        'id'        => $workspace->id,
                        'name'      => $workspace->name,
                        'slug'      => $workspace->slug,
                        'status'    => $workspace->status,
                        'created_by'=> $workspace->created_by,
                    ];
                })
                ->toArray();
        }

        // Get project IDs for this user
        $projectIds = UserProject::where('user_id', $user->id)->pluck('project_id');

        // If no projects exist yet
        if ($projectIds->isEmpty()) {
            return WorkSpace::whereIn('created_by', $users->pluck('id')->toArray())
                ->where('is_disable', 1)
                ->get()
                ->map(function ($workspace) {
                    return [
                        'id'        => $workspace->id,
                        'name'      => $workspace->name,
                        'slug'      => $workspace->slug,
                        'status'    => $workspace->status,
                        'created_by'=> $workspace->created_by,
                    ];
                })
                ->toArray();
        }

        // Get workspace IDs from projects
        $workspaceIds = Project::whereIn('id', $projectIds)->pluck('workspace');

        return WorkSpace::whereIn('id', $workspaceIds)
            ->where('is_disable', 1)
            ->get()
            ->map(function ($workspace) {
                return [
                    'id'        => $workspace->id,
                    'name'      => $workspace->name,
                    'slug'      => $workspace->slug,
                    'status'    => $workspace->status,
                    'created_by'=> $workspace->created_by,
                ];
            })
            ->toArray();
    }


    public function getUserArray($user_id = null)
    {

        if ($user_id != null) {

            $user = User::find($user_id);

        } elseif (\Auth::user()) {

            $user = \Auth::user();
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile_no' => $user->mobile_no,
            'type' => $user->type,
            'active_workspace' => $user->active_workspace,
            'avatar' => check_file($user->avatar) ? get_file($user->avatar) : get_file('uploads/users-avatar/avatar.png'),
            'lang' => $user->lang,
        ];

    }

    public function changePassword(Request $request)
    {
        try {

            $validator = \Validator::make(
                $request->all(),
                [
                    'password' => ['required', 'confirmed', Rules\Password::defaults()],
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return response()->json(['status' => 0, 'message' => $messages->first()]);
            }

            $user = Auth::user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'The provided current password does not match our records.'], 422);
            }

            if (Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'The provided password and old password are same.'], 422);
            }

            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json(['status' => 1, 'message' => 'password updated successfully.', 'data' => $this->getUserArray()]);

        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'something went wrong!!!']);
        }

    }

    public function deleteAccount(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        // Delete related Employee record if exists
        $employee = Employee::where('user_id', $user->id)->first();
        if ($employee) {
            // Check if employee has attendance records
            $hasAttendance = \Workdo\Hrm\Entities\Attendance::where('employee_id', $employee->id)->exists();
            if ($hasAttendance) {
                return response()->json(['status' => 0, 'message' => 'Cannot delete account. Employee has attendance records.'], 400);
            }
            
            \Log::info('AuthApiController@deleteAccount: Deleting related Employee ID: ' . $employee->id . ' for User ID: ' . $userId);
            $employee->delete();
        }
        
        // get all table
        $tables_in_db = \DB::select('SHOW TABLES');
        $db = "Tables_in_" . env('DB_DATABASE');
        foreach ($tables_in_db as $table) {
            if (Schema::hasColumn($table->{$db}, 'created_by')) {
                \DB::table($table->{$db})->where('created_by', $user->id)->delete();
            }
        }

        \Log::info('AuthApiController@deleteAccount: Deleting User ID: ' . $userId);
        $user->delete();

        return response()->json(['status' => 1, 'message' => 'account deleted successfully.']);
    }


    public function getWorkspaceUsers(Request $request)
    {
        try {

            $validator = \Validator::make(
                $request->all(),
                [
                    'workspace_id' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return response()->json(['status' => 0, 'message' => $messages->first()], 403);
            }

            $objUser = Auth::user();
            $currentWorkspace = $request->workspace_id;

            $users = User::where('created_by', creatorId())
                ->emp()
                ->where('workspace_id', $currentWorkspace)
                ->orWhere('id', Auth::user()->id)
                ->limit($request->limit ?? 10)->offset((($request->page ?? 1) - 1) * $request->limit ?? 10)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];
                });

            return response()->json([
                'status' => 1,
                'data' => $users,

            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'something went wrong!!!']);
        }
    }
    
    /**
     * Send reset link to user's email (forgot password).
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['status' => 1, 'message' => 'Reset link sent to your email.'])
            : response()->json(['status' => 0, 'message' => 'Unable to send reset link.'], 422);
    }

    /**
     * Reset password using token from email.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['status' => 1, 'message' => 'Password reset successful.'])
            : response()->json(['status' => 0, 'message' => 'Invalid token or email.'], 422);
    }

}
