<?php

namespace App\Http\Controllers;

use App\Events\DefaultData;
use App\Events\DestroyWorkSpace;
use App\Models\CustomDomainRequest;
use App\Models\User;
use App\Models\WorkSpace;
use App\Services\DefaultMasterDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Workdo\Taskly\Entities\Project;
use Illuminate\Support\Str;

class WorkSpaceController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        if (Auth::user()->isAbleTo('workspace create')) {
            // custom domain code

            $serverIp = $_SERVER['SERVER_ADDR'];

            $subdomain_name = str_replace(
                    [
                        'http://',
                        'https://',
                    ],
                    '',
                    env('APP_URL')
            );

            $serverIp = gethostbyname($subdomain_name);
            if ($serverIp != $_SERVER['SERVER_ADDR']) {
                $serverIp;
            } else {
                $serverIp = request()->server('SERVER_ADDR');
            }
            return view('workspace.create', compact('subdomain_name', 'serverIp'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        if (Auth::user()->isAbleTo('workspace create')) {
            
            
//            if (Auth::user()->type != 'super admin') {
//                $canUse = PlanCheck('Workspace', Auth::user()->id);
//                if ($canUse == false) {
//                    return redirect()->back()->with('error', 'You have maxed out the total number of Workspace allowed on your current plan');
//                }
//            }
            
            
            $validator = \Validator::make(
                    $request->all(), [
                'name' => 'required',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'gst_number' => 'nullable|string|max:20',
                'pan_number' => 'nullable|string|max:20',
                'account_number' => 'nullable|string|max:30',
                'ifsc_code' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'website' => 'nullable|string|max:255',
                'cin_no' => 'nullable|string|max:50',
                'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
                'terms_and_conditions' => 'nullable|string',
                    ]
            );

            // custom domain code
            if ($request->domain_switch == 'on') {
                if ($request->enable_domain == 'enable_domain') {
                    $validator = \Validator::make(
                            $request->all(), [
                        'domains' => 'required',
                            ]
                    );
                }
                if ($request->enable_domain == 'enable_subdomain') {
                    $validator = \Validator::make(
                            $request->all(), [
                        'subdomain' => 'required',
                            ]
                    );
                }
            }

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            try {
                $workspace = new WorkSpace();

                // custom domain code
                if ($request->domain_switch == 'on') {
                    $workspace->enable_domain = 'on';

                    if ($request->enable_domain == 'enable_domain') {

                        $input = $request->domains;
                        $input = trim($input, '/');
                        if (!preg_match('#^http(s)?://#', $input)) {
                            $input = 'http://' . $input;
                        }
                        $urlParts = parse_url($input);
                        $domain_name = preg_replace('/^www\./', '', $urlParts['host']);

                        $check = WorkSpace::where('domain', $domain_name)->first();
                        if ($check) {
                            return redirect()->back()->with('error', __('The domain has already been claimed. Please try a different one.'));
                        }

                        $workspace->domain_type = 'custom';
                        $custom_domain_request = new CustomDomainRequest();
                        $custom_domain_request->domain = $domain_name;
                        $custom_domain_request->status = 0;
                        $custom_domain_request->created_by = \Auth::user()->id;
                    }
                    if ($request->enable_domain == 'enable_subdomain') {
                        $input = env('APP_URL');
                        $input = trim($input, '/');
                        if (!preg_match('#^http(s)?://#', $input)) {
                            $input = 'http://' . $input;
                        }
                        $urlParts = parse_url($input);
                        $subdomain_name = preg_replace('/^www\./', '', $urlParts['host']);
                        $subdomain_name = $request->subdomain . '.' . $subdomain_name;

                        $check = WorkSpace::where('subdomain', $subdomain_name)->first();
                        if ($check) {
                            return redirect()->back()->with('error', __('The domain has already been claimed. Please try a different one.'));
                        }

                        $workspace->domain_type = 'subdomain';
                        $workspace->subdomain = $subdomain_name;
                    }
                }

                $workspace->name = $request->name;
                $workspace->created_by = \Auth::user()->id;
                
                // Business Information
                $workspace->contact_person = $request->contact_person;
                $workspace->phone = $request->phone;
                $workspace->email = $request->email;
                $workspace->address = $request->address;
                $workspace->city = $request->city;
                $workspace->state = $request->state;
                $workspace->pincode = $request->pincode;
                $workspace->country = $request->country;
                $workspace->gst_number = $request->gst_number;
                $workspace->pan_number = $request->pan_number;
                $workspace->bank_name = $request->bank_name;
                $workspace->account_number = $request->account_number;
                $workspace->ifsc_code = $request->ifsc_code;
                
                // Additional Business Details
                $workspace->website = $request->website;
                $workspace->cin_no = $request->cin_no;
                $workspace->terms_and_conditions = $request->terms_and_conditions;
                
                // Handle logo upload
                if ($request->hasFile('logo')) {
                    $fileName = time() . '_workspace_logo.' . $request->file('logo')->getClientOriginalExtension();
                    $upload = upload_file($request, 'logo', $fileName, 'workspace');
                    if ($upload['flag'] == 1) {
                        $workspace->logo = $upload['url'];
                    }
                }
                
                $workspace->save();
                
                // ✅ Seed global master data (once, not per workspace)
                app(DefaultMasterDataService::class)->seedAll();

                $msg = __('The workspace has been created successfully');

                if ($workspace->domain_type == 'custom') {
                    $custom_domain_request->workspace = $workspace->id;
                    $custom_domain_request->save();
                    $msg = __('The workspace has been created successfully') . '<br> <span class="text-danger">' . __("Your customdomain request will be approved by admin and then your domain is activated.") . '</span>';
                }

                $user = \Auth::user();
                $user->active_workspace = $workspace->id;
                $user->save();

                User::CompanySetting(\Auth::user()->id, $workspace->id);
                if (!empty(\Auth::user()->active_module)) {
                    event(new DefaultData(\Auth::user()->id, $workspace->id, \Auth::user()->active_module));
                }


                // return redirect()->route('dashboard')->with('success',$msg);
                return redirect()->back()->with('success', $msg);
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkSpace  $workSpace
     * @return \Illuminate\Http\Response
     */
    public function show(WorkSpace $workSpace) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkSpace  $workSpace
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        if (Auth::user()->isAbleTo('workspace edit')) {
            $workSpace = WorkSpace::find($id);

            // custom domain code

            $serverIp = $_SERVER['SERVER_ADDR'];

            $subdomain_name = str_replace(
                    [
                        'http://',
                        'https://',
                    ],
                    '',
                    env('APP_URL')
            );

            $serverIp = gethostbyname($subdomain_name);
            if ($serverIp != $_SERVER['SERVER_ADDR']) {
                $serverIp;
            } else {
                $serverIp = request()->server('SERVER_ADDR');
            }
            $sub_domain = $workSpace->subdomain;
            $parts = explode('.', $sub_domain); // Split the string by '.' delimiter
            $subdomain = $parts[0];

            $custom_domain_request = CustomDomainRequest::where('workspace', $workSpace->id)->first();

            return view('workspace.edit', compact('workSpace', 'subdomain_name', 'serverIp', 'subdomain', 'custom_domain_request'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\WorkSpace  $workSpace
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
{
    if (!Auth::user()->isAbleTo('workspace edit')) {
        return redirect()->back()->with('error', __('Permission denied.'));
    }

    $workSpace = WorkSpace::find($id);
    if (!$workSpace) {
        return redirect()->back()->with('error', __('Workspace not found.'));
    }

    // Validation
    $rules = [
        'name' => 'required',
        'phone' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255',
        'gst_number' => 'nullable|string|max:20',
        'pan_number' => 'nullable|string|max:20',
        'account_number' => 'nullable|string|max:30',
        'ifsc_code' => 'nullable|string|max:20',
        'address' => 'nullable|string',
        'website' => 'nullable|string|max:255',
        'cin_no' => 'nullable|string|max:50',
        'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
        'terms_and_conditions' => 'nullable|string',
    ];
    if ($request->domain_switch == 'on') {
        if ($request->enable_domain == 'enable_domain') {
            $rules['domains'] = 'required';
        }
        if ($request->enable_domain == 'enable_subdomain') {
            $rules['subdomain'] = 'required';
        }
    }

    $validator = \Validator::make($request->all(), $rules);
    if ($validator->fails()) {
        return redirect()->back()->with('error', $validator->getMessageBag()->first());
    }

    // Reset domain fields
    $workSpace->enable_domain = 'off';
    $workSpace->domain_type = null;
    $workSpace->domain = null;
    $workSpace->subdomain = null;

    // Domain logic
    if ($request->domain_switch == 'on') {
        $workSpace->enable_domain = 'on';

        if ($request->enable_domain == 'enable_domain') {
            $input = trim($request->domains, '/');
            if (!preg_match('#^http(s)?://#', $input)) {
                $input = 'http://' . $input;
            }
            $urlParts = parse_url($input);
            $domain_name = preg_replace('/^www\./', '', $urlParts['host']);

            $check = WorkSpace::where('domain', $domain_name)->where('id', '!=', $workSpace->id)->first();
            if ($check) {
                return redirect()->back()->with('error', __('The domain has already been claimed. Please try a different one.'));
            }

            $workSpace->domain_type = 'custom';
            $workSpace->domain = $domain_name;

            $custom_domain_request = CustomDomainRequest::where('workspace', $id)->first();
            if ($custom_domain_request) {
                $custom_domain_request->domain = $domain_name;
                $custom_domain_request->status = 0;
                $custom_domain_request->update();
            } else {
                $custom_domain_request = new CustomDomainRequest();
                $custom_domain_request->domain = $domain_name;
                $custom_domain_request->status = 0;
                $custom_domain_request->workspace = $id;
                $custom_domain_request->created_by = Auth::id();
                $custom_domain_request->save();
            }
        }

        if ($request->enable_domain == 'enable_subdomain') {
            $input = trim(env('APP_URL'), '/');
            if (!preg_match('#^http(s)?://#', $input)) {
                $input = 'http://' . $input;
            }
            $urlParts = parse_url($input);
            $subdomain_name = preg_replace('/^www\./', '', $urlParts['host']);
            $subdomain_name = $request->subdomain . '.' . $subdomain_name;

            $check = WorkSpace::where('subdomain', $subdomain_name)->where('id', '!=', $workSpace->id)->first();
            if ($check) {
                return redirect()->back()->with('error', __('The domain has already been claimed. Please try a different one.'));
            }

            $workSpace->domain_type = 'subdomain';
            $workSpace->subdomain = $subdomain_name;
        }
    }

    // Generate unique slug
    $slug = Str::slug($request->name);
    $count = WorkSpace::where('slug', 'LIKE', "{$slug}%")->where('id', '!=', $workSpace->id)->count();
    if ($count > 0) {
        $slug .= '-' . ($count + 1);
    }

    $workSpace->name = $request->name;
    $workSpace->slug = $slug;
    
    // Business Information
    $workSpace->contact_person = $request->contact_person;
    $workSpace->phone = $request->phone;
    $workSpace->email = $request->email;
    $workSpace->address = $request->address;
    $workSpace->city = $request->city;
    $workSpace->state = $request->state;
    $workSpace->pincode = $request->pincode;
    $workSpace->country = $request->country;
    $workSpace->gst_number = $request->gst_number;
    $workSpace->pan_number = $request->pan_number;
    $workSpace->bank_name = $request->bank_name;
    $workSpace->account_number = $request->account_number;
    $workSpace->ifsc_code = $request->ifsc_code;
    
    // Additional Business Details
    $workSpace->website = $request->website;
    $workSpace->cin_no = $request->cin_no;
    $workSpace->terms_and_conditions = $request->terms_and_conditions;
    
    // Handle logo upload
    if ($request->hasFile('logo')) {
        // Delete old logo if exists
        if ($workSpace->logo) {
            $filePath = public_path($workSpace->logo);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        $fileName = time() . '_workspace_logo.' . $request->file('logo')->getClientOriginalExtension();
        $upload = upload_file($request, 'logo', $fileName, 'workspace');
        if ($upload['flag'] == 1) {
            $workSpace->logo = $upload['url'];
        }
    }
    
    $workSpace->save();

    // ✅ Seed global master data (idempotent - safe to call multiple times)
    app(DefaultMasterDataService::class)->seedAll();

    return redirect()->back()->with('success', __('The workspace details are updated successfully'));
}


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkSpace  $workSpace
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkSpace $workSpace, $workspace_id) {
        if (Auth::user()->isAbleTo('workspace delete')) {
            $objUser = \Auth::user();
            $workspace = Workspace::find($workspace_id);

            if ($workspace && ($workspace->created_by == $objUser->id || $objUser->isAbleTo('workspace manage'))) {


                // Check if material is linked in indents
                $existsInprojects = \DB::table('projects')
                    ->where('workspace', $workspace_id)
                    ->exists();

                if ($existsInprojects) {
                    return response()->json(['status' => 0, 'message' => 'Site cannot be deleted because it is used in Projects.'], 400);
                } 


                $other_workspac = Workspace::where('created_by', $objUser->id)->where('is_disable', 1)->where('id', '!=', $workspace->id)->first();
                if ($other_workspac) {
                    if (!empty($other_workspac)) {
                        $objUser->active_workspace = $other_workspac->id;
                        $objUser->save();
                    }
                    // first parameter workspace
                    event(new DestroyWorkSpace($workspace));
                    $custom_domain_request = CustomDomainRequest::where('workspace', $workspace->id)->where('created_by', $workspace->created_by)->first();
                    if (!empty($custom_domain_request)) {
                        $custom_domain_request->delete();
                    }
                    $workspace->delete();

                    // custom domain code

                    $local = parse_url(config('app.url'))['host'];
                    // Get the request host
                    $remote = request()->getHost();
                    if ($local != $remote) {
                        if ($other_workspac->enable_domain == 'on') {
                            sideMenuCacheForget('company');

                            if ($other_workspac->domain_type == 'custom') {
                                return redirect('http://' . $other_workspac->domain . '/dashboard')->with('success', 'User Workspace change successfully.');
                            } else if ($other_workspac->domain_type == 'sub') {
                                return redirect($other_workspac->subdomain . '/dashboard')->with('success', 'User Workspace change successfully.');
                            }
                        }
                    }

                    return redirect()->route('dashboard')->with('success', __('The workspace has been deleted'));
                }
                return redirect()->route('dashboard')->with('errors', __("You can't delete Workspace! because your other workspaces are disabled "));
            } else {
                return redirect()->route('dashboard')->with('errors', __("You can't delete Workspace!"));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function change($workspace_id) {
        $check = WorkSpace::find($workspace_id);
        if (!empty($check)) {
            $users = User::where('email', \Auth::user()->email)->where('workspace_id', $workspace_id)->where('created_by', Auth::user()->created_by)->first();
            if (empty($users)) {
                $users = User::where('email', \Auth::user()->email)->Where('id', $check->created_by)->first();
            }
            if (empty($users)) {
                $users = User::where('email', \Auth::user()->email)->where('workspace_id', $workspace_id)->first();
            }
            if (empty($users)) {
                $users = User::where('email', \Auth::user()->email)->first();
            }


            $user = User::find($users->id);
            $user->active_workspace = $workspace_id;
            $user->active_project = getFirstProjectIdWithUser($workspace_id, $users->id);
//            $user->workspace_id = $workspace_id;
//            $user->site_id = getFirstProjectIdWithUser($workspace_id, $users->id);

            $user->save();
//            dd($user);
            if (!empty($user)) {
                Auth::login($user);

                if ($check->enable_domain == 'on') {
                    if ($check->domain_type == 'custom' && !empty($check->domain)) {
                        return redirect()->away('http://' . $check->domain . '/dashboard');
                    } else if ($check->domain_type == 'subdomain' && !empty($check->subdomain)) {
                        return redirect()->away('http://' . $check->subdomain . '/dashboard');
                    }
                }

                return redirect()->back()->with('success', 'The user workspace has been change successfully.');
            }
            return redirect()->back()->with('success', 'User Workspace change successfully.');
        } else {

            return redirect()->back()->with('error', "Workspace not found.");
        }
    }

    public function workspaceCheck(Request $request) {
        if (isset($request->slug)) {
            $workSpace = WorkSpace::where('slug', $request->slug)->where('id', '!=', $request->workspace)->exists();
            if (!$workSpace) {
                return response()->json(['success' => __('This Slug is Available.')]);
            }
        }
        return response()->json(['error' => __('This Slug Not Available.')]);
    }
    
    public function changeProject($site_id)
{
    $authUser = \Auth::user();
    $oldProjectId = $authUser->active_project;

    // Attempt to switch project
    $result = changeProject($site_id);

    // If project is locked
    if (is_array($result) && ($result['locked'] ?? false)) {
        return view('projects.locked', ['project' => $result['project']]);
    }

    if (!$result) {
        return redirect()->back()->with('error', 'Project not found.');
    }

    // Ensure project exists
    $project = Project::find($site_id);
    if (!$project) {
        return redirect()->back()->with('error', 'Project not found.');
    }

    // Get the correct user (clean fallback logic)
    $user = User::where('email', $authUser->email)
        ->where(function ($q) use ($site_id, $authUser, $project) {
            $q->where('site_id', $site_id)
              ->orWhere('created_by', $authUser->created_by)
              ->orWhere('id', $project->created_by);
        })
        ->first() ?? $authUser;

    // Update active project
    $user->active_project = $site_id;
    $user->save();

    // Update session and auth
    session(['active_project' => $site_id]);
    \Auth::setUser($user);

    // Get previous route from URL
    $previousUrl = url()->previous();

    try {
        $previousRoute = app('router')->getRoutes()->match(
            app('request')->create($previousUrl)
        );

        $previousRouteName = $previousRoute->getName();
        $previousParams = $previousRoute->parameters();

    } catch (\Exception $e) {
        $previousRouteName = null;
        $previousParams = [];
    }

    // If same project selected
    if ($oldProjectId == (int)$site_id) {
        return redirect()->back()->with('success', 'Project already active.');
    }

    // If valid previous route exists
    if ($previousRouteName && $previousRouteName !== 'project.changeProject') {

        // Replace project-related parameter
        foreach ($previousParams as $key => $value) {
            if (in_array($key, ['id', 'project', 'project_id', 'projectId'])) {
                $previousParams[$key] = $site_id;
            }
        }

        return redirect()
            ->route($previousRouteName, $previousParams)
            ->with('success', 'Project changed successfully.');
    }

    // Fallback
    return redirect()
        ->route('projects.show', ['project' => $site_id])
        ->with('success', 'Project changed successfully.');
}

//    public function changeProject($site_id) {
//        
//        $user_old_active_project=\Auth::user()->active_project;
//        $result = changeProject($site_id);
//        
//        // Check if project is locked (not 'Ongoing')
//        if (is_array($result) && isset($result['locked']) && $result['locked'] === true) {
//            return view('projects.locked', ['project' => $result['project']]);
//        }
//        
//        if ($result) {
//            $check = Project::find($site_id);
//            if (!empty($check)) {
//
//                $users = User::where('email', \Auth::user()->email)->where('site_id', $site_id)->where('created_by', Auth::user()->created_by)->first();
//                if (empty($users)) {
//                    $users = User::where('email', \Auth::user()->email)->Where('id', $check->created_by)->first();
//                }
//                if (empty($users)) {
//                    $users = User::where('email', \Auth::user()->email)->where('site_id', $site_id)->first();
//                }
//                if (empty($users)) {
//                    $users = User::where('email', \Auth::user()->email)->first();
//                }
//
//                $user = User::find($users->id);               
//                $user->active_project = $site_id;             
////                $user->site_id = $site_id;
//                $user->save();
//                
//                // Store active project in session for faster access
//                session(['active_project' => $site_id]);
//                
//                
//                
//                // Refresh the authenticated user to pick up the new active_project
//                \Auth::setUser($user);
//                
//                // Check if current route is a project-related route
//                $currentRoute = Route::currentRouteName();
//                $previousUrl = url()->previous();
//                var_dump($previousUrl);
//                var_dump($currentRoute);
//                var_dump($user_old_active_project);
//                var_dump($site_id);
//                die;
//                
//                // If on a project route (projects.show, projects.task.board, etc.), redirect to same route with new project ID
//                // Using PHP's native str_starts_with function
//                 if (($previousUrl!='project.changeProject') && ($user_old_active_project != (int)$site_id)) {
//                    // Get the current route parameters
//                    $routeParams = Route::current()->parameters();
//                    
//                    // Update the project ID parameter (could be 'id', 'projectId', 'project_id', or 'project')
//                    $updated = false;
//                    foreach ($routeParams as $key => $value) {
//                        // Check if this parameter looks like a project ID (numeric or matches project pattern)
//                        if (in_array($key, ['id', 'projectId', 'project_id', 'project'])) {
//                            $routeParams[$key] = $site_id;
//                            $updated = true;
//                            break;
//                        }
//                    }
//                    
//                    // If no project parameter found but route starts with projects., add it
//                    if (!$updated) {
//                        $routeParams['project'] = $site_id;
//                    }
//                    
////                    return redirect()->route($currentRoute, $routeParams)->with('success', 'Project changed successfully.');
//                    
//                     return redirect()->back()->with('success', '1 Project changed successfully.');
//                    
//                }else{
//                    
//                    return redirect()->route('projects.show', ['project' => $site_id])->with('success', ' 2 Project changed successfully.');
//                   
//                }
//                
//                // Default: redirect to projects.show with new project ID as fallback
//                // This ensures we always go to a project page after switching
//                // Note: Laravel resource routes use 'project' as the parameter name
//                return redirect()->route('projects.show', ['project' => $site_id])->with('success', '3 Project changed successfully.');
//            }
//        }
//        return redirect()->back()->with('error', 'Project not found.');
//    }
}
