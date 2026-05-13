<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Users
 * Endpoints for user management including CRUD operations, profile, and password management
 */
use App\Events\CreateUser;
use App\Events\DefaultData;
use App\Events\DestroyUser;
use App\Events\EditProfileUser;
use App\Events\UpdateUser;
use App\Models\EmailTemplate;
use App\Models\LoginDetail;
use App\Models\Plan;
use App\Models\ReferralTransaction;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSpace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Lab404\Impersonate\Impersonate;
use Illuminate\Support\Facades\Storage;

class UserApiController extends Controller
{
    /**
     * =====================================================
     * USER LISTING APIs
     * =====================================================
     */

    /**
     * GET /api/users
     * List all users with optional filters
     * 
     * Query Params:
     * - name: Filter by name
     * - email: Filter by email
     * - role: Filter by role ID
     * - workspace_id: Filter by workspace
     * - page: Page number (default 1)
     * - per_page: Items per page (default 15)
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('user manage')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $query = User::query();

            if (Auth::user()->type == 'super admin') {
                $query->where('type', 'company');
            } else {
                $query->where('created_by', creatorId());

                if (Auth::user()->isAbleTo('workspace manage')) {
                    $query->where('workspace_id', getActiveWorkSpace());
                }

                if ($request->name) {
                    $query->where('name', 'like', '%' . $request->name . '%');
                }
                if ($request->email) {
                    $query->where('email', 'like', '%' . $request->email . '%');
                }
                if ($request->role) {
                    $role = Role::find($request->role);
                    if ($role) {
                        $query->where('type', $role->name);
                    }
                }
            }

            $perPage = $request->per_page ?? 15;
            $users = $query->paginate($perPage);

            foreach ($users as $user) {
                $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token']);
            }

            return $this->jsonResponse(true, 'Users retrieved successfully', [
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'total_pages' => $users->lastPage(),
                    'total_items' => $users->total(),
                    'per_page' => $users->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * GET /api/users/create-data
     * Get data needed for creating a user (roles list)
     */
    public function createData(Request $request)
    {
        if (!Auth::user()->isAbleTo('user create')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $roles = [];
            if (Auth::user()->type != 'super admin') {
                $roles = Role::where('created_by', Auth::user()->id)
                    ->where('status', 0)
                    ->pluck('name', 'id');
            } else {
                $roles = Role::where('name', 'company')->pluck('name', 'id');
            }

            return $this->jsonResponse(true, 'Roles data retrieved successfully', [
                'roles' => $roles
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * POST /api/users
     * Create a new user
     * 
     * Form Data:
     * - name (required): User name
     * - email (required): User email
     * - mobile_no (optional): Mobile number
     * - password (required if login enabled): Password
     * - password_switch (optional): 'on' to enable login
     * - roles (required): Role ID
     * - avatar (optional): Profile image file
     * - workspace_id (optional): Workspace ID
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('user create')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            // Plan check
            if (Auth::user()->type != 'super admin') {
                $canUse = PlanCheck('User', Auth::user()->id);
                if ($canUse == false) {
                    return $this->jsonResponse(false, 'You have maxed out the total number of Users allowed on your current plan', [], 403);
                }
            }

            // Validation rules
            $rules = [
                'name' => 'required|max:120',
                'email' => 'required|email|max:100|unique:users,email',
                'roles' => 'required|exists:roles,id',
            ];

            // Mobile validation
            if ($request->mobile_no) {
                $rules['mobile_no'] = 'nullable|regex:/^\+\d{1,3}\d{9,13}$/';
            }

            // Password validation if login enabled
            if ($request->password_switch == 'on') {
                $rules['password'] = 'required|min:6';
            }

            // Avatar validation
            if ($request->hasFile('avatar')) {
                $rules['avatar'] = 'image|mimes:jpeg,png,jpg,gif|max:2048';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return $this->jsonResponse(false, $validator->errors()->first(), $validator->errors()->toArray(), 422);
            }

            // Get role
            $roles = Role::find($request->roles);
            $company_settings = getCompanyAllSetting();

            // Handle password
            $userpassword = $request->password;
            $is_enable_login = ($request->password_switch == 'on') ? 1 : 0;

            $userData = [
                'name' => $this->toCamelCase($request->name),
                'email' => $request->email,
                'mobile_no' => $request->mobile_no,
                'password' => !empty($userpassword) ? Hash::make($userpassword) : null,
                'lang' => !empty($company_settings['defult_language']) ? $company_settings['defult_language'] : 'en',
                'type' => $roles->name,
                'created_by' => creatorId(),
                'workspace_id' => $request->workspace_id ?? getActiveWorkSpace(),
                'active_workspace' => $request->workspace_id ?? getActiveWorkSpace(),
                'is_enable_login' => $is_enable_login,
            ];

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatarFile = $request->file('avatar');
                $tempRequest = new Request();
                $tempRequest->merge(['avatar' => $avatarFile]);
                
                $filenameWithExt = $avatarFile->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $avatarFile->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($tempRequest, 'avatar', $fileNameToStore, 'avatars');

                if (isset($path['flag']) && $path['flag'] == 0) {
                    return $this->jsonResponse(false, $path['msg'] ?? 'File upload failed', [], 500);
                }

                if (!empty($path['url'])) {
                    $userData['avatar'] = $path['url'];
                }
            }

            $user = User::create($userData);
            $user->addRole($roles);

            // Fire event
            event(new CreateUser($user, $request));

            // Email verification
            if (admin_setting('email_verification') == 'on') {
                try {
                    $user->sendEmailVerificationNotification();
                } catch (\Throwable $th) {
                    // Ignore email errors
                }
            } else {
                $user->email_verified_at = date('Y-m-d h:i:s');
                $user->save();
            }

            // Send email notification
            if (!empty($company_settings['Create User']) && $company_settings['Create User'] == true) {
                $uArr = [
                    'email' => $request->email,
                    'password' => $request->password,
                    'company_name' => $request->name,
                ];
                EmailTemplate::sendEmailTemplate('New User', [$user->email], $uArr);
            }

            $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token']);

            return $this->jsonResponse(true, 'User created successfully', ['user' => $user], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * GET /api/users/{id}
     * Show a specific user
     */
    public function show(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('user manage')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $user = User::find($id);

            if (!$user) {
                return $this->jsonResponse(false, 'User not found', [], 404);
            }

            $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token']);

            // Get role details
            $role = Role::where('name', $user->type)->first();

            return $this->jsonResponse(true, 'User retrieved successfully', [
                'user' => $user,
                'role' => $role
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * GET /api/users/{id}/edit
     * Get user data for editing
     */
    public function edit(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('user edit')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $user = User::find($id);

            if (!$user) {
                return $this->jsonResponse(false, 'User not found', [], 404);
            }

            $roles = Role::where('created_by', Auth::user()->id)
                ->where('status', 0)
                ->pluck('name', 'id');

            $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token']);

            return $this->jsonResponse(true, 'User data retrieved successfully', [
                'user' => $user,
                'roles' => $roles
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * PUT /api/users/{id}
     * Update a user
     * 
     * Form Data:
     * - name (required): User name
     * - email (required): User email
     * - mobile_no (optional): Mobile number
     * - roles (optional): Role ID
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('user edit')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            // Validation rules
            $rules = [
                'name' => 'required|max:120',
                'email' => [
                    'required',
                    Rule::unique('users')->where(function ($query) use ($id) {
                        return $query->whereNotIn('id', [$id])
                            ->where('created_by', creatorId())
                            ->where('workspace_id', getActiveWorkSpace());
                    })
                ],
            ];

            if ($request->mobile_no) {
                $rules['mobile_no'] = 'nullable|regex:/^\+\d{1,3}\d{9,13}$/';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return $this->jsonResponse(false, $validator->errors()->first(), $validator->errors()->toArray(), 422);
            }

            $user = User::find($id);

            if (!$user) {
                return $this->jsonResponse(false, 'User not found', [], 404);
            }

            // Update user fields
            $user->name = $this->toCamelCase($request->name);
            $user->email = $request->email;
            $user->mobile_no = $request->mobile_no;

            // Update role if provided
            if ($request->roles) {
                $role = Role::find($request->roles);
                if ($role) {
                    $user->type = $role->name;
                }
            }

            $user->save();

            // Fire event
            event(new UpdateUser($user, $request));

            $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token']);

            return $this->jsonResponse(true, 'User updated successfully', ['user' => $user]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * DELETE /api/users/{id}
     * Delete a user
     */
    public function destroy(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('user delete')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $user = User::findOrFail($id);

            // Fire event
            event(new DestroyUser($user));

            // Delete related data from all tables
            $tables_in_db = \DB::select('SHOW TABLES');
            $db = "Tables_in_" . env('DB_DATABASE');

            foreach ($tables_in_db as $table) {
                if (Schema::hasColumn($table->{$db}, 'created_by')) {
                    \DB::table($table->{$db})->where('created_by', $user->id)->delete();
                }
            }

            // Delete related employee if exists
            if (class_exists('Workdo\Hrm\Entities\Employee')) {
                $employee = \Workdo\Hrm\Entities\Employee::where('user_id', $user->id)->first();
                if ($employee) {
                    $hasAttendance = \Workdo\Hrm\Entities\Attendance::where('employee_id', $employee->id)->exists();
                    if ($hasAttendance) {
                        return $this->jsonResponse(false, 'Cannot delete user. Employee has attendance records.', [], 400);
                    }
                    $employee->delete();
                }
            }

            // Delete referral transactions
            ReferralTransaction::where('company_id', $id)->delete();

            // Delete user
            $user->delete();

            return $this->jsonResponse(true, 'User deleted successfully', []);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * =====================================================
     * USER PROFILE APIs
     * =====================================================
     */

    /**
     * GET /api/users/profile
     * Get current authenticated user's profile
     */
    public function profile(Request $request)
    {
        if (!Auth::user()->isAbleTo('user profile manage')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $userDetail = Auth::user();
            $userDetail->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token']);

            return $this->jsonResponse(true, 'Profile retrieved successfully', [
                'user' => $userDetail
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * PUT /api/users/profile
     * Update current authenticated user's profile
     * 
     * Form Data:
     * - name (required): User name
     * - email (required): User email
     * - mobile_no (optional): Mobile number
     * - avatar (optional): Profile image file
     */
    public function editprofile(Request $request)
    {
        if (!Auth::user()->isAbleTo('user profile manage')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $userDetail = Auth::user();
            $user = User::findOrFail($userDetail->id);

            // Validation rules
            $rules = [
                'name' => 'required|max:120',
                'email' => [
                    'required',
                    Rule::unique('users')->where(function ($query) use ($user) {
                        return $query->whereNotIn('id', [$user->id])
                            ->where('created_by', $user->created_by)
                            ->where('workspace_id', $user->workspace_id);
                    })
                ],
            ];

            if ($request->mobile_no) {
                $rules['mobile_no'] = 'nullable|regex:/^\+\d{1,3}\d{9,13}$/';
            }

            if ($request->hasFile('avatar')) {
                $rules['avatar'] = 'image|mimes:jpeg,png,jpg,gif|max:2048';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return $this->jsonResponse(false, $validator->errors()->first(), $validator->errors()->toArray(), 422);
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatarFile = $request->file('avatar');
                $tempRequest = new Request();
                $tempRequest->merge(['avatar' => $avatarFile]);
                
                $filenameWithExt = $avatarFile->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $avatarFile->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($tempRequest, 'avatar', $fileNameToStore, 'avatars');

                if (isset($path['flag']) && $path['flag'] == 0) {
                    return $this->jsonResponse(false, $path['msg'] ?? 'File upload failed', [], 500);
                }

                // Delete old avatar if exists
                if (!empty($userDetail->avatar) && strpos($userDetail->avatar, 'avatar.png') == false && check_file($userDetail->avatar)) {
                    delete_file($userDetail->avatar);
                }

                if (!empty($path['url'])) {
                    $user->avatar = $path['url'];
                }
            }

            $user->name = $this->toCamelCase($request->name);
            $user->email = $request->email;
            $user->mobile_no = $request->mobile_no;
            $user->save();

            // Update related student/teacher if applicable
            if ($user->hasRole('student')) {
                $student = $user->musicStudent;
                if ($student) {
                    $student->avatar = $user->avatar;
                    $student->save();
                }
            }

            if ($user->hasRole('staff')) {
                $teacher = $user->musicTeacher;
                if ($teacher) {
                    $teacher->avatar = $user->avatar;
                    $teacher->save();
                }
            }

            // Fire event
            event(new EditProfileUser($request, $user));

            $user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token']);

            return $this->jsonResponse(true, 'Profile updated successfully', ['user' => $user]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * PUT /api/users/profile/avatar
     * Update only user avatar
     * 
     * Form Data:
     * - avatar (required): Profile image file
     */
    public function updateAvatar(Request $request)
    {
        if (!Auth::user()->isAbleTo('user profile manage')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $user = User::findOrFail(Auth::user()->id);
            $userDetail = Auth::user();

            $rules = [
                'avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return $this->jsonResponse(false, $validator->errors()->first(), $validator->errors()->toArray(), 422);
            }

            $avatarFile = $request->file('avatar');

            if ($avatarFile) {
                // Create a temporary request with the file for upload_file function
                $tempRequest = new Request();
                $tempRequest->merge(['avatar' => $avatarFile]);
                
                $filenameWithExt = $avatarFile->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $avatarFile->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($tempRequest, 'avatar', $fileNameToStore, 'avatars');

                if (isset($path['flag']) && $path['flag'] == 0) {
                    return $this->jsonResponse(false, $path['msg'] ?? 'File upload failed', [], 500);
                }

                if (!empty($userDetail->avatar) && strpos($userDetail->avatar, 'avatar.png') === false && check_file($userDetail->avatar)) {
                    delete_file($userDetail->avatar);
                }

                if (!empty($path['url'])) {
                    $user->avatar = $path['url'];
                    $user->save();

                    return $this->jsonResponse(true, 'Avatar updated successfully', [
                        'avatar' => $user->avatar
                    ]);
                }
            }

            return $this->jsonResponse(false, 'No avatar file provided', [], 400);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * PUT /api/users/password
     * Update current user's password
     * 
     * Form Data:
     * - current_password (required): Current password
     * - new_password (required): New password (min 6 chars)
     * - confirm_password (required): Confirm new password
     */
    public function updatePassword(Request $request)
    {
        if (!Auth::user()->isAbleTo('user profile manage')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|min:6',
                'confirm_password' => 'required|same:new_password',
            ]);

            $objUser = Auth::user();
            $current_password = $objUser->password;

            if (Hash::check($request->current_password, $current_password)) {
                $obj_user = User::find(Auth::user()->id);
                $obj_user->password = Hash::make($request->new_password);
                $obj_user->save();

                return $this->jsonResponse(true, 'Password updated successfully', []);
            } else {
                return $this->jsonResponse(false, 'Please enter correct current password', [], 400);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * =====================================================
     * USER PASSWORD RESET APIs (Admin)
     * =====================================================
     */

    /**
     * GET /api/users/{id}/password
     * Get password reset form data
     */
    public function UserPassword(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('user reset password')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $eId = \Crypt::decrypt($id);
            $user = User::find($eId);

            if (!$user) {
                return $this->jsonResponse(false, 'User not found', [], 404);
            }

            return $this->jsonResponse(true, 'User data retrieved successfully', [
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * PUT /api/users/{id}/password
     * Reset user's password (Admin)
     * 
     * Form Data:
     * - password (required): New password
     * - password_confirmation (required): Confirm password
     * - login_enable (optional): Enable login after reset
     */
    public function UserPasswordReset(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('user reset password')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $validator = Validator::make($request->all(), [
                'password' => 'required|confirmed|same:password_confirmation|min:6',
            ]);

            if ($validator->fails()) {
                return $this->jsonResponse(false, $validator->errors()->first(), $validator->errors()->toArray(), 422);
            }

            $user = User::where('id', $id)->first();

            if (!$user) {
                return $this->jsonResponse(false, 'User not found', [], 404);
            }

            if (isset($request->login_enable)) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'is_enable_login' => 1,
                ])->save();
            } else {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                ])->save();
            }

            return $this->jsonResponse(true, 'Password reset successfully', []);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * POST /api/users/{id}/login-manage
     * Enable/disable user login
     */
    public function LoginManage(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('user reset password')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $eId = \Crypt::decrypt($id);
            $user = User::find($eId);

            if (!$user) {
                return $this->jsonResponse(false, 'User not found', [], 404);
            }

            if ($user->is_enable_login == 1) {
                $user->is_enable_login = 0;
                $user->save();
                return $this->jsonResponse(true, 'User login disabled successfully', []);
            } else {
                $user->is_enable_login = 1;
                $user->save();
                return $this->jsonResponse(true, 'User login enabled successfully', []);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * =====================================================
     * USER IMPORT APIs
     * =====================================================
     */

    /**
     * GET /api/users/import
     * Get import form data
     */
    public function fileImportExport(Request $request)
    {
        if (!Auth::user()->isAbleTo('user import')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            return $this->jsonResponse(true, 'Import form data retrieved successfully', []);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * POST /api/users/import-preview
     * Preview CSV import file
     * 
     * Form Data:
     * - file (required): CSV file
     */
    public function fileImport(Request $request)
    {
        if (!Auth::user()->isAbleTo('user import')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            if (!$request->hasFile('file')) {
                return $this->jsonResponse(false, 'Please select a file', [], 400);
            }

            $file_array = explode(".", $request->file->getClientOriginalName());
            $extension = end($file_array);

            if ($extension != 'csv') {
                return $this->jsonResponse(false, 'Only .csv file allowed', [], 400);
            }

            $file_data = fopen($request->file->getRealPath(), 'r');
            $file_header = fgetcsv($file_data);

            $columns = [];
            for ($count = 0; $count < count($file_header); $count++) {
                $columns[] = $file_header[$count];
            }

            $roles = Role::where('created_by', Auth::user()->id)
                ->where('status', 0)
                ->pluck('name', 'id');

            return $this->jsonResponse(true, 'File preview data', [
                'columns' => $columns,
                'roles' => $roles
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * POST /api/users/import
     * Import users from CSV
     * 
     * Form Data:
     * - name (required, array): Name column index
     * - email (required, array): Email column index
     * - role (required, array): Role column index
     * - file_data (required): CSV data
     */
    public function UserImportdata(Request $request)
    {
        if (!Auth::user()->isAbleTo('user import')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $file_data = json_decode($request->file_data, true);

            if (empty($file_data)) {
                return $this->jsonResponse(false, 'No data provided', [], 400);
            }

            $users_count = 0;
            $status = admin_setting('email_verification');
            $imported = [];
            $failed = [];

            foreach ($file_data as $key => $row) {
                $email = $row[$request->email] ?? null;

                // Validation
                $validator = Validator::make(
                    ['email' => $email],
                    ['email' => 'required|email']
                );

                if ($validator->fails()) {
                    $failed[] = ['row' => $key + 1, 'email' => $email, 'error' => 'Invalid email'];
                    continue;
                }

                // Check for duplicates
                $check_user = User::where('created_by', creatorId())
                    ->where('workspace_id', getActiveWorkSpace())
                    ->Where('email', $email)
                    ->first();

                if ($check_user) {
                    $failed[] = ['row' => $key + 1, 'email' => $email, 'error' => 'Email already exists'];
                    continue;
                }

                // Plan check
                if (Auth::user()->type != 'super admin') {
                    $canUse = PlanCheck('User', Auth::user()->id);
                    if ($canUse == false) {
                        return $this->jsonResponse(false, 'You have maxed out the total number of Users allowed on your current plan', [
                            'imported' => $imported,
                            'failed' => $failed
                        ], 403);
                    }
                }

                // Get role
                $roleKey = $request->role[$key] ?? null;
                $role_r = Role::find($roleKey);

                if (empty($role_r)) {
                    $role_r = Role::where('created_by', creatorId())->where('status', 0)->where('name', 'staff')->first();
                }

                // Create user
                $user_data = new User();
                $user_data->name = $this->toCamelCase($row[$request->name] ?? 'User');
                $user_data->email = $email;
                $user_data->password = null;
                $user_data->lang = 'en';
                $user_data->type = !empty($role_r) ? $role_r->name : 'staff';
                $user_data->is_enable_login = 0;
                $user_data->created_by = creatorId();
                $user_data->workspace_id = getActiveWorkSpace();
                $user_data->active_workspace = getActiveWorkSpace();

                if (empty($status) || $status != 'on') {
                    $user_data->email_verified_at = date('Y-m-d h:i:s');
                }

                $user_data->save();
                $user_data->addRole($role_r);

                $users_count++;

                $imported[] = [
                    'row' => $key + 1,
                    'email' => $email,
                    'name' => $user_data->name
                ];
            }

            return $this->jsonResponse(true, 'Import completed', [
                'total_imported' => $users_count,
                'imported' => $imported,
                'failed' => $failed
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * =====================================================
     * USER LOG HISTORY APIs
     * =====================================================
     */

    /**
     * GET /api/users/logs
     * Get user login history
     * 
     * Query Params:
     * - month (optional): Filter by month
     * - users (optional): Filter by user ID
     */
    public function UserLogHistory(Request $request)
    {
        if (!Auth::user()->isAbleTo('user logs history')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            $filteruser = User::where('created_by', creatorId())->get()->pluck('name', 'id');

            if (Auth::user()->type == 'super admin') {
                $filteruser = User::where('type', 'company')->get()->pluck('name', 'id');

                $query = \DB::table('login_details')
                    ->join('users', 'login_details.user_id', '=', 'users.id')
                    ->select(\DB::raw('login_details.*, users.id as user_id , users.name as user_name , users.email as user_email ,users.type as user_type'))
                    ->where('login_details.type', 'company');
            } elseif (Auth::user()->isAbleTo('user login manage')) {
                $query = \DB::table('login_details')
                    ->join('users', 'login_details.user_id', '=', 'users.id')
                    ->select(\DB::raw('login_details.*, users.id as user_id , users.name as user_name , users.email as user_email ,users.type as user_type'))
                    ->where(['login_details.created_by' => creatorId()]);
            } else {
                $query = \DB::table('login_details')
                    ->join('users', 'login_details.user_id', '=', 'users.id')
                    ->select(\DB::raw('login_details.*, users.id as user_id , users.name as user_name , users.email as user_email ,users.type as user_type'))
                    ->where(['login_details.user_id' => Auth::user()->id]);
            }

            if ($request->month) {
                $query->whereMonth('date', date('m', strtotime($request->month)));
                $query->whereYear('date', date('Y', strtotime($request->month)));
            } else {
                $query->whereMonth('date', date('m'));
                $query->whereYear('date', date('Y'));
            }

            if ($request->users) {
                $query->where('user_id', '=', $request->users);
            }

            $userdetails = $query->get()->sortDesc();

            return $this->jsonResponse(true, 'User logs retrieved successfully', [
                'logs' => $userdetails,
                'filter_users' => $filteruser
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * GET /api/users/logs/{id}
     * View specific login log
     */
    public function UserLogView(Request $request, $id)
    {
        try {
            $users_log = LoginDetail::find($id);

            if (!$users_log) {
                return $this->jsonResponse(false, 'Log not found', [], 404);
            }

            return $this->jsonResponse(true, 'Log retrieved successfully', [
                'log' => $users_log
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * DELETE /api/users/logs/{id}
     * Delete login log
     */
    public function UserLogDestroy(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('user delete')) {
            return $this->jsonResponse(false, 'Permission denied', [], 403);
        }

        try {
            LoginDetail::where('id', $id)->delete();

            return $this->jsonResponse(true, 'Log deleted successfully', []);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * =====================================================
     * IMPERSONATION APIs
     * =====================================================
     */

    /**
     * POST /api/users/{id}/impersonate
     * Impersonate as another user
     */
    public function LoginWithCompany(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->jsonResponse(false, 'User not found', [], 404);
            }

            if (auth()->check()) {
                Impersonate::take($request->user(), $user);
                return $this->jsonResponse(true, 'Now impersonating user', [
                    'impersonated_user' => $user->id
                ]);
            }

            return $this->jsonResponse(false, 'Cannot impersonate', [], 400);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * POST /api/users/impersonate-exit
     * Exit impersonation
     */
    public function ExitCompany(Request $request)
    {
        try {
            \Auth::user()->leaveImpersonation($request->user());
            return $this->jsonResponse(true, 'Impersonation exited', []);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Convert string to camel case (proper capitalization)
     * 
     * @param string $string
     * @return string
     */
    private function toCamelCase($string)
    {
        // Convert to title case (first letter of each word uppercase, rest lowercase)
        return ucwords(strtolower(trim($string)));
    }

    /**
     * =====================================================
     * COMPANY INFO APIs
     * =====================================================
     */

    /**
     * GET /api/users/{id}/company-info
     * Get company info with user/workspace counters
     */
    public function CompnayInfo(Request $request, $id)
    {
        try {
            $data = $this->Counter($id);

            if ($data['is_success']) {
                return $this->jsonResponse(true, 'Company info retrieved successfully', [
                    'users_data' => $data['response']['users_data'],
                    'workspce_data' => $data['response']['workspce_data']
                ]);
            }

            return $this->jsonResponse(false, 'Company not found', [], 404);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * POST /api/users/enable-disable
     * Enable/disable user or workspace
     * 
     * Form Data:
     * - id (required): User or Workspace ID
     * - name (required): 'user' or 'workspace'
     * - company_id (required): Company ID
     * - is_disable (required): 0 or 1
     */
    public function UserUnable(Request $request)
    {
        try {
            if (empty($request->id) || empty($request->company_id)) {
                return $this->jsonResponse(false, 'Invalid request', [], 400);
            }

            if ($request->name == 'user') {
                User::where('id', $request->id)->update(['is_disable' => $request->is_disable]);
                $data = $this->Counter($request->company_id);
            } elseif ($request->name == 'workspace') {
                $company = User::find($request->company_id);

                if ($company->active_workspace != $request->id) {
                    WorkSpace::where('id', $request->id)->update(['is_disable' => $request->is_disable]);
                } else {
                    return $this->jsonResponse(false, 'Active Workspace cannot be disabled', [], 400);
                }

                if ($request->is_disable == 0) {
                    User::where('workspace_id', $request->id)->where('type', '!=', 'company')->update(['is_disable' => $request->is_disable]);
                }

                $data = $this->Counter($request->company_id);
            }

            if (isset($data['is_success'])) {
                return $this->jsonResponse(
                    $request->is_disable == 1 ? 'User/Workspace enabled successfully' : 'User/Workspace disabled successfully',
                    [
                        'users_data' => $data['response']['users_data'],
                        'workspce_data' => $data['response']['workspce_data']
                    ]
                );
            }

            return $this->jsonResponse(false, 'Operation failed', [], 400);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * GET /api/users/{id}/counter
     * Get user/workspace counter data
     */
    public function Counter($id)
    {
        $response = [];

        if (!empty($id)) {
            $workspces = WorkSpace::where('created_by', $id)
                ->selectRaw('COUNT(*) as total_workspace, SUM(CASE WHEN is_disable = 0 THEN 1 ELSE 0 END) as disable_workspace, SUM(CASE WHEN is_disable = 1 THEN 1 ELSE 0 END) as active_workspace')
                ->first();

            $workspaces = WorkSpace::where('created_by', $id)->get();
            $users_data = [];

            foreach ($workspaces as $workspce) {
                $users = User::where('created_by', $id)->where('workspace_id', $workspce->id)
                    ->selectRaw('COUNT(*) as total_users, SUM(CASE WHEN is_disable = 0 THEN 1 ELSE 0 END) as disable_users, SUM(CASE WHEN is_disable = 1 THEN 1 ELSE 0 END) as active_users')
                    ->first();

                $users_data[$workspce->name] = [
                    'workspace_id' => $workspce->id,
                    'total_users' => !empty($users->total_users) ? $users->total_users : 0,
                    'disable_users' => !empty($users->disable_users) ? $users->disable_users : 0,
                    'active_users' => !empty($users->active_users) ? $users->active_users : 0,
                ];
            }

            $workspce_data = [
                'total_workspace' => $workspces->total_workspace,
                'disable_workspace' => $workspces->disable_workspace,
                'active_workspace' => $workspces->active_workspace,
            ];

            $response['users_data'] = $users_data;
            $response['workspce_data'] = $workspce_data;

            return [
                'is_success' => true,
                'response' => $response,
            ];
        }

        return [
            'is_success' => false,
            'error' => 'Plan is deleted.',
        ];
    }

    /**
     * POST /api/users/{id}/verify
     * Verify user email
     */
    public function verifeduser(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->jsonResponse(false, 'User not found', [], 404);
            }

            $user->email_verified_at = date('Y-m-d h:i:s');
            $user->save();

            return $this->jsonResponse(true, 'User verified successfully', []);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * =====================================================
     * HELPER METHODS
     * =====================================================
     */

    /**
     * Standard JSON response format
     */
    private function jsonResponse($status, $message, $data = [], $code = 200)
    {
        if (is_string($status)) {
            $message = $status;
            $status = true;
            $code = 200;
        }

        $response = [
            'status' => $status,
            'message' => $message,
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Upload file to storage
     */
    private function uploadFile($file, $folder = 'uploads')
    {
        $filenameWithExt = $file->getClientOriginalName();
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $fileNameToStore = $filename . '_' . time() . '.' . $extension;

        // Use the existing upload_file function if available
        if (function_exists('upload_file')) {
            $request = new Request();
            $path = upload_file($request, $folder, $fileNameToStore, $folder);
            if (!empty($path['url'])) {
                return $path['url'];
            }
        }

        // Fallback to direct storage
        $path = $file->storeAs('public/uploads/' . $folder, $fileNameToStore);
        return 'storage/uploads/' . $folder . '/' . $fileNameToStore;
    }
}
