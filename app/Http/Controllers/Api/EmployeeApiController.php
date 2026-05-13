<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Validator;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Workdo\Hrm\Entities\Branch;
use Workdo\Hrm\Entities\Department;
use Workdo\Hrm\Entities\Designation;
use Workdo\Hrm\Entities\DocumentType;
use Workdo\Hrm\Entities\Employee;
use Workdo\Hrm\Entities\EmployeeDocument;
use Workdo\Hrm\Entities\ExperienceCertificate;
use Workdo\Hrm\Entities\JoiningLetter;
use Workdo\Hrm\Entities\NOC;
use Workdo\Hrm\Entities\PaySlip;
use Workdo\Hrm\Entities\Termination;
use Workdo\Hrm\Events\CreateEmployee;
use Workdo\Hrm\Events\DestroyEmployee;
use Workdo\Hrm\Events\UpdateEmployee;
use Illuminate\Validation\Rule;
use Workdo\Hrm\DataTables\EmployeeDataTable;
use Illuminate\Support\Facades\Storage;
use Workdo\Taskly\Entities\UserProject;
use Illuminate\Support\Facades\DB;

/**
 * @group Employees
 * Endpoints for employee management including CRUD operations and documents
 */
class EmployeeApiController extends Controller {

    public function index(Request $request) {
    if (!Auth::user()->isAbleTo('employee manage')) {
        return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
    }
    
    // DEBUG: Log user type and permissions for diagnosis
    $currentUser = Auth::user();
    
    // Check if user has employee manage permission
    $hasEmployeeManage = $currentUser->isAbleTo('employee manage');
    
    // Get user type
    $userType = $currentUser->type;
    
    // Check if user type is company
    $isCompany = ($userType === 'company');
    
    // Check if user has employee manage permission
    $isAdmin = $currentUser->isAbleTo('employee manage');
    
    // Current user ID
    $currentUserId = $currentUser->id;
    
    // Current user's created_by (who created this user)
    $createdBy = $currentUser->created_by;
    
    // Current user's workspace_id
    $workspaceId = $currentUser->workspace_id;
    
    // Current user's site_id
    $siteId = $currentUser->site_id;
    
    // // Debug log to validate assumptions
    // \Log::error('[EmployeeApiController][index] User role-based access debug', [
    //     'user_id' => $currentUserId,
    //     'user_type' => $userType,
    //     'is_company' => $isCompany,
    //     'is_admin' => $isAdmin,
    //     'has_employee_manage' => $hasEmployeeManage,
    //     'created_by' => $createdBy,
    //     'user_workspace_id' => $workspaceId,
    //     'user_site_id' => $siteId,
    // ]);
    
    try {

        $query = Employee::query()
            ->select('employees.*', 'users.name as user_name', 'users.email as user_email', 'users.type as user_type', 'roles.name as role_name', 'roles.id as role_id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.id')
            ->leftJoin('roles', 'users.type', '=', 'roles.name')
            ->with(['branch','department','designation','documents','documents.documentType','creator','projects']);

        // Role-based filtering: 'company' and 'Admin' see all, others see only their own records
        // If user type is NOT 'company' and NOT 'Admin', filter by created_by
        if (!$isCompany && !$isAdmin) {
            // Non-admin users can only see employees they created
            $query->where('users.id', $currentUserId);
            
            // // Debug log for non-admin filtering
            // \Log::error('[EmployeeApiController][index] Non-admin user - filtering by created_by', [
            //     'user_id' => $currentUserId,
            //     'filter_created_by' => $currentUserId,
            // ]);
        } else {
            // // Admin or company users see all employees (no additional filter needed)
            // \Log::error('[EmployeeApiController][index] Admin/Company user - showing all employees', [
            //     'user_id' => $currentUserId,
            //     'user_type' => $userType,
            // ]);
        }

        // Workspace filter (check against user.workspace_id)
        $workspaceId = $request->input('workspace_id');
        if (!empty($workspaceId) && $workspaceId != 0) {
            $query->whereHas('user', function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            });
        }

        // Site filter (check against user.site_id)
        $siteId = $request->input('site_id');
        if (!empty($siteId) && $siteId != 0) {
            $query->whereHas('user', function ($q) use ($siteId) {
                $q->where('site_id', $siteId);
            });
        }

        // Fetch all results (no pagination)
        $employees = $query->get();

        // Remove sensitive fields from user in response
        foreach ($employees as $employee) {
            if (isset($employee->user)) {
                $employee->user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token']);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $employees,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}



    public function createData(Request $request) {
        try {

//        // Get authenticated user (JWT or Web)
//        try {
//            $AuthUser = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::user();
//        } catch (\Exception $e) {
//            $AuthUser = null;
//        }
//
//        if (!$AuthUser) {
//            $AuthUser = \Auth::user();
//        }
//
//        if (!$AuthUser) {
//            return response()->json([
//                'status' => 'error',
//                'message' => 'Unauthorized user'
//            ], 401);
//        }
            // Now safe to use $AuthUser
            $workspaceId = $request->input('workspace_id');
            $siteId = $request->input('site_id');
            $created_by = $request->input('created_by');

            // Roles
            $role = Role::query()
                    ->whereNotIn('id', [1, 2])
                    ->where('status', 0)
                    ->pluck('name', 'id');

            // Global master data - no workspace filter
            $documents = DocumentType::all();

            // Global master data - no workspace filter
            $branches = Branch::all()->pluck('name', 'id');

            // Global master data - no workspace filter
            $departments = Department::all()->pluck('name', 'id');

            // Global master data - no workspace filter
            $designations = Designation::all()->pluck('name', 'id');

            // Employees
            $employees = User::query()
                    ->when($created_by, fn($q) => $q->where('created_by', $created_by))
                    ->when($workspaceId, fn($q) => $q->where('workspace_id', $workspaceId))
                    ->when($siteId, fn($q) => $q->where('site_id', $siteId))
                    ->get();

            $employeesId = Employee::employeeIdFormat($this->employeeNumber());
            $location_type = Employee::$location_type;

            
            $assign_project=getSitesWithWorkspace();
            
            return response()->json([
                        'status' => 'success',
                        'employees' => $employees,
                        'employeesId' => $employeesId,
                        'departments' => $departments,
                        'designations' => $designations,
                        'documents' => $documents,
                        'branches' => $branches,
                        'role' => $role,
                        'customFields' => null,
                        'location_type' => $location_type,
                        'assign_project' => $assign_project,
                
                            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(),], 500);
        }
    }

    public function store(Request $request) {
        if (!Auth::user()->isAbleTo('employee create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Plan check
//            $canUse = PlanCheck('User', Auth::user()->id);
//            $company_settings = getCompanyAllSetting();
//
//            if ($canUse === false) {
//                return response()->json([
//                            'status' => 'error',
//                            'message' => 'You have maxed out the total number of Employees allowed on your current plan'
//                                ], 403);
//            }
//
//            // Role
            

        if (!Auth::user()->isAbleTo('employee create')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }
            // Validation rules
            
            
            
            $rules = [
                    'name' => 'required|string|max:120',
                    'email' => 'required|email|max:120',
                    'password' => 'required|string|min:6|max:120',

                    'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:9',
                    'dob' => 'required|date|before:' . date('Y-m-d'),
                    'gender' => 'required|string',

                    'address' => 'nullable|string|max:255',

                    'branch_id' => 'required|integer',
                    'department_id' => 'required|integer',
                    'designation_id' => 'required|integer',


                    'role' => 'required|integer',
                    

                    'company_doj' => 'required|date',

                    'workspace_id' => 'required|integer',
                    'site_id' => 'required|integer',
                    'created_by' => 'required|integer',

                    'project_id' => 'required|array',
                    'project_id.*' => 'integer',

                    'location_type' => 'nullable|string|max:50',

                    'account_holder_name' => 'nullable|string|max:120',
                    'account_number' => 'nullable|string|max:50',
                    'bank_name' => 'nullable|string|max:120',

                    'organisation_switch' => 'nullable|string|max:50',
                    'provident_fund_no' => 'nullable|string|max:100',

                    'emergency_contact_no' => 'nullable|string|max:20',
                    'emergency_address' => 'nullable|string|max:255',

                    'avatar' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',

                    'document' => 'required|array',
                    'document.*' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
                ];

            if ($request->hasFile('avatar')) {
                $rules['avatar'] = 'image|mimes:jpeg,png,jpg,gif|max:2048';
            }

            // Debug logging to identify the root cause
            \Log::error('[EmployeeApiController] Role lookup debug', [
                'request_role' => $request->role,
                'request_role_type' => gettype($request->role),
                'request_all' => $request->all(),
            ]);
            
            // Role lookup - now expects single integer
            $roles = Role::where('id', $request->role)->first();

            // Debug: Check total roles in database
            \Log::error('[EmployeeApiController] Total roles in DB', [
                'total_roles' => Role::count(),
                'role_ids' => Role::pluck('id')->toArray(),
            ]);
            
            // Get user type from role
            $userType = $roles->name ?? null;
            
            \Log::error('[EmployeeApiController] User type determined', [
                'user_type' => $userType,
            ]);

            // Debug: Log the result of the role lookup
            \Log::error('[EmployeeApiController] Role lookup result', [
                'roles' => $roles,
                'roles_is_null' => is_null($roles),
            ]);

            // Validate that role was found
            if (is_null($roles)) {
                \Log::error('[EmployeeApiController] Role not found', [
                    'request_role' => $request->role,
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid role provided. Role not found.',
                ], 422);
            }

            if (!isset($request->user_id)) {
                $rules['email'] = 'required|email|max:100|unique:users,email';
                $rules['password'] = 'required';
            }

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json([
                            'status' => 'error',
                            'message' => $validator->messages()->first(),
                            'errors' => $validator->errors()
                                ], 422);
            }

            // User creation or fetch
            if (isset($request->user_id)) {
                $user = User::find($request->user_id);
            } else {
                $user = User::create([
                    'name' => $this->toCamelCase($request->get('name')),
                    'email' => $request->get('email'),
                    'mobile_no' => $request->get('phone'),
                    'password' => Hash::make($request->get('password')),
                    'type' => $userType,
                    'lang' => 'en',
                    'workspace_id' => $request->get('workspace_id'),
                    'site_id' => $request->get('site_id'),
                    'active_workspace' => $request->get('workspace_id'),
                    'active_project' => $request->get('site_id'),                     
                    'created_by' => $request->get('created_by'),
                    'email_verified_at' => now(),
                ]);
                $user->addRole($roles);
            }

            if (empty($user)) {
                return response()->json([
                            'status' => 'error',
                            'message' => 'Something went wrong, please try again.'
                                ], 500);
            }

            // Update user name if changed
            if ($user->name != $request->name) {
                $user->name = $request->name;
                $user->save();
               
            }

            // Documents implode
            $document_implode = !empty($request->document) ? implode(',', array_keys($request->document)) : null;

            // Payment advice
            $payment_requires_work_advice = $request->payment_requires_work_advice ?? 'off';

            // Avatar upload
            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $filenameWithExt = $request->file('avatar')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('avatar')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($request, 'avatar', $fileNameToStore, 'employee-avatar');
                if ($path['flag'] == 0) {
                    return response()->json(['status' => 0, 'message' => $path['msg']], 500);
                }
                if (!empty($path['url'])) {
                    $avatarPath = $path['url'];
                }
            }

            // Employee creation
            $employee = Employee::create([
                'user_id' => $user->id,
                'name' => $this->toCamelCase($user->name),
                'dob' => $request->get('dob'),
                'gender' => $request->get('gender'),
                'phone' => $request->get('phone'),
                'email' => $user->email,
                'passport_country' => $request->get('passport_country'),
                'passport' => $request->get('passport'),
                'location_type' => $request->get('location_type'),
                'country' => $request->get('country'),
                'state' => $request->get('state'),
                'city' => $request->get('city'),
                'zipcode' => $request->get('zipcode'),
                'address' => $request->get('address'),
                'employee_id' => $this->employeeNumber(),
                'branch_id' => $request->get('branch_id'),
                'department_id' => $request->get('department_id'),
                'designation_id' => $request->get('designation_id'),
                'company_doj' => $request->get('company_doj'),
                'documents' => $document_implode,
                'account_holder_name' => $request->get('account_holder_name'),
                'account_number' => $request->get('account_number'),
                'bank_name' => $request->get('bank_name'),
                'bank_identifier_code' => $request->get('bank_identifier_code'),
                'branch_location' => $request->get('branch_location'),
                'tax_payer_id' => $request->get('tax_payer_id'),
                'hours_per_day' => $request->get('hours_per_day'),
                'annual_salary' => $request->get('annual_salary'),
                'days_per_week' => $request->get('days_per_week'),
                'fixed_salary' => $request->get('fixed_salary'),
                'hours_per_month' => $request->get('hours_per_month'),
                'rate_per_day' => $request->get('rate_per_day'),
                'days_per_month' => $request->get('days_per_month'),
                'rate_per_hour' => $request->get('rate_per_hour'),
                'payment_requires_work_advice' => $payment_requires_work_advice,
                'workspace' => $user->workspace_id,
                'active_workspace' => $user->workspace_id,
                'site_id' => $user->site_id,
                'active_project' => $user->site_id,
                'created_by' => $user->created_by,
                'avatar' => $avatarPath,
                'organisation_switch' => $request->get('organisation_switch'),
                'provident_fund_no' => $request->get('provident_fund_no'),
                'emergency_contact_no' => $request->get('emergency_contact_no'),
                'emergency_address' => $request->get('emergency_address'),
            ]);

            // Employee documents upload
            if ($request->hasFile('document')) {
                foreach ($request->document as $key => $document) {
                    $filenameWithExt = $request->file('document')[$key]->getClientOriginalName();
                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension = $request->file('document')[$key]->getClientOriginalExtension();
                    $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                    $upload = multi_upload_file($document, 'document', $fileNameToStore, 'emp_document');
                    if ($upload['flag'] == 1) {
                        $url = $upload['url'];
                    } else {
                        return response()->json(['status' => 'error', 'message' => $upload['msg']], 500);
                    }

                    EmployeeDocument::create([
                        'employee_id' => $employee->id,
                        'document_id' => $key,
                        'document_value' => !empty($url) ? $url : '',
                    ]);
                }
            }

            // Custom fields
//        if (module_is_active('CustomField')) {
//            \Workdo\CustomField\Entities\CustomField::saveData($employee, $request->customField);
//        }
            // Fire event
            event(new CreateEmployee($request, $employee));

            $projectIds = $request->input('project_id', []);
            if (!empty($projectIds)) {
                
                foreach ($projectIds as $projectId) {
                    UserProject::firstOrCreate([
                        'user_id' => $user->id,
                        'project_id' => $projectId,
                    ]);
                    
                    
                    
                    
                }
                
                
                // Optionally set the first selected project as active
            if (!empty($projectIds)) {
                
                

                if (in_array($request->get('site_id'), $projectIds)) {
                    $firstProjectId = $request->get('site_id');
                } else {
                    $firstProjectId = $projectIds[0] ?? $request->get('site_id'); // use null if array is empty
                }

                
                $workspaceId = getWorkspaceIDFromSiteID($firstProjectId);

                $user->active_project = $firstProjectId;
                $user->active_workspace = $workspaceId;
                $user->workspace_id = $workspaceId;
                $user->site_id = $firstProjectId;
                $user->save();
            }
                
                

                
            }

            
            
            $employee = Employee::with([
                        'user',
                        'branch',
                        'department',
                        'designation',
                        'documents',
                        'documents.documentType',
                        'projects'
                    ])->find($employee->id);

            // Get role details
            $roleDetails = null;
            if (!empty($user->type)) {
                $roleDetails = Role::where('name', $user->type)->first();
            }

            // Add role details to employee array and remove sensitive fields
            $employeeArray = $employee->toArray();
            
            // Remove sensitive fields from user in response
            if (isset($employeeArray['user'])) {
                unset(
                    $employeeArray['user']['password'],
                    $employeeArray['user']['two_factor_secret'],
                    $employeeArray['user']['two_factor_recovery_codes'],
                    $employeeArray['user']['two_factor_confirmed_at'],
                    $employeeArray['user']['remember_token']
                );
            }
            
            $employeeArray['user_type'] = $user->type;
            $employeeArray['role_name'] = $roleDetails->name ?? null;
            $employeeArray['role_id'] = $roleDetails->id ?? null;

            return response()->json([
                        'status' => 'success',
                        'message' => 'The employee has been created successfully.',
                        'data' => $employeeArray,
                            ], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(),], 500);
        }
    }
    
    public function show(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('employee show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
    try {
        $empId = $id;

        // Filters
        $workspaceId = $request->input('workspace_id');
        $siteId      = $request->input('site_id');
        $created_by  = $request->input('created_by');

        // Employee with relations - join with users and roles to get role details
        $query = Employee::query()
            ->select('employees.*', 'users.name as user_name', 'users.email as user_email', 'users.type as user_type', 'roles.name as role_name', 'roles.id as role_id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.id')
            ->leftJoin('roles', 'users.type', '=', 'roles.name')
            ->with([
                'user',
                'branch',
                'department',
                'designation',
                'documents',
                'documents.documentType',
            ])
            ->where('user_id', $empId);

        // Workspace filter (check against user.workspace_id)
        if (!empty($workspaceId) && $workspaceId != 0) {
            $query->whereHas('user', function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            });
        }

        // Site filter (check against user.site_id)
        if (!empty($siteId) && $siteId != 0) {
            $query->whereHas('user', function ($q) use ($siteId) {
                $q->where('site_id', $siteId);
            });
        }

        $employee = $query->first();

        // Remove sensitive fields from user in response
        if ($employee && isset($employee->user)) {
            $employee->user->makeHidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token']);
        }

        // Get user and assigned projects
        $user = User::where('id', $id)->first();
        $selectedProjects = $user->projects()
            ->select('projects.id', 'projects.name')
            ->pluck('name', 'id')
            ->toArray();

        if (!$employee) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Employee not found.'
            ], 404);
        }

        return response()->json([
            'status'            => 'success',
            'employee'          => $employee,
            'assigned_projects' => $selectedProjects,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ], 500);
    }
}



//    public function show(Request $request, $id) {
//        try {
//            // Permission check
////        if (!Auth::user()->isAbleTo('employee show')) {
////            return response()->json([
////                'status'  => 'error',
////                'message' => 'Permission denied.'
////            ], 403);
////        }
//            // Decrypt employee id
//            //
//            //
////        try {
////            $empId = Crypt::decrypt($id);
////        } catch (\Throwable $th) {
////            return response()->json([
////                'status'  => 'error',
////                'message' => 'Employee Not Found.'
////            ], 404);
////        }
////            dd(getActiveWorkSpace(), creatorId(), getCompanyAllSetting());
//
//            $empId = $id;
//            // Filters
////            $workspaceId = $request->input('workspace_id', getActiveWorkSpace());
////            $created_by = $request->input('created_by', creatorId());
//
//            $company_settings = getCompanyAllSetting();
//
//            $workspaceId = $request->input('workspace_id');
//            $siteId = $request->input('site_id');
//            $created_by = $request->input('created_by');
//
//            // Employee
////            $employee = Employee::query()
////                    ->where('user_id', $empId)
////                    ->when(!empty($workspaceId) && $workspaceId != 0, function ($q) use ($workspaceId) {
////                        $q->where('workspace', $workspaceId);
////                    })
////                    ->when(!empty($created_by) && $created_by != 0, function ($q) use ($created_by) {
////                        $q->where('created_by', $created_by);
////                    })
////                    ->first();
//                    
//                    
//                $query = Employee::query()
//                    ->with(['user','branch','department','designation','documents','documents.documentType'])
//                    ->where('user_id', $empId);
//
//                // Workspace filter
//                if (!empty($workspaceId) && $workspaceId != 0) {
//                    $query->where('workspace', $workspaceId);
//                }
//
//                // Site filter (check against user.site_id)
//                if (!empty($siteId) && $siteId != 0) {
//                    $query->whereHas('user', function ($q) use ($siteId) {
//                        $q->where('site_id', $siteId);
//                    });
//                }
//
//                // Fetch single employee
//                $employee = $query->first();
//
//               
//    
//                    
//                    
//
//            if (empty($employee)) {
//                return response()->json([
//                            'status' => 'error',
//                            'message' => 'Employee not found.'
//                                ], 404);
//            }
//
////            // Documents
////            $documents = DocumentType::query()
////                    ->when(!empty($workspaceId) && $workspaceId != 0, function ($q) use ($workspaceId) {
////                        $q->where('workspace', $workspaceId);
////                    })                 
////                    ->get();
////
////            // Payslips
////            $payslips = PaySlip::query()
////                    ->where('employee_id', $employee->id)
////                    ->when(!empty($workspaceId) && $workspaceId != 0, function ($q) use ($workspaceId) {
////                        $q->where('workspace', $workspaceId);
////                    })                   
////                    ->latest()
////                    ->get();
////
////            // User
////            $user = User::query()
////                    ->where('id', $empId)
////                    ->when(!empty($workspaceId) && $workspaceId != 0, function ($q) use ($workspaceId) {
////                        $q->where('workspace_id', $workspaceId);
////                    })
////                    ->first();
////
////            // Employee ID format
////            $employeesId = Employee::employeeIdFormat($employee->employee_id);
////            $location_type = Employee::$location_type;
//
//            // Custom fields
////            $customFields = null;
////        if (module_is_active('CustomField')) {
////            $employee->customField = \Workdo\CustomField\Entities\CustomField::getData($employee, 'hrm', 'Employee');
////            $customFields = \Workdo\CustomField\Entities\CustomField::query()
////                ->when(!empty($workspaceId) && $workspaceId != 0, function ($q) use ($workspaceId) {
////                    $q->where('workspace_id', $workspaceId);
////                })
////                ->where('module', '=', 'hrm')
////                ->where('sub_module', 'Employee')
////                ->get();
////        }
//
//            return response()->json([
//                        'status' => 'success',
//                        'employee' => $employee,                        
//                            ], 200);
//            
//            
////            return response()->json([
////                        'status' => 'success',
////                        'employee' => $employee,
////                        'user' => $user,
////                        'employeesId' => $employeesId,
////                        'documents' => $documents,
////                        'customFields' => $customFields,
////                        'location_type' => $location_type,
////                        'payslips' => $payslips,
////                        'company_settings' => $company_settings,
////                            ], 200);
//            
//        } catch (\Exception $e) {
//            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(),], 500);
//        }
//    }

    public function edit(Request $request, $id) {
        try {

            $empId = $id;
            // Decrypt employee id
//        try {
//            $empId = Crypt::decrypt($id);
//        } catch (\Throwable $th) {
//            return response()->json([
//                'status'  => 'error',
//                'message' => 'Employee Not Found.'
//            ], 404);
//        }
            // Permission check
//        if (!Auth::user()->isAbleTo('employee edit')) {
//            return response()->json([
//                'status'  => 'error',
//                'message' => 'Permission denied.'
//            ], 403);
//        }
            // Filters
            $workspaceId = $request->input('workspace_id', getActiveWorkSpace());
            $created_by = $request->input('created_by', creatorId());

            // Document types - Global (no workspace filter)
            $document_types = DocumentType::query()->get();

            // Branches - Global (no workspace filter)
            $branches = Branch::query()->pluck('name', 'id');

            // Departments - Global (no workspace filter)
            $departments = Department::query()->pluck('name', 'id');

            // Designations - Global (no workspace filter)
            $designations = Designation::query()->pluck('name', 'id');

            // Employee
            $employee = Employee::query()
                    ->where('user_id', $empId)
                    ->when(!empty($workspaceId) && $workspaceId != 0, fn($q) => $q->where('workspace', $workspaceId))
                    ->first();

            // User
            $user = User::query()
                    ->where('id', $empId)
                    ->when(!empty($workspaceId) && $workspaceId != 0, fn($q) => $q->where('workspace_id', $workspaceId))
                    ->first();

            $location_type = Employee::$location_type;

            // Custom fields
            $customFields = null;
            if (!empty($employee)) {
//            if (module_is_active('CustomField')) {
//                $employee->customField = \Workdo\CustomField\Entities\CustomField::getData($employee, 'hrm', 'Employee');
//                $customFields = \Workdo\CustomField\Entities\CustomField::query()
//                    ->when(!empty($workspaceId) && $workspaceId != 0, fn($q) => $q->where('workspace_id', $workspaceId))
//                    ->where('module', '=', 'hrm')
//                    ->where('sub_module', 'Employee')
//                    ->get();
//            }
                $employeesId = Employee::employeeIdFormat($employee->employee_id);
            } else {
//            if (module_is_active('CustomField')) {
//                $customFields = \Workdo\CustomField\Entities\CustomField::query()
//                    ->when(!empty($workspaceId) && $workspaceId != 0, fn($q) => $q->where('workspace_id', $workspaceId))
//                    ->where('module', '=', 'hrm')
//                    ->where('sub_module', 'Employee')
//                    ->get();
//            }
                $employeesId = Employee::employeeIdFormat($this->employeeNumber());
            }
            
           
            $role = Role::query()
                    ->whereNotIn('id', [1, 2])
                    ->where('status', 0)
                    ->pluck('name', 'id');
            
             $selectedRoleId = $role->search($user->type); 
            return response()->json([
                        'status' => 'success',
                        'employee' => $employee,
                        'user' => $user,
                        'employeesId' => $employeesId,
                        'branches' => $branches,
                        'departments' => $departments,
                        'designations' => $designations,
                        'document_types' => $document_types,
                        'customFields' => $customFields,
                        'location_type' => $location_type,
                        'role' => $role,
                        'selectedRoleId'=> $selectedRoleId,
                
                            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(),], 500);
        }
    }

    public function update(Request $request, $id) {
        if (!Auth::user()->isAbleTo('employee edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
//        // Permission check
//        if (!Auth::user()->isAbleTo('employee edit')) {
//            return response()->json([
//                'status'  => 'error',
//                'message' => 'Permission denied.'
//            ], 403);
//        }
            // Validation rules
            $rules = [
                'dob' => 'required',
                'gender' => 'required',
                'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:9',
                'address' => 'required',
                'organisation_switch' => 'nullable|string|max:50',
                'provident_fund_no' => 'nullable|string|max:100',
                'role' => 'required|integer',   
                'company_doj' => 'required|date',
                'workspace_id' => 'required|integer',
                'site_id' => 'required|integer',
                'created_by' => 'required|integer',
                'project_id' => 'required|array',
                'project_id.*' => 'integer',
            ];

            if ($request->hasFile('avatar')) {
                $rules['avatar'] = 'image|mimes:jpeg,png,jpg,gif|max:2048';
            }

            $employee = Employee::findOrFail($id);

//        // Biometric validation
//        if (module_is_active('BiometricAttendance')) {
//            if ($request->has('biometric_emp_id') && $employee->biometric_emp_id != $request->biometric_emp_id) {
//                $rules['biometric_emp_id'] = [
//                    'required',
//                    Rule::unique('employees')->where(function ($query) {
//                        return $query->where('created_by', creatorId())
//                                     ->where('workspace', getActiveWorkSpace());
//                    })
//                ];
//            }
//        }

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json([
                            'status' => 'error',
                            'message' => $validator->messages()->first(),
                            'errors' => $validator->errors()
                                ], 422);
            }

            // User
            $user = User::where('id', $request->user_id)->first();
            
            
            
            if (empty($user)) {
                return response()->json([
                            'status' => 'error',
                            'message' => 'Something went wrong, please try again.'
                                ], 500);
            }

            if ($user->name != $request->name) {
                $camelCaseName = $this->toCamelCase($request->name);
                $user->name = $camelCaseName;
                $user->save();
            }

            // Role update - get role from request and update user's role assignment
            $role = Role::where('id', $request->role)->first();
            if ($role) {
                // Update user type to match role name
                $userType = $role->name;
                if ($user->type != $userType) {
                    $user->type = $userType;
                    $user->save();
                }
                
                // Remove all existing roles and add the new role
                $user->roles()->detach();
                $user->addRole($role);
            }

            // Avatar upload
            if ($request->hasFile('avatar')) {
                $employee_temp = $employee->avatar;
                $filenameWithExt = $request->file('avatar')->getClientOriginalName();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension = $request->file('avatar')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($request, 'avatar', $fileNameToStore, 'employee-avatar');

                if (!empty($employee_temp) && strpos($employee_temp, 'avatar.png') === false && check_file($employee_temp)) {
                    delete_file($employee_temp);
                }

                if (!empty($path['url'])) {
                    $employee->avatar = $path['url'];
                }
            }

            // Handle documents
            if ($request->document) {
                foreach ($request->document as $key => $document) {
                    if (!empty($document)) {
                        $filenameWithExt = $request->file('document')[$key]->getClientOriginalName();
                        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                        $extension = $request->file('document')[$key]->getClientOriginalExtension();
                        $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                        $upload = multi_upload_file($document, 'document', $fileNameToStore, 'emp_document');
                        if ($upload['flag'] == 1) {
                            $url = $upload['url'];
                        } else {
                            return response()->json([
                                        'status' => 'error',
                                        'message' => $upload['msg']
                                            ], 500);
                        }

                        $employee_document = EmployeeDocument::where('employee_id', $employee->employee_id)
                                ->where('document_id', $key)
                                ->first();

                        if (!empty($employee_document)) {
                            if (!empty($employee_document->document_value)) {
                                delete_file($employee_document->document_value);
                            }
                            $employee_document->document_value = $url;
                            $employee_document->save();
                        } else {
                            EmployeeDocument::create([
                                'employee_id' => $employee->id,
                                'document_id' => $key,
                                'document_value' => $url,
                            ]);
                        }
                    }
                }
            }

            // Fill other fields
            $employee->organisation_switch = $request->get('organisation_switch');
            $employee->provident_fund_no = $request->get('provident_fund_no');
            $employee->emergency_contact_no = $request->get('emergency_contact_no');
            $employee->emergency_address = $request->get('emergency_address');

            $employee->fill($request->except(['avatar']));
            $employee->save();

//        // Custom fields
//        if (module_is_active('CustomField')) {
//            \Workdo\CustomField\Entities\CustomField::saveData($employee, $request->customField);
//        }
            // Fire event
            event(new UpdateEmployee($request, $employee));
            
            $projectIds = $request->input('project_id', []); // array of selected project IDs
            // Remove old links and insert new ones
            DB::table('user_projects')
            ->where('user_id', $user->id)
            ->delete();


            foreach ($projectIds as $projectId) {
                UserProject::firstOrCreate([
                    'user_id' => $user->id,
                    'project_id' => $projectId,
                ]);
            }

            // Optionally set the first selected project as active
            if (!empty($projectIds)) {
                
                $projectIds = $request->input('project_id', []); // array of selected project IDs

                if (in_array($request->get('site_id'), $projectIds)) {
                    $firstProjectId = $request->get('site_id');
                } else {
                    $firstProjectId = $projectIds[0] ?? $request->get('site_id'); // use null if array is empty
                }

                
                $workspaceId = getWorkspaceIDFromSiteID($firstProjectId);

                $user->active_project = $firstProjectId;
                $user->active_workspace = $workspaceId;
                $user->workspace_id = $workspaceId;
                $user->site_id = $firstProjectId;
                $user->save();
            }

            // Get the employee with relations after update
            $employee = Employee::with([
                'user',
                'branch',
                'department',
                'designation',
                'documents',
                'documents.documentType',
            ])->find($employee->id);

            // Remove sensitive fields from user in response
            $employeeArray = $employee->toArray();
            if (isset($employeeArray['user'])) {
                unset(
                    $employeeArray['user']['password'],
                    $employeeArray['user']['two_factor_secret'],
                    $employeeArray['user']['two_factor_recovery_codes'],
                    $employeeArray['user']['two_factor_confirmed_at'],
                    $employeeArray['user']['remember_token']
                );
            }

            return response()->json([
                        'status' => 'success',
                        'message' => 'The employee details are updated successfully.',
                        'data' => $employeeArray,
                        'user_type' => $user->type ?? null,
                        'role_name' => !empty($user->type) ? Role::where('name', $user->type)->value('name') : null,
                        'role_id' => !empty($user->type) ? Role::where('name', $user->type)->value('id') : null,
                            ], 200);
        } catch (\Exception $e) {
            
            \Log::error('update employee error: ' . 'message' . $e->getMessage() .'line' . $e->getLine() . 'file' . $e->getFile());
            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(),], 500);
        }
    }

    public function destroy($id)
{
    // ✅ Permission check (single)
    if (!\Auth::user()->isAbleTo('employee delete')) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Permission denied.'
        ], 403);
    }

    \DB::beginTransaction();

    try {
        // ✅ Find employee
        $employee = Employee::where('user_id', $id)->first();

        if (empty($employee)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee already deleted or not found.'
            ], 404);
        }

        // ✅ VALIDATION FIRST (critical fix)
        $hasAttendance = \Workdo\Hrm\Entities\Attendance::where('employee_id', $employee->id)->exists();

        if ($hasAttendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete employee. Attendance records exist for this employee.'
            ], 400);
        }

        \Log::info('EmployeeApiController@destroy: Deleting Employee ID: ' . $employee->id . ' (User ID: ' . $id . ')');

        // ✅ Delete employee documents
        $emp_documents = EmployeeDocument::where('employee_id', $employee->employee_id)->get();

        foreach ($emp_documents as $emp_document) {
            if (!empty($emp_document->document_value)) {
                delete_file($emp_document->document_value);
            }
            $emp_document->delete();
        }

        // ✅ Delete payslips
        $pay_slips = PaySlip::where('employee_id', $employee->id)->get();
        foreach ($pay_slips as $pay_slip) {
            $pay_slip->delete();
        }

        // ✅ Delete custom fields
        if (module_is_active('CustomField')) {
            $customFields = \Workdo\CustomField\Entities\CustomField::where('module', 'Hrm')
                ->where('sub_module', 'Employee')
                ->get();

            foreach ($customFields as $customField) {
                $value = \Workdo\CustomField\Entities\CustomFieldValue::where('record_id', $employee->id)
                    ->where('field_id', $customField->id)
                    ->first();

                if (!empty($value)) {
                    $value->delete();
                }
            }
        }

        // ✅ Fire event
        event(new DestroyEmployee($employee));

        // ✅ Delete employee
        $employee->delete();

        // ✅ Delete related user (with condition)
        $user = User::find($id);

        if ($user && !in_array($user->type, ['super admin', 'company'])) {
            \Log::info('EmployeeApiController@destroy: Deleting related User ID: ' . $id);
            $user->delete();
        } elseif ($user) {
            \Log::info('EmployeeApiController@destroy: Skipped deleting User ID: ' . $id . ' (type: ' . $user->type . ')');
        }

        \DB::commit();

        return response()->json([
            'status'  => 'success',
            'message' => 'The employee has been deleted successfully.'
        ], 200);

    } catch (\Exception $e) {

        \DB::rollBack();

        \Log::error('EmployeeApiController@destroy Error: ' . $e->getMessage(), [
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'user_id' => $id
        ]);

        return response()->json([
            'status'  => 'error',
            'message' => 'Something went wrong while deleting employee.',
            'debug'   => $e->getMessage()
        ], 500);
    }
}

    function employeeNumber() { $maxId = Employee::max('id'); return $maxId ? $maxId + 1 : 1; }
    
//    function employeeNumber() {
//        $latest = Employee::where('workspace', getActiveWorkSpace())->where('created_by', creatorId())->latest()->first();
//        if (!$latest) {
//            return 1;
//        }
//        return $latest->employee_id + 1;
//    }

    public function getdepartment(Request $request) {
        // Determine workspace
        if ($request->workspace_id && $request->workspace_id != 0) {
            $workspace_id = $request->workspace_id;
        } else {
            $workspace_id = getActiveWorkSpace();
        }

        // Build query - Global (no workspace filter)
        $query = Department::query();

        if ($request->branch_id && $request->branch_id != 0) {
            $query->where('branch_id', $request->branch_id);
        }

        // Optional: add creator filter if needed
        // $query->where('created_by', creatorId());

        $departments = $query->pluck('name', 'id')->toArray();

        return response()->json($departments);
    }

//    public function getdepartment(Request $request)
//    {
//        if ($request->branch_id == 0) {
//            $departments = Department::where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->get()->pluck('name', 'id')->toArray();
//        } else {
//            $departments = Department::where('branch_id', $request->branch_id)->where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->get()->pluck('name', 'id')->toArray();
//        }
//        return response()->json($departments);
//    }


    public function getdDesignation(Request $request) {
        // Determine workspace
        if ($request->workspace_id && $request->workspace_id != 0) {
            $workspace_id = $request->workspace_id;
        } else {
            $workspace_id = getActiveWorkSpace();
        }

        // Build query - Global (no workspace filter)
        $query = Designation::query();

        if ($request->department_id && $request->department_id != 0) {
            $query->where('department_id', $request->department_id);
        }

        $designations = $query->pluck('name', 'id')->toArray();

        return response()->json($designations);
    }

//    public function getdDesignation(Request $request)
//    {
//        if ($request->department_id == 0) {
//            $designations = Designation::where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->get()->pluck('name', 'id')->toArray();
//        } else {
//            $designations = Designation::where('department_id', $request->department_id)->where('created_by', '=', creatorId())->where('workspace', getActiveWorkSpace())->get()->pluck('name', 'id')->toArray();
//        }
//        return response()->json($designations);
//    }

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
}
