<?php

use App\Models\AddOn;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\Language;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkSpace;
use App\Models\userActiveModule;
use App\Models\PaymentModuleAllocation;
use App\Models\PaymentsModule;
use Illuminate\Support\Collection;
use App\Models\Setting;
use App\Models\UserCoupon;
use Illuminate\Support\Facades\Auth;
use App\Facades\ModuleFacade as Module;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\UserProject;

if (!function_exists('getMenu')) {

    function getMenu() {
        // Check if user is logged in
        if (!auth()->check()) {
            // If not authenticated, redirect to login
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Include active_project in cache key to invalidate on project change
        $activeProject = session('active_project', 0);
        return Cache::rememberForever(
                        'sidebar_menu_' . $user->id . '_project_' . $activeProject,
                        function () use ($user) {
                            $role = $user->roles->first();
                            $menu = new \App\Classes\Menu($user);

                            if ($role && $role->name === 'super admin') {
                                event(new \App\Events\SuperAdminMenuEvent($menu));
                            } else {
                                // Admin and Company users get the same menu
                                event(new \App\Events\CompanyMenuEvent($menu));
                            }

                            $collection = collect($menu->menu);
                            $grouped = $collection->groupBy('category')->toArray();

                            $categoryIcon = categoryIcon();

                            uksort($grouped, function ($a, $b) use ($categoryIcon) {
                                $indexA = array_search($a, array_keys($categoryIcon));
                                $indexB = array_search($b, array_keys($categoryIcon));
                                return $indexA <=> $indexB;
                            });

                            return generateMenu($grouped, null);
                        }
                );
    }

}


if (!function_exists('generateMenu')) {

    function generateMenu($grouped, $parent = null) {
        $html = '';

        foreach ($grouped as $category => $menuItems) {
            $company_settings = getCompanyAllSetting();
            if (!empty($company_settings['category_wise_sidemenu']) && $company_settings['category_wise_sidemenu'] == 'on') {
                $icon = isset(categoryIcon()[$category]) ? categoryIcon()[$category] : 'home';
                $html .= '<li class="dash-item dash-caption">
                        <label>' . $category . '</label>
                        <i class="ti ti-' . $icon . '"></i>
                      </li>';
            }

            $html .= generateSubMenu($menuItems, $parent);
        }

        return $html;
    }

}

if (!function_exists('generateSubMenu')) {

    function generateSubMenu($menuItems, $parent = null) {
        $html = '';

        $filteredItems = array_filter($menuItems, function ($item) use ($parent) {
            return $item['parent'] == $parent;
        });

        usort($filteredItems, function ($a, $b) {
            return $a['order'] - $b['order'];
        });

        foreach ($filteredItems as $item) {
            $hasChildren = hasChildren($menuItems, $item['name']);
            if ($item['parent'] == null) {
                $html .= '<li class="dash-item dash-hasmenu">';
            } else {
                $html .= '<li class="dash-item">';
            }
            $html .= '<a href="' . (!empty($item['route']) ? route($item['route'], $item['parameters'] ?? []) : '#!') . '" class="dash-link">';

            if ($item['parent'] == null) {
                $html .= ' <span class="dash-micon"><i class="ti ti-' . $item['icon'] . '"></i></span>
                <span class="dash-mtext">';
            }
            $html .= __($item['title']) . '</span>';

            if ($hasChildren) {
                $html .= '<span class="dash-arrow"> <i data-feather="chevron-right"></i> </span> </a>';
                $html .= '<ul class="dash-submenu">';
                $html .= generateSubMenu($menuItems, $item['name']);
                $html .= '</ul>';
            } else {
                $html .= '</a>';
            }

            $html .= '</li>';
        }
        return $html;
    }

}

if (!function_exists('categoryIcon')) {

    function categoryIcon() {
        $categoryIcon = [
            'General' => 'indent-increase',
            'Addon Manager' => 'apps',
            'Finance' => 'chart-dots',
            'HR' => 'users',
            'Sales' => 'businessplan',
            'eCommerce' => 'shopping-cart',
            'Education' => 'school',
            'Operations' => 'stack-2',
            'Productivity' => 'list-check',
            'Communication' => 'messages',
            'Medical' => 'ambulance',
            'Vehicle' => 'bike',
            'AI' => 'brand-gitlab',
            'Settings' => 'settings',
        ];

        return $categoryIcon;
    }

}

if (!function_exists('hasChildren')) {

    function hasChildren($menuItems, $name) {
        foreach ($menuItems as $item) {
            if ($item['parent'] === $name) {
                return true;
            }
        }
        return false;
    }

}


if (!function_exists('getSettingMenu')) {

    function getSettingMenu() {
        $user = auth()->user();
        $role = $user->roles->first();
        $menu = new \App\Classes\Menu($user);
        if ($role->name == 'super admin') {
            event(new \App\Events\SuperAdminSettingMenuEvent($menu));
        } else {
            // Admin and Company users get the same settings menu
            event(new \App\Events\CompanySettingMenuEvent($menu));
        }

        return generateSettingMenu($menu->menu);
    }

}


if (!function_exists('generateSettingMenu')) {

    function generateSettingMenu($menuItems) {
        usort($menuItems, function ($a, $b) {
            return $a['order'] - $b['order'];
        });

        $html = '';
        foreach ($menuItems as $menu) {
            $method = isset($menu['method']) ? $menu['method'] : null;
            $html .= '<a href="#' . $menu['navigation'] . '" data-module="' . $menu['module'] . '" data-method="' . $method . '"  class="list-group-item list-group-item-action setting-menu-nav">' . $menu['title'] . '<div class="float-end"><i class="ti ti-chevron-right"></i></div></a>';
        }
        return $html;
    }

}
if (!function_exists('getSettings')) {

    function getSettings() {
        $user = auth()->user();
        $role = $user->roles->first();
        if ($role->name == 'super admin') {
            $settings = getAdminAllSetting();
            $html = new \App\Classes\Setting($user, $settings);
            event(new \App\Events\SuperAdminSettingEvent($html));
        } else {
            // Admin and Company users get the same settings
            $settings = getCompanyAllSetting();
            $html = new \App\Classes\Setting($user, $settings);
            event(new \App\Events\CompanySettingEvent($html));
        }
        return generateSettings($html->html);
    }

}
if (!function_exists('generateSettings')) {

    function generateSettings($settingItems) {
        usort($settingItems, function ($a, $b) {
            return $a['order'] - $b['order'];
        });

        $html = '';
        foreach ($settingItems as $setting) {
            $html .= $setting['html'];
        }
        return $html;
    }

}

if (!function_exists('getAdminAllSetting')) {

    function getAdminAllSetting() {
        // Laravel cache - but skip cache if testing/debugging
        try {
            $super_admin = User::where('type', 'super admin')->first();
            $settings = [];
            if ($super_admin) {
                // Use workspace 0 for super admin if active_workspace is not set
                $workspace = $super_admin->active_workspace ?? 0;
                $settings = Setting::where('created_by', $super_admin->id)->where('workspace', $workspace)->pluck('value', 'key')->toArray();
            }

            // Debug log
            // \Illuminate\Support\Facades\Log::info('getAdminAllSetting - Debug', [
            //     'super_admin_id' => $super_admin->id ?? null,
            //     'active_workspace' => $super_admin->active_workspace ?? 'NULL',
            //     'workspace_used' => $workspace ?? 0,
            //     'settings_count' => count($settings),
            // ]);

            return $settings;
        } catch (\Exception $e) {
            return [];
        }
    }

}

if (!function_exists('getCompanyAllSetting')) {

    function getCompanyAllSetting($user_id = null, $workspace = null) {



        // 1. Try to get user by ID
        if (!empty($user_id)) {
            $user = User::find($user_id);
        } else {
            $user = auth()->user();
        }

        // 4. If still no user → return empty settings
        if (!$user) {
            return [];
        }


        // 5. Determine workspace safely

        if ($user->type == 'super admin') {
            $workspace = 0;
        } else {
            $workspace = $workspace ?? ($user->active_workspace ?? null);
            // 6. If user is employee, use creator
            if (!in_array($user->type, ['company', 'super admin'])) {
                $user = User::find($user->created_by);
            }

            if (!$workspace) {
                return [];
            }
        }




        if (!$user) {
            return [];
        }






//        dd($user);
        // 7. Cache key
        $key = 'company_settings_' . $workspace . '_' . $user->id;

        return Cache::rememberForever($key, function () use ($user, $workspace) {
                    return Setting::where('created_by', $user->id)
                                    ->where('workspace', $workspace)
                                    ->pluck('value', 'key')
                                    ->toArray();
                });
    }

}


if (!function_exists('admin_setting')) {

    function admin_setting($key) {
        if ($key) {
            $admin_settings = getAdminAllSetting();
            $setting = (array_key_exists($key, $admin_settings)) ? $admin_settings[$key] : null;
            return $setting;
        }
    }

}

if (!function_exists('company_setting')) {

    function company_setting($key, $user_id = null, $workspace = null) {
        if ($key) {
            $company_settings = getCompanyAllSetting($user_id, $workspace);
            $setting = null;
            if (!empty($company_settings)) {
                $setting = (array_key_exists($key, $company_settings)) ? $company_settings[$key] : null;
            }
            return $setting;
        }
    }

}

if (!function_exists('AdminSettingCacheForget')) {

    function AdminSettingCacheForget() {
        try {
            Cache::forget('admin_settings');
        } catch (\Exception $e) {
            // \Log::error('AdminSettingCacheForget :' . $e->getMessage());
        }
    }

}

if (!function_exists('comapnySettingCacheForget')) {

    function comapnySettingCacheForget($user_id = null, $workspace = null) {
        try {
            if (empty($user_id)) {
                $user_id = creatorId();
            }
            if (empty($workspace)) {
                $workspace = getActiveWorkSpace();
            }
            $key = 'company_settings_' . $workspace . '_' . $user_id;
            Cache::forget($key);
        } catch (\Exception $e) {
            // \Log::error('comapnySettingCacheForget :' . $e->getMessage());
        }
    }

}

if (!function_exists('sideMenuCacheForget')) {

    function sideMenuCacheForget($type = null, $user_id = null) {
        if ($type == 'all') {
            Cache::flush();
        }

        if (!empty($user_id)) {
            $user = User::find($user_id);
        } else {
            $user = auth()->user();
        }

        if ($user->isAbleTo('workspace manage')) {
            $users = User::select('id')->where('created_by', $user->id)->pluck('id');
            foreach ($users as $id) {
                try {
                    $key = 'sidebar_menu_' . $id;
                    Cache::forget($key);
                } catch (\Exception $e) {
                    // \Log::error('comapnySettingCacheForget :' . $e->getMessage());
                }
            }
            try {
                $key = 'sidebar_menu_' . $user->id;
                Cache::forget($key);
            } catch (\Exception $e) {
                // \Log::error('comapnySettingCacheForget :' . $e->getMessage());
            }
            return true;
        }

        try {
            $key = 'sidebar_menu_' . $user->id;
            Cache::forget($key);
        } catch (\Exception $e) {
            // \Log::error('comapnySettingCacheForget :' . $e->getMessage());
        }

        return true;
    }

}

if (!function_exists('getActiveWorkSpace')) {

    function getActiveWorkSpace($user_id = null) {

        if (!empty($user_id)) {
            $user = User::find($user_id);
        } else {
            $user = auth()->user();
        }

        if ($user) {
            if (!empty($user->active_workspace)) {
                return $user->active_workspace;
            } else {
                if ($user->type == 'super admin') {
                    return 0;
                } else {
                    static $WorkSpace = null;
                    if ($WorkSpace == null) {
                        $workspace = WorkSpace::where('created_by', $user->id)->first();
                    }

                    return $workspace->id;
                }
            }
        }
    }

}

if (!function_exists('getFilteredBySite')) {

    function getFilteredBySite($model, $site_id = null, $workspace_id = null, $created_by = null) {


//        dd($site_id);
        // 1. Detect user (JWT or Web)
        $user = auth()->user();

        if (!$user) {
            return collect(); // return empty collection
        }

        // 2. Determine creator
        if (!$created_by) {
            $created_by = ($user->isAbleTo('workspace manage')) ? $user->id : $user->created_by;
        }

        // 3. Determine workspace
        if (!$workspace_id) {
            $workspace_id = $user->active_workspace ?? null;
        }

        // 4. Build query
        $query = $model::query()
                ->when($created_by, fn($q) => $q->where('created_by', $created_by))
                ->when($workspace_id, fn($q) => $q->where('workspace_id', $workspace_id))
                ->when($site_id, fn($q) => $q->where('site_id', $site_id));

        return $query->get();
    }

}


if (!function_exists('getActiveProjectEmployees')) {

    function getActiveProjectEmployees() {
        return User::select('users.*')
                        ->join('user_projects', 'user_projects.user_id', '=', 'users.id')
                        ->where('user_projects.project_id', getActiveProject())
                        ->whereNotIn('users.type', ['super admin', 'company'])
                        ->get()
                        ->pluck('name', 'id');
    }

}


if (!function_exists('getActiveProject')) {

    function getActiveProject($user_id = null) {


        // First check session for faster access (especially after project switch)
        if (session()->has('active_project')) {
            return session('active_project');
        }

        if (!empty($user_id)) {
            $user = User::find($user_id);
        } else {
            $user = auth()->user();
        }

        if ($user) {
            // If user has an active project set
            if (!empty($user->active_project)) {
                // Sync to session for future calls
                session(['active_project' => $user->active_project]);
                return $user->active_project;
            } else {
                // For super admin, return 0 (or null depending on your logic)
                if ($user->type == 'super admin') {
                    return 0;
                } else {
                    // Fallback: get the first project created by this user
                    $project = Project::where('created_by', $user->id)->first();
                    $projectId = $project ? $project->id : null;
                    // Store in session for future calls
                    if ($projectId) {
                        session(['active_project' => $projectId]);
                    }
                    return $projectId;
                }
            }
        }

        return null; // if no user found
    }

}

if (!function_exists('setActiveProject')) {

    /**
     * Set the active project for the authenticated user.
     * Updates both database and session for immediate effect.
     *
     * @param  int  $project_id
     * @return void
     */
    function setActiveProject($project_id) {
        // Always update session first for immediate effect
        session(['active_project' => $project_id]);

        // Also update database
        $user = auth()->user();
        if ($user) {
            $user->active_project = $project_id;
            $user->save();
        }
    }

}

if (!function_exists('getActiveProjectName')) {

    /**
     * Get the active project's name for a user.
     *
     * @param  int|null  $user_id
     * @return string|null
     */
    function getActiveProjectName($user_id = null) {
        // Get user
        $user = !empty($user_id) ? User::find($user_id) : auth()->user();

        if ($user) {
            // If user has an active_project set
            if (!empty($user->active_project)) {
//               dd('if');
                $project = Project::find($user->active_project);
                return $project ? $project->name : null;
            } else {

//                dd('else');
                // Super admin fallback
                if ($user->type === 'super admin') {
                    return __('No Active Project');
                } else {
                    // Fallback: first project created by user
                    $project = Project::where('created_by', $user->id)->where('workspace', $user->active_workspace)->first();
//                     dd($project);
                    //dd($project);
                    return $project ? $project->name : 'Select Site / Project';

//                    return 'Select Site / Project';
                }
            }
        }

        return null;
    }

}


if (!function_exists('getProject')) {

    function getProject() {
        if (!Auth::check()) {
            return collect();
        }

        $user = Auth::user();
        
        // Use user's active_workspace directly - more reliable than getActiveWorkSpace()
        $workspaceId = $user->active_workspace;

        // COMPREHENSIVE DEBUG LOGGING
        // \Log::info('getProject FULL DEBUG', [
        //     'user_id' => $user->id,
        //     'user_email' => $user->email,
        //     'user_active_workspace' => $user->active_workspace,
        //     'getActiveWorkSpace_result' => getActiveWorkSpace(),
        //     'user_project_ids_raw' => UserProject::where('user_id', $user->id)->pluck('project_id')->toArray(),
        //     'projects_4_5_raw' => Project::whereIn('id', [4, 5])->select('id', 'workspace', 'created_by')->get()->toArray(),
        // ]);

        // Get project IDs from user_projects mapping
        $userProjectIds = UserProject::where('user_id', $user->id)
            ->pluck('project_id');

        // Get projects where user is creator (fallback)
        $createdProjectIds = Project::where('created_by', $user->id)
            ->pluck('id');

        // Merge both and get unique IDs
        $allProjectIds = $userProjectIds->merge($createdProjectIds)->unique();

        // \Log::info('getProject IDs', [
        //     'user_project_ids' => $userProjectIds->toArray(),
        //     'created_project_ids' => $createdProjectIds->toArray(),
        //     'merged_ids' => $allProjectIds->toArray(),
        // ]);

        $projects = Project::where('workspace', $workspaceId)
            ->whereIn('id', $allProjectIds)
            ->get();

        // Debug logging
        // \Log::info('getProject result', [
        //     'count' => $projects->count(),
        //     'project_ids' => $projects->pluck('id')->toArray(),
        //     'query_workspace' => $workspaceId,
        // ]);

        return $projects;
    }

}


//if (!function_exists('getProject')) {
//
//    function getProject()
//    {
//        $data = [];
//        if (Auth::check()) {
//            // Cache user lookup
//            static $users = null;
//            if ($users === null) {
//                $users = User::where('email', Auth::user()->email)->get();
//            }
//
//            // Cache projects
//            static $Projects = null;
//            if ($Projects === null) {
//                // Collect user IDs
//                $userIds = $users->pluck('id')->toArray();
//
//                
//                
//                // Collect workspace IDs
////                $workspaceIds = $users->pluck('workspace_id')->toArray();
//                
//                $workspaceIds[] =getActiveWorkSpace();
//                
//// var_dump($workspaceIds);
//                // Build query: projects either created by these users OR belonging to their workspaces
//                $Projects = Project::whereIn('workspace', $workspaceIds)
////                    ->orWhereIn('created_by', $userIds)
//                    ->get();
//                
//                
//                // Get project IDs for this user
//            $projectIds = UserProject::where('user_id', $user->id)
//            ->pluck('project_id');
//            }
//
////            dd($Projects);
//            
//            return $Projects;
//        } else {
//            return $data;
//        }
//    }
//}

if (!function_exists('changeProject')) {

    /**
     * Change the active project for the authenticated user.
     *
     * @param  int  $project_id
     * @return bool|array  Returns false if project not found, or array with project status info
     */
    function changeProject($project_id) {
        $project = Project::find($project_id);

        if (!$project) {
            return false; // project not found
        }

        // Check if project status is not 'Ongoing'
        if ($project->status != 'Ongoing') {
            return [
                'locked' => true,
                'project' => $project
            ];
        }

        $user = Auth::user();

        if ($user) {
            // Update active_project field in database
            $user->active_project = $project_id;
            $user->save();

            // Store in session for immediate/faster access
            session(['active_project' => $project_id]);

            // Refresh the authenticated user to pick up the new active_project
            Auth::setUser($user);

            return [
                'locked' => false,
                'project' => $project
            ];
        }

        return false;
    }

}

if (!function_exists('getWorkspace')) {

    function getWorkspace() {
        if (!Auth::check()) {
            return collect([]);
        }

        $user = Auth::user();

        // Get project IDs for this user
        $projectIds = UserProject::where('user_id', $user->id)
                ->pluck('project_id');

        // Get workspace IDs from projects
        $workspaceIds = Project::whereIn('id', $projectIds)
                ->pluck('workspace');

        // Fetch workspaces either linked to projects or created by the user
        return WorkSpace::whereIn('id', $workspaceIds)
                        ->orWhere('created_by', $user->id)
                        ->get();
    }

}

if (!function_exists('getCompanyOwnerId')) {

    function getCompanyOwnerId() {

        // Attempt to get the user from JWT
        $user = Auth::user();

        // If JWTAuth user not found, fall back to Auth for web-based session
        if (!$user) {
            $user = Auth::user();
        }

        // If no user is found (either from JWT or session), return Unauthorized
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        if ($user->isAbleTo('workspace manage')) {

            return $user->id;
        } else {

            $user = User::where('type', 'company')->first();

            return $user->id;
        }
    }

}


if (!function_exists('creatorId')) {

    function creatorId() {
        // Attempt to get the user from JWT
        $user = Auth::user();

        // If JWTAuth user not found, fall back to Auth for web-based session
        if (!$user) {
            $user = Auth::user();
        }

        // If no user is found (either from JWT or session), return Unauthorized
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // If the user type is super admin or company, return the user's ID
        // Otherwise, return the user ID that created the user (e.g., `created_by` field)

        return ($user->isAbleTo('workspace manage')) ? $user->id : $user->id;
//        return ($user->type === 'super admin' || $user->type === 'company') ? $user->id : $user->created_by;
    }

}


//if (!function_exists('creatorId')) {
//
//    function creatorId() {
//        
//        
//        if ($request->wantsJson()) {
//            // For API requests (JWT-based)
//            $user = JWTAuth::user();
//            if (!$user) {
//                return response()->json(['message' => 'Unauthorized'], 401);
//            }
//            $userId = $user->id;
//            $userName = $user->name;
//        } else {
//            // For web requests (session-based)
//            if (Auth::check()) {
//                $userId = Auth::user()->id;
//                $userName = Auth::user()->name;
//                $user = Auth::user();
//            } else {
//                return response()->json(['message' => 'User not authenticated'], 401);
//            }
//        }
//
//        if (!$user) {
//            throw new \Exception('No authenticated user found for creatorId()');
//        }
//
//        return ($user->type === 'super admin' || $user->type === 'company') ? $user->id : $user->created_by;
//    }
//
//}

if (!function_exists('getModuleList')) {

    function getModuleList() {
        $all = Module::getOrdered();
        $list = [];
        foreach ($all as $module) {
            array_push($list, $module->name);
        }
        return $list;
    }

}

if (!function_exists('getshowModuleList')) {

    function getshowModuleList() {
        $all = Module::getOrdered();
        $list = [];
        foreach ($all as $module) {
            if ($module->display) {
                array_push($list, $module->name);
            }
        }
        return $list;
    }

}

//if (!function_exists('module_is_active')) {
//
//    function module_is_active($module, $user_id = null) {
//        return true;
//    }
//
//
//}


if (!function_exists('module_is_active')) {

    function module_is_active($module, $user_id = null) {
        // Always allow 'General'
        if ($module == 'General') {
            return true;
        }

        // Check if module is in getSystemActiveModule() - this is the master list
        $systemModules = getSystemActiveModule();
        if (in_array($module, $systemModules)) {
            return true;
        }

        // Also check Laravel modules (original logic)
        if (Module::has($module)) {
            $isModuleActive = Module::isEnabled($module);
            if ($isModuleActive == false) {
                return false;
            }
            if (Auth::check()) {
                $user = Auth::user();
            } else {
                $user = User::find($user_id);
            }
            if (!empty($user)) {

                if ($user->type == 'super admin') {
                    return true;
                } else {
                    $active_module = ActivatedModule($user->id);
                    if ((count($active_module) > 0 && in_array($module, $active_module))) {
                        return true;
                    }
                    return false;
                }
            }
            return true;
        }

        return false;
    }

}
if (!function_exists('ActivatedModule')) {

    function ActivatedModule($user_id = null) {
        $activated_module = user::$superadmin_activated_module;

        if ($user_id != null) {
            $user = User::find($user_id);
        } elseif (Auth::check()) {
            $user = Auth::user();
        }
        if (!empty($user)) {
            $available_modules = array_values(Module::allEnabled());

            if ($user->type == 'super admin') {
                $user_active_module = $available_modules;
            } else {
                static $active_module = null;
                if ($user->type != 'company') {
                    $user_not_com = User::find($user->created_by);
                    if (!empty($user)) {
                        // Sidebar Performance Changes

                        if ($active_module == null) {
                            $active_module = userActiveModule::where('user_id', $user_not_com->id)->pluck('module')->toArray();
                        }
                    }
                } else {
                    if ($active_module == null) {
                        $active_module = userActiveModule::where('user_id', $user->id)->pluck('module')->toArray();
                    }
                }

                // Find the common modules
                $commonModules = array_intersect($active_module, $available_modules);
//                $user_active_module = array_unique(array_merge($commonModules, $activated_module));

                $user_active_module = $available_modules; // login check added
            }
        }
        return $user_active_module;
    }

}
// // module alias name
if (!function_exists('Module_Alias_Name')) {

    function Module_Alias_Name($module_name) {
        static $addons = [];
        static $resultArray = [];
        if (count($addons) == 0 && count($resultArray) == 0) {
            $addons = Module::all();
            $resultArray = array_reduce($addons, function ($carry, $item) {
                // Check if both "name" and "alias" keys exist in the current item
                if (isset($item->name) && isset($item->alias)) {
                    // Add a new key-value pair to the result array
                    $carry[$item->name] = $item->alias;
                }
                return $carry;
            }, []);
        }

        if ($module_name === 'general' || $module_name === 'General') {
            return $module_name;
        }
        $module = Module::find($module_name);
        if (isset($resultArray)) {
            $module_name = array_key_exists($module_name, $resultArray) ? $resultArray[$module_name] : (!empty($module) ? $module->alias : $module_name);
        } elseif (!empty($module)) {
            $module_name = $module->alias;
        }
        return $module_name;
    }

}

if (!function_exists('get_permission_by_module')) {

    function get_permission_by_module($mudule) {
        $user = Auth::user();

        if ($user->type == 'super admin') {
            $permissions = Permission::where('module', $mudule)->where('guard_name', 'web')->orderBy('name')->get();
        } else if ($user->isAbleTo('workspace manage')) {
            $permissions = Permission::where('module', $mudule)->where('guard_name', 'web')->orderBy('name')->get();
        } else {
            $permissions = new Collection();
            foreach ($user->roles as $role) {
                $permissions = $permissions->merge($role->permissions);
            }
            $permissions = $permissions->where('module', $mudule);
        }

        return $permissions;
    }

}

if (!function_exists('getActiveLanguage')) {

    function getActiveLanguage() {
        if ((Auth::check()) && (!empty(Auth::user()->lang))) {
            return Auth::user()->lang;
        } else {
            $admin_settings = getAdminAllSetting();
            return !empty($admin_settings['defult_language']) ? $admin_settings['defult_language'] : 'en';
        }
    }

}

if (!function_exists('languages')) {

    function languages() {

        try {
            $arrLang = Language::where('status', 1)->get()->pluck('name', 'code')->toArray();
        } catch (\Throwable $th) {
            $arrLang = [
                "ar" => "Arabic",
                "da" => "Danish",
                "de" => "German",
                "en" => "English",
                "es" => "Spanish",
                "fr" => "French",
                "it" => "Italian",
                "ja" => "Japanese",
                "nl" => "Dutch",
                "pl" => "Polish",
                "pt" => "Portuguese",
                "ru" => "Russian",
                "tr" => "Turkish"
            ];
        }
        return $arrLang;
    }

}


// setConfigEmail ( SMTP )
if (!function_exists('SetConfigEmail')) {

    function SetConfigEmail($user_id = null, $workspace_id = null) {
        try {

            if (!empty($user_id)) {
                $company_settings = getCompanyAllSetting($user_id);
            } elseif (!empty($user_id) && !empty($workspace_id)) {
                $company_settings = getCompanyAllSetting($user_id, $workspace_id);
            } else if (Auth::check()) {
                $company_settings = getCompanyAllSetting();
            } else {
                $user_id = User::where('type', 'super admin')->first()->id;
                $company_settings = getCompanyAllSetting($user_id);
            }

            // DEBUG: Log SetConfigEmail parameters
            // \Illuminate\Support\Facades\Log::info('SetConfigEmail - Debug Info', [
            //     'user_id' => $user_id,
            //     'workspace_id' => $workspace_id,
            //     'company_settings_empty' => empty($company_settings),
            //     'company_settings_keys' => array_keys($company_settings),
            //     'has_mail_driver' => isset($company_settings['mail_driver']),
            //     'has_mail_host' => isset($company_settings['mail_host']),
            //     'has_mail_port' => isset($company_settings['mail_port']),
            // ]);
            // Check if required mail settings exist
            if (empty($company_settings) || !isset($company_settings['mail_driver'])) {
                // \Illuminate\Support\Facades\Log::error('SetConfigEmail - FAILED: Mail configuration not set!', [
                //     'company_settings' => $company_settings,
                // ]);
                return false;
            }

            config(
                    [
                        'mail.driver' => $company_settings['mail_driver'],
                        'mail.host' => $company_settings['mail_host'],
                        'mail.port' => $company_settings['mail_port'],
                        'mail.encryption' => $company_settings['mail_encryption'],
                        'mail.username' => $company_settings['mail_username'],
                        'mail.password' => $company_settings['mail_password'],
                        'mail.from.address' => $company_settings['mail_from_address'],
                        'mail.from.name' => $company_settings['mail_from_name'],
                    ]
            );
            return true;
        } catch (\Exception $e) {
            // \Illuminate\Support\Facades\Log::error('SetConfigEmail - EXCEPTION: ' . $e->getMessage());
            return false;
        }
    }

}

// file upload

if (!function_exists('upload_file')) {

    function upload_file($request, $key_name, $name, $path, $custom_validation = []) {
        try {
            $storage_settings = getAdminAllSetting();

            // DEBUG: Log storage settings
            // \Illuminate\Support\Facades\Log::info('upload_file - Storage Settings Debug', [
            //     'storage_settings_keys' => array_keys($storage_settings ?? []),
            //     'has_storage_setting' => isset($storage_settings['storage_setting']),
            //     'storage_setting_value' => $storage_settings['storage_setting'] ?? 'NOT SET',
            // ]);
            // Default to local storage if not configured
            $storage_setting = $storage_settings['storage_setting'] ?? 'local';

            if (!empty($storage_setting)) {
                if ($storage_setting == 'wasabi') {
                    config(
                            [
                                'filesystems.disks.wasabi.key' => $storage_settings['wasabi_key'],
                                'filesystems.disks.wasabi.secret' => $storage_settings['wasabi_secret'],
                                'filesystems.disks.wasabi.region' => $storage_settings['wasabi_region'],
                                'filesystems.disks.wasabi.bucket' => $storage_settings['wasabi_bucket'],
                                'filesystems.disks.wasabi.root' => $storage_settings['wasabi_root'],
                                'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url']
                            ]
                    );
                    $max_size = !empty($storage_settings['wasabi_max_upload_size']) ? $storage_settings['wasabi_max_upload_size'] : '2048';
                    $mimes = !empty($storage_settings['wasabi_storage_validation']) ? $storage_settings['wasabi_storage_validation'] : 'jpeg,jpg,png,svg,zip,txt,gif,docx';
                } else if ($storage_setting == 's3') {
                    config(
                            [
                                'filesystems.disks.s3.key' => $storage_settings['s3_key'] ?? '',
                                'filesystems.disks.s3.secret' => $storage_settings['s3_secret'] ?? '',
                                'filesystems.disks.s3.region' => $storage_settings['s3_region'] ?? '',
                                'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'] ?? '',
                            // 'filesystems.disks.s3.url' => $storage_settings['s3_url'],
                            // 'filesystems.disks.s3.endpoint' => $storage_settings['s3_endpoint'],
                            ]
                    );
                    $max_size = !empty($storage_settings['s3_max_upload_size']) ? $storage_settings['s3_max_upload_size'] : '2048';
                    $mimes = !empty($storage_settings['s3_storage_validation']) ? $storage_settings['s3_storage_validation'] : 'jpeg,jpg,png,svg,zip,txt,gif,docx';
                } else {
                    // Local storage (default when storage_setting is not configured)
                    $max_size = !empty($storage_settings['local_storage_max_upload_size']) ? $storage_settings['local_storage_max_upload_size'] : '204800';
                    $mimes = !empty($storage_settings['local_storage_validation']) ? $storage_settings['local_storage_validation'] : 'jpeg,jpg,png,svg,zip,txt,gif,docx,doc,pdf,ppt,pptx';
                }
                $file = $request->$key_name;
                if (count($custom_validation) > 0) {
                    $validation = $custom_validation;
                } else {
                    $validation = [
                        'mimes:' . $mimes,
                        'max:' . $max_size,
                    ];
                }
                $validator = Validator::make($request->all(), [
                    $key_name => $validation
                ]);
                if ($validator->fails()) {
                    $res = [
                        'flag' => 0,
                        'msg' => $validator->messages()->first(),
                    ];
                    return $res;
                } else {
                    $name = $name;

//                    $path='uploads/' .$path;
                    // Use $storage_setting variable instead of direct array access
                    $diskType = $storage_setting ?? 'local';

                    // Save to both public_folder (for web access via asset) and local (for Storage::disk access)
                    // This ensures images work regardless of which method is used to access them
                    if ($diskType == 's3' || $diskType == 'wasabi') {
                        // For cloud storage, save to cloud
                        $save = Storage::disk($diskType)->putFileAs(
                                $path,
                                $file,
                                $name
                        );
                        $url = $save;
                    } else {
                        // For local storage, save to BOTH public/uploads AND project root/uploads
                        // This ensures compatibility with both asset() and Storage::disk() methods
                        // Save to public/uploads (for web access via asset())
                        $publicPath = 'uploads/' . $path;
                        Storage::disk('public_folder')->putFileAs($path, $file, $name);

                        // Save to project root/uploads (for Storage::disk('local'))
                        Storage::disk('local')->putFileAs($path, $file, $name);

                        $save = $name;
                        $url = 'uploads/' . $path . '/' . $name;
                    }
                    $res = [
                        'flag' => 1,
                        'msg' => 'success',
                        'url' => $url
                    ];
                    return $res;
                }
            } else {
                // This else block should never execute now since we default to local storage
                // But keep it for safety
                $res = [
                    'flag' => 0,
                    'msg' => 'not set configurations',
                ];
                return $res;
            }
        } catch (\Exception $e) {
            $res = [
                'flag' => 0,
                'msg' => $e->getMessage(),
            ];
            return $res;
        }
    }

}




if (!function_exists('multi_upload_file')) {

    function multi_upload_file($request, $key_name, $name, $path, $custom_validation = []) {
        try {
            $storage_settings = getAdminAllSetting();

            // Default to local storage if not configured
            $storage_setting = $storage_settings['storage_setting'] ?? 'local';

            if (!empty($storage_setting)) {
                if ($storage_setting == 'wasabi') {
                    config(
                            [
                                'filesystems.disks.wasabi.key' => $storage_settings['wasabi_key'],
                                'filesystems.disks.wasabi.secret' => $storage_settings['wasabi_secret'],
                                'filesystems.disks.wasabi.region' => $storage_settings['wasabi_region'],
                                'filesystems.disks.wasabi.bucket' => $storage_settings['wasabi_bucket'],
                                'filesystems.disks.wasabi.root' => $storage_settings['wasabi_root'],
                                'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url']
                            ]
                    );
                    $max_size = !empty($storage_settings['wasabi_max_upload_size']) ? $storage_settings['wasabi_max_upload_size'] : '2048';
                    $mimes = !empty($storage_settings['wasabi_storage_validation']) ? $storage_settings['wasabi_storage_validation'] : 'jpeg,jpg,png,svg,zip,txt,gif,docx';
                } else if ($storage_setting == 's3') {
                    config(
                            [
                                'filesystems.disks.s3.key' => $storage_settings['s3_key'] ?? '',
                                'filesystems.disks.s3.secret' => $storage_settings['s3_secret'] ?? '',
                                'filesystems.disks.s3.region' => $storage_settings['s3_region'] ?? '',
                                'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'] ?? '',
                            // 'filesystems.disks.s3.url' => $storage_settings['s3_url'],
                            // 'filesystems.disks.s3.endpoint' => $storage_settings['s3_endpoint'],
                            ]
                    );
                    $max_size = !empty($storage_settings['s3_max_upload_size']) ? $storage_settings['s3_max_upload_size'] : '2048';
                    $mimes = !empty($storage_settings['s3_storage_validation']) ? $storage_settings['s3_storage_validation'] : 'jpeg,jpg,png,svg,zip,txt,gif,docx';
                } else {
                    // Local storage (default)
                    $max_size = !empty($storage_settings['local_storage_max_upload_size']) ? $storage_settings['local_storage_max_upload_size'] : '2048';
                    $mimes = !empty($storage_settings['local_storage_validation']) ? $storage_settings['local_storage_validation'] : 'jpeg,jpg,png,svg,zip,txt,gif,docx,doc,pdf,ppt,pptx';
                }

                $file = $request;
                $key_validation = $key_name . '*';
                if (count($custom_validation) > 0) {
                    $validation = $custom_validation;
                } else {
                    $validation = [
                        'mimes:' . $mimes,
                        'max:' . $max_size,
                    ];
                }
                $validator = Validator::make(array($key_name => $request), [
                    $key_validation => $validation
                ]);
                if ($validator->fails()) {
                    $res = [
                        'flag' => 0,
                        'msg' => $validator->messages()->first(),
                    ];
                    return $res;
                } else {

                    $name = $name;

//                    $path='uploads/' .$path;
                    // Use $storage_setting variable instead of direct array access
                    $diskType = $storage_setting ?? 'local';

                    // Save to both public_folder (for web access via asset) and local (for Storage::disk access)
                    if ($diskType == 's3' || $diskType == 'wasabi') {
                        // For cloud storage, save to cloud
                        $save = Storage::disk($diskType)->putFileAs(
                                $path,
                                $file,
                                $name
                        );
                        $url = $save;
                    } else {
                        // For local storage, save to BOTH public/uploads AND project root/uploads
                        // Save to public/uploads (for web access via asset())
                        Storage::disk('public_folder')->putFileAs($path, $file, $name);

                        // Save to project root/uploads (for Storage::disk('local'))
                        Storage::disk('local')->putFileAs($path, $file, $name);

                        $save = $name;
                        $url = 'uploads/' . $path . '/' . $name;
                    }
                    $res = [
                        'flag' => 1,
                        'msg' => 'success',
                        'url' => $url
                    ];
                    return $res;
                }
            } else {
                $res = [
                    'flag' => 0,
                    'msg' => 'not set configration',
                ];
                return $res;
            }
        } catch (\Exception $e) {
            $res = [
                'flag' => 0,
                'msg' => $e->getMessage(),
            ];
            return $res;
        }
    }

}

if (!function_exists('check_file')) {

    function check_file($path) {

        if (!empty($path)) {

            $storage_settings = getAdminAllSetting();
            $storage_setting = $storage_settings['storage_setting'] ?? null;

            if ($storage_setting == null || $storage_setting == 'local') {

                return file_exists(base_path($path));
            } else {

                if (isset($storage_settings['storage_setting']) && $storage_settings['storage_setting'] == 's3') {
                    config(
                            [
                                'filesystems.disks.s3.key' => $storage_settings['s3_key'],
                                'filesystems.disks.s3.secret' => $storage_settings['s3_secret'],
                                'filesystems.disks.s3.region' => $storage_settings['s3_region'],
                                'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'],
                            // 'filesystems.disks.s3.url' => $storage_settings['s3_url'],
                            // 'filesystems.disks.s3.endpoint' => $storage_settings['s3_endpoint'],
                            ]
                    );
                } else if (isset($storage_settings['storage_setting']) && $storage_settings['storage_setting'] == 'wasabi') {
                    config(
                            [
                                'filesystems.disks.wasabi.key' => $storage_settings['wasabi_key'],
                                'filesystems.disks.wasabi.secret' => $storage_settings['wasabi_secret'],
                                'filesystems.disks.wasabi.region' => $storage_settings['wasabi_region'],
                                'filesystems.disks.wasabi.bucket' => $storage_settings['wasabi_bucket'],
                                'filesystems.disks.wasabi.root' => $storage_settings['wasabi_root'],
                                'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url']
                            ]
                    );
                }
                try {

//                    dd($path);
//                     dd($storage_settings['storage_setting']);
//                     dd(Storage::disk($storage_settings['storage_setting'])->exists($path));
//                      return Storage::disk($storage_settings['storage_setting'])->exists($path);

                    return true;
                } catch (\Throwable $th) {
                    return 0;
                }
            }
        } else {
            return 0;
        }
    }

}

if (!function_exists('get_file')) {

    function get_file($path) {

        // Debug: Log the path being requested
        // \Illuminate\Support\Facades\Log::info('get_file called with path: ' . $path);
        // If path is empty or null, return default
        if (empty($path)) {
            // \Illuminate\Support\Facades\Log::warning('get_file: Empty path provided, returning default avatar');
            return asset('uploads/users-avatar/avatar.png');
        }

        // FIX: Check if file exists in local storage before returning URL
        $storage_settings = getAdminAllSetting();
        $storage_setting = $storage_settings['storage_setting'] ?? null;

        // For local storage, verify file exists
        if ($storage_setting == 'local' || $storage_setting === null || $storage_setting == 'public') {
            // Check both possible locations: public/uploads and project root/uploads
            $fileExists = false;
            $checkedPaths = [];

            // Check in public/uploads (for asset() access)
            $publicPath = public_path($path);
            $checkedPaths[] = $publicPath;
            if (file_exists($publicPath)) {
                $fileExists = true;
            }

            // Also check in storage/app (for Storage::disk('local') access)
            $storagePath = storage_path('app/' . $path);
            $checkedPaths[] = $storagePath;
            if (file_exists($storagePath)) {
                $fileExists = true;
            }

            // \Illuminate\Support\Facades\Log::info('get_file: File existence check', [
            //     'path' => $path,
            //     'checked_paths' => $checkedPaths,
            //     'file_exists' => $fileExists,
            // ]);

            if (!$fileExists) {
                // \Illuminate\Support\Facades\Log::warning('get_file: File does not exist at any checked path!', [
                //     'path' => $path,
                //     'checked_paths' => $checkedPaths,
                // ]);
            }
        }

        // Re-fetch storage settings for actual URL generation
        $storage_settings = getAdminAllSetting();
        $storage_setting = $storage_settings['storage_setting'] ?? null;

        // Debug
        // \Illuminate\Support\Facades\Log::info('get_file - storage_setting: ' . ($storage_setting ?? 'null'));

        if ($storage_setting == 's3') {
            config([
                'filesystems.disks.s3.key' => $storage_settings['s3_key'] ?? '',
                'filesystems.disks.s3.secret' => $storage_settings['s3_secret'] ?? '',
                'filesystems.disks.s3.region' => $storage_settings['s3_region'] ?? '',
                'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'] ?? '',
                'filesystems.disks.s3.url' => $storage_settings['s3_url'] ?? '',
                'filesystems.disks.s3.endpoint' => $storage_settings['s3_endpoint'] ?? '',
            ]);
            return Storage::disk('s3')->url($path);
        } else if ($storage_setting == 'wasabi') {
            config([
                'filesystems.disks.wasabi.key' => $storage_settings['wasabi_key'] ?? '',
                'filesystems.disks.wasabi.secret' => $storage_settings['wasabi_secret'] ?? '',
                'filesystems.disks.wasabi.region' => $storage_settings['wasabi_region'] ?? '',
                'filesystems.disks.wasabi.bucket' => $storage_settings['wasabi_bucket'] ?? '',
                'filesystems.disks.wasabi.root' => $storage_settings['wasabi_root'] ?? '',
                'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url'] ?? '',
            ]);
            return Storage::disk('wasabi')->url($path);
        } else if ($storage_setting == 'public') {
            return Storage::disk('public')->url($path);

//            Storage::disk('public')->url($employee->avatar)
        } else if ($storage_setting == 'local' || $storage_setting === null) {
            // Use asset() for local files in public/uploads folder
            // Don't modify the path - asset() will correctly resolve it
            return asset($path);
        } else if ($storage_setting == 'public_folder') {
            return Storage::disk('public_folder')->url($path);
//            dd(Storage::disk('public_folder')->url($path));
        } else {
            // Fallback: just return asset path
            return asset($path);
        }
    }

}


if (!function_exists('get_base_file')) {

    function get_base_file($path) {
        $admin_settings = getAdminAllSetting();
        if (isset($storage_settings['storage_setting']) && $storage_settings['storage_setting'] == 's3') {
            config(
                    [
                        'filesystems.disks.s3.key' => $admin_settings['s3_key'],
                        'filesystems.disks.s3.secret' => $admin_settings['s3_secret'],
                        'filesystems.disks.s3.region' => $admin_settings['s3_region'],
                        'filesystems.disks.s3.bucket' => $admin_settings['s3_bucket'],
                    // 'filesystems.disks.s3.url' => $admin_settings['s3_url'],
                    // 'filesystems.disks.s3.endpoint' => $admin_settings['s3_endpoint'],
                    ]
            );

            return Storage::disk('s3')->url($path);
        } else if (isset($storage_settings['storage_setting']) && $storage_settings['storage_setting'] == 'wasabi') {
            config(
                    [
                        'filesystems.disks.wasabi.key' => $admin_settings['wasabi_key'],
                        'filesystems.disks.wasabi.secret' => $admin_settings['wasabi_secret'],
                        'filesystems.disks.wasabi.region' => $admin_settings['wasabi_region'],
                        'filesystems.disks.wasabi.bucket' => $admin_settings['wasabi_bucket'],
                        'filesystems.disks.wasabi.root' => $admin_settings['wasabi_root'],
                        'filesystems.disks.wasabi.endpoint' => $admin_settings['wasabi_url']
                    ]
            );
            return Storage::disk('wasabi')->url($path);
        } else {
            return base_path($path);
        }
    }

}
if (!function_exists('delete_file')) {

    function delete_file($path) {
        if (check_file($path)) {
            $storage_settings = getAdminAllSetting();
            if (isset($storage_settings['storage_setting'])) {
                if ($storage_settings['storage_setting'] == 'local') {
                    $deleted = File::delete($path);
                    // Also delete from public/uploads
                    $publicPath = public_path($path);
                    if (file_exists($publicPath)) {
                        File::delete($publicPath);
                    }
                    return $deleted;
                } else {
                    if ($storage_settings['storage_setting'] == 's3') {
                        config(
                                [
                                    'filesystems.disks.s3.key' => $storage_settings['s3_key'],
                                    'filesystems.disks.s3.secret' => $storage_settings['s3_secret'],
                                    'filesystems.disks.s3.region' => $storage_settings['s3_region'],
                                    'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'],
                                // 'filesystems.disks.s3.url' => $storage_settings['s3_url'],
                                // 'filesystems.disks.s3.endpoint' => $storage_settings['s3_endpoint'],
                                ]
                        );
                    } else if ($storage_settings['storage_setting'] == 'wasabi') {
                        config(
                                [
                                    'filesystems.disks.wasabi.key' => $storage_settings['wasabi_key'],
                                    'filesystems.disks.wasabi.secret' => $storage_settings['wasabi_secret'],
                                    'filesystems.disks.wasabi.region' => $storage_settings['wasabi_region'],
                                    'filesystems.disks.wasabi.bucket' => $storage_settings['wasabi_bucket'],
                                    'filesystems.disks.wasabi.root' => $storage_settings['wasabi_root'],
                                    'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url']
                                ]
                        );

                        Storage::disk($storage_settings['local'])->delete($path);
                        // Also delete from public/uploads
                        $publicPath = public_path($path);
                        if (file_exists($publicPath)) {
                            File::delete($publicPath);
                        }

                        return Storage::disk($storage_settings['storage_setting'])->delete($path);
                    }
                }
            }
        }
    }

}

if (!function_exists('get_size')) {

    function get_size($url) {
        $url = str_replace(' ', '%20', $url);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE);

        $data = curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        curl_close($ch);
        return $size;
    }

}
if (!function_exists('delete_folder')) {

    function delete_folder($path) {
        $storage_settings = getAdminAllSetting();
        if (isset($storage_settings['storage_setting'])) {

            if ($storage_settings['storage_setting'] == 'local') {
                if (is_dir(Storage::path($path))) {
                    return \File::deleteDirectory(Storage::path($path));
                }
            } else {
                if ($storage_settings['storage_setting'] == 's3') {
                    config(
                            [
                                'filesystems.disks.s3.key' => $storage_settings['s3_key'],
                                'filesystems.disks.s3.secret' => $storage_settings['s3_secret'],
                                'filesystems.disks.s3.region' => $storage_settings['s3_region'],
                                'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'],
                            // 'filesystems.disks.s3.url' => $storage_settings['s3_url'],
                            // 'filesystems.disks.s3.endpoint' => $storage_settings['s3_endpoint'],
                            ]
                    );
                } else if ($storage_settings['storage_setting'] == 'wasabi') {
                    config(
                            [
                                'filesystems.disks.wasabi.key' => $storage_settings['wasabi_key'],
                                'filesystems.disks.wasabi.secret' => $storage_settings['wasabi_secret'],
                                'filesystems.disks.wasabi.region' => $storage_settings['wasabi_region'],
                                'filesystems.disks.wasabi.bucket' => $storage_settings['wasabi_bucket'],
                                'filesystems.disks.wasabi.root' => $storage_settings['wasabi_root'],
                                'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url']
                            ]
                    );
                }
                return Storage::disk($storage_settings['storage_setting'])->deleteDirectory($path);
            }
        }
    }

}
if (!function_exists('delete_directory')) {

    function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

}
if (!function_exists('currency')) {

    function currency($code = null) {
        if ($code == null) {
            $c = Currency::get();
        } else {
            $c = Currency::where('code', $code)->first();
        }
        return $c;
    }

}

// Company Subscription Details
if (!function_exists('SubscriptionDetails')) {

    function SubscriptionDetails($user_id = null) {
        $data = [];
        $data['status'] = false;
        if ($user_id != null) {
            $user = User::find($user_id);
        } elseif (\Auth::check()) {
            $user = \Auth::user();
        }

        if (isset($user) && !empty($user)) {
            if ($user->type != 'company' && $user->type != 'super admin') {
                $user = User::find($user->created_by);
            }

            if (!empty($user)) {
                if ($user->active_plan != 0) {
                    $data['status'] = true;
                    $data['active_plan'] = $user->active_plan;
                    $data['billing_type'] = $user->billing_type;
                    $data['plan_expire_date'] = $user->plan_expire_date;
                    $data['active_module'] = ActivatedModule();
                    $data['total_user'] = $user->total_user == -1 ? 'Unlimited' : (isset($user->total_user) ? $user->total_user : 'Unlimited');
                    $data['total_workspace'] = $user->total_workspace == -1 ? 'Unlimited' : (isset($user->total_workspace) ? $user->total_workspace : 'Unlimited');
                    $data['seeder_run'] = $user->seeder_run;
                }
            }
        }
        return $data;
    }

}


if (!function_exists('PlanCheck')) {

    function PlanCheck($type = 'User', $id = null) {
        if (!empty($id)) {
            $user = User::where('id', $id)->first();
            if ($user->isAbleTo('workspace manage')) {
                $id = $user->id;
            } else {
                $user = User::where('id', $user->created_by)->first();
                $id = $user->id;
            }
        } else {
            $user = \Auth::user();
            if ($user->isAbleTo('workspace manage')) {
                $id = $user->id;
            } else {
                $user = User::where('id', $user->created_by)->first();
                $id = $user->id;
            }
        }
        if ($type == "User") {
            if ($user->total_user >= 0) {
                if ($user->isAbleTo('workspace manage')) {
                    $users = User::where('created_by', $id)->where('workspace_id', getActiveWorkSpace())->get();
                } else {
                    $users = User::where('created_by', $user->created_by)->get();
                }
                if ($users->count() >= $user->total_user) {
//                    return false;
                    return true;
                } else {
                    return true;
                }
            } elseif ($user->total_user < 0) {
                return true;
            }
        }
        if ($type == "Workspace") {
            if ($user->total_workspace >= 0) {
                $workspace = WorkSpace::where('created_by', $id)->get();

                if ($workspace->count() >= $user->total_workspace) {
//                    return false;

                    return true;
                } else {
                    return true;
                }
            } elseif ($user->total_workspace < 0) {
                return true;
            }
        }
    }

}
if (!function_exists('CheckCoupon')) {

    function CheckCoupon($code, $price = 0, $plan_id = 0) {
        if (empty($code) || intval($price) <= 0) {
            return $price;
        }

        $coupon = Coupon::where('code', strtoupper($code))
                ->where('is_active', '1')
                ->first();

        if (empty($coupon)) {
            return $price;
        }

        $usedCoupon = $coupon->used_coupon();
        $userUsedCoupon = \Auth::user()->user_coupon_user($coupon);

        if (
                $usedCoupon >= $coupon->limit ||
                $userUsedCoupon >= $coupon->limit_per_user ||
                $coupon->minimum_spend > $price ||
                $coupon->maximum_spend < $price ||
                $coupon->expiry_date < date('Y-m-d')
        ) {
            return $price;
        }

        switch ($coupon->type) {
            case 'percentage':
                $discountValue = ($price / 100) * $coupon->discount;
                $finalPrice = $price - $discountValue;
                break;
            case 'flat':
                $finalPrice = $price - $coupon->discount;
                break;
            case 'fixed':
                if ((!empty($coupon->included_module) && in_array($plan_id, explode(',', $coupon->included_module))) ||
                        (empty($coupon->included_module) && !in_array($plan_id, explode(',', $coupon->excluded_module)))
                ) {
                    $finalPrice = $price - $coupon->discount;
                } else {
                    return $price;
                }
                break;
            default:
                return $price;
        }

        return $finalPrice;
    }

}

if (!function_exists('UserCoupon')) {

    function UserCoupon($code, $orderID, $user_id = null) {
        if (!empty($code)) {
            $coupons = Coupon::where('code', strtoupper($code))->where('is_active', '1')->first();
            if ($user_id) {
                $user = User::find($user_id);
            } else {
                $user = \Auth::user();
            }
            if (!empty($coupons)) {
                $userCoupon = new UserCoupon();
                $userCoupon->user = $user->id;
                $userCoupon->coupon = $coupons->id;
                $userCoupon->order = $orderID;
                $userCoupon->save();

                $usedCoupun = $coupons->used_coupon();
                if ($coupons->limit <= $usedCoupun) {
                    $coupons->is_active = 0;
                    $coupons->save();
                }
            }
        }
    }

}

// if Subscription price is 0 then call this
if (!function_exists('DirectAssignPlan')) {

    function DirectAssignPlan($plan_id, $duration, $user_module, $counter, $type, $coupon_code = null, $user_id = null) {
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
        $plan = Plan::find($plan_id);
        if (empty($user_id)) {
            $user_id = \Auth::user()->id;
        }
        $user = User::find($user_id);
        $assignPlan = $user->assignPlan($plan->id, $duration, $user_module, $counter, $user_id);
        if ($assignPlan['is_success']) {
            $order = Order::create(
                    [
                        'order_id' => $orderID,
                        'name' => null,
                        'email' => null,
                        'card_number' => null,
                        'card_exp_month' => null,
                        'card_exp_year' => null,
                        'plan_name' => !empty($plan->name) ? $plan->name : 'Basic Package',
                        'plan_id' => $plan->id,
                        'price' => 0,
                        'price_currency' => admin_setting('defult_currancy'),
                        'txn_id' => '',
                        'payment_type' => !empty($type) ? $type : "STRIPE",
                        'payment_status' => 'succeeded',
                        'receipt' => null,
                        'user_id' => $user_id,
                    ]
            );
            if ($coupon_code) {

                UserCoupon($coupon_code, $order);
            }
            return ['is_success' => true];
        } else {
            return ['is_success' => false];
        }
    }

}
if (!function_exists('makeEmailLang')) {

    function makeEmailLang($lang) {
        $templates = EmailTemplate::all();
        foreach ($templates as $template) {

            $default_lang = EmailTemplateLang::where('parent_id', '=', $template->id)->where('lang', 'LIKE', 'en')->first();

            $emailTemplateLang = new EmailTemplateLang();
            $emailTemplateLang->parent_id = $template->id;
            $emailTemplateLang->lang = $lang;
            $emailTemplateLang->subject = $default_lang->subject;
            $emailTemplateLang->content = $default_lang->content;
            $emailTemplateLang->variables = $default_lang->variables;
            $emailTemplateLang->save();
        }
    }

}
if (!function_exists('error_res')) {

    function error_res($msg = "", $args = array()) {
        $msg = $msg == "" ? "error" : $msg;
        $msg_id = 'error.' . $msg;
        $converted = \Lang::get($msg_id, $args);
        $msg = $msg_id == $converted ? $msg : $converted;
        $json = array(
            'flag' => 0,
            'msg' => $msg,
        );

        return $json;
    }

}

if (!function_exists('success_res')) {

    function success_res($msg = "", $args = array()) {
        $msg = $msg == "" ? "success" : $msg;
        $json = array(
            'flag' => 1,
            'msg' => $msg,
        );

        return $json;
    }

}

if (!function_exists('GetDeviceType')) {

    function GetDeviceType($user_agent) {
        $mobile_regex = '/(?:phone|windows\s+phone|ipod|blackberry|(?:android|bb\d+|meego|silk|googlebot) .+? mobile|palm|windows\s+ce|opera mini|avantgo|mobilesafari|docomo)/i';
        $tablet_regex = '/(?:ipad|playbook|(?:android|bb\d+|meego|silk)(?! .+? mobile))/i';
        if (preg_match_all($mobile_regex, $user_agent)) {
            return 'mobile';
        } else {
            if (preg_match_all($tablet_regex, $user_agent)) {
                return 'tablet';
            } else {
                return 'desktop';
            }
        }
    }

}

// Get Cache Size
if (!function_exists('CacheSize')) {

    function CacheSize() {
        //start for cache clear
        $file_size = 0;
        foreach (\File::allFiles(storage_path('/framework')) as $file) {
            $file_size += $file->getSize();
        }
        $file_size = number_format($file_size / 1000000, 4);

        return $file_size;
    }

}

if (!function_exists('get_module_img')) {

    function get_module_img($module) {
        $module = Module::find($module);
        return $module->image;
    }

}

if (!function_exists('sidebar_logo')) {

    function sidebar_logo() {
        $admin_settings = getAdminAllSetting();
        if (\Auth::check() && (\Auth::user()->type != 'super admin')) {
            $company_settings = getCompanyAllSetting();

//            dd($company_settings);

            if ((isset($company_settings['cust_darklayout']) ? $company_settings['cust_darklayout'] : 'off') == 'on') {
                if (!empty($company_settings['logo_light'])) {
                    if (check_file($company_settings['logo_light'])) {
                        return $company_settings['logo_light'];
                    } else {
                        return 'uploads/logo/logo_light.png';
                    }
                } else {
                    if (!empty($admin_settings['logo_light'])) {
                        if (check_file($admin_settings['logo_light'])) {
                            return $admin_settings['logo_light'];
                        } else {
                            return 'uploads/logo/logo_light.png';
                        }
                    } else {
                        return 'uploads/logo/logo_light.png';
                    }
                }
            } else {
                if (!empty($company_settings['logo_dark'])) {
                    if (check_file($company_settings['logo_dark'])) {
                        return $company_settings['logo_dark'];
                    } else {
                        return 'uploads/logo/logo_dark.png';
                    }
                } else {
                    if (!empty($admin_settings['logo_dark'])) {
                        if (check_file($admin_settings['logo_dark'])) {
                            return $admin_settings['logo_dark'];
                        } else {
                            return 'uploads/logo/logo_dark.png';
                        }
                    } else {
                        return 'uploads/logo/logo_dark.png';
                    }
                }
            }
        } else {
            if ((isset($admin_settings['cust_darklayout']) ? $admin_settings['cust_darklayout'] : 'off') == 'on') {
                if (!empty($admin_settings['logo_light'])) {
                    if (check_file($admin_settings['logo_light'])) {
                        return $admin_settings['logo_light'];
                    } else {
                        return 'uploads/logo/logo_light.png';
                    }
                } else {
                    return 'uploads/logo/logo_light.png';
                }
            } else {
                if (!empty($admin_settings['logo_dark'])) {
                    if (check_file($admin_settings['logo_dark'])) {
                        return $admin_settings['logo_dark'];
                    } else {
                        return 'uploads/logo/logo_dark.png';
                    }
                } else {
                    return 'uploads/logo/logo_dark.png';
                }
            }
        }
    }

}

if (!function_exists('light_logo')) {

    function light_logo() {
        if (\Auth::check()) {
            $company_settings = getCompanyAllSetting();
            $logo_light = isset($company_settings['logo_light']) ? $company_settings['logo_light'] : 'uploads/logo/logo_light.png';
        } else {
            $admin_settings = getAdminAllSetting();
            $logo_light = isset($admin_settings['logo_light']) ? $admin_settings['logo_light'] : 'uploads/logo/logo_light.png';
        }
        if (check_file($logo_light)) {
            return $logo_light;
        } else {
            return 'uploads/logo/logo_dark.png';
        }
    }

}

if (!function_exists('dark_logo')) {

    function dark_logo() {
        if (\Auth::check()) {
            $company_settings = getCompanyAllSetting();
            $logo_dark = isset($company_settings['logo_dark']) ? $company_settings['logo_dark'] : 'uploads/logo/logo_dark.png';
        } else {
            $admin_settings = getAdminAllSetting();
            $logo_dark = isset($admin_settings['logo_dark']) ? $admin_settings['logo_dark'] : 'uploads/logo/logo_dark.png';
        }
        if (check_file($logo_dark)) {
            return $logo_dark;
        } else {
            return 'uploads/logo/logo_dark.png';
        }
    }

}

if (!function_exists('currency_format')) {

    function currency_format($price, $company_id = null, $workspace = null) {

        return number_format($price, company_setting('currency_format', $company_id, $workspace), '.', '');
    }

}

if (!function_exists('currency_format_with_sym')) {

    function currency_format_with_sym($price, $company_id = null, $workspace = null) {
        if (!empty($company_id) && empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id);
        } elseif (!empty($company_id) && !empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id, $workspace);
        } else {
            $company_settings = getCompanyAllSetting();
        }
        $symbol_position = 'pre';
        $currancy_symbol = '$';
        $currency_space = null;
        $format = '1';
        $number = explode('.', $price);
        $length = strlen(trim($number[0]));
        $float_number = isset($company_settings['float_number']) && $company_settings['float_number'] != 'dot' ? ',' : '.';

        if ($length > 3) {
            $decimal_separator = isset($company_settings['decimal_separator']) && $company_settings['decimal_separator'] === 'dot' ? ',' : ',';
            $thousand_separator = isset($company_settings['thousand_separator']) && $company_settings['thousand_separator'] === 'dot' ? '.' : ',';
        } else {
            $decimal_separator = isset($company_settings['decimal_separator']) == 'dot' ? '.' : ',';
            $thousand_separator = isset($company_settings['thousand_separator']) == 'dot' ? '.' : ',';
        }
        if (isset($company_settings['site_currency_symbol_position'])) {
            $symbol_position = $company_settings['site_currency_symbol_position'];
        }
        if (isset($company_settings['defult_currancy_symbol'])) {
            $currancy_symbol = $company_settings['defult_currancy_symbol'];
        }
        if (isset($company_settings['currency_format'])) {
            $format = $company_settings['currency_format'];
        }
        if (isset($company_settings['currency_space'])) {
            $currency_space = isset($company_settings['currency_space']) ? $company_settings['currency_space'] : '';
        }
        if (isset($company_settings['site_currency_symbol_name'])) {
            $defult_currancy = $company_settings['defult_currancy'];
            $defult_currancy_symbol = $company_settings['defult_currancy_symbol'];
            $currancy_symbol = $company_settings['site_currency_symbol_name'] == 'symbol' ? $defult_currancy_symbol : $defult_currancy;
        }
        $price = number_format($price, $format, $decimal_separator, $thousand_separator);

        if ($float_number == 'dot') {
            $price = preg_replace('/' . preg_quote($thousand_separator, '/') . '([^' . preg_quote($thousand_separator, '/') . ']*)$/', $float_number . '$1', $price);
        } else {
            $price = preg_replace('/' . preg_quote($decimal_separator, '/') . '([^' . preg_quote($decimal_separator, '/') . ']*)$/', $float_number . '$1', $price);
        }

//        dd((($symbol_position == "pre") ? $currancy_symbol : '') . ($currency_space == 'withspace' ? ' ' : '') . $price . ($currency_space == 'withspace' ? ' ' : '') . (($symbol_position == "post") ? $currancy_symbol : ''));

        return (($symbol_position == "pre") ? $currancy_symbol : '') . ($currency_space == 'withspace' ? ' ' : '') . $price . ($currency_space == 'withspace' ? ' ' : '') . (($symbol_position == "post") ? $currancy_symbol : '');
    }

}

if (!function_exists('company_date_formate')) {

    function format_indian_currency($number)
    {
        if ($number === null || $number === '') {
            return '0.00';
        }

        $num = str_replace([',', ' '], '', $number);

        if (!is_numeric($num)) {
            return $number;
        }

        $isNegative = $num < 0;
        $num = abs((float)$num);
        $num = number_format($num, 2, '.', '');

        [$integer, $decimal] = explode('.', $num);

        $length = strlen($integer);

        if ($length <= 3) {
            $result = $integer;
        } else {
            $lastThree = substr($integer, -3);
            $rest = substr($integer, 0, $length - 3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $result = $rest . ',' . $lastThree;
        }

        return ($isNegative ? '-' : '') . $result . '.' . $decimal;
    }

}

if (!function_exists('currency_format_with_sym_indian')) {

    function currency_format_with_sym_indian($price, $company_id = null, $workspace = null) {
        if (!empty($company_id) && empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id);
        } elseif (!empty($company_id) && !empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id, $workspace);
        } else {
            $company_settings = getCompanyAllSetting();
        }
        
        $symbol_position = 'post';
        $currancy_symbol = '₹';
        $currency_space = null;
        
        if (isset($company_settings['site_currency_symbol_position'])) {
            $symbol_position = $company_settings['site_currency_symbol_position'];
        }
        if (isset($company_settings['defult_currancy_symbol'])) {
            $currancy_symbol = $company_settings['defult_currancy_symbol'];
        }
        if (isset($company_settings['currency_space'])) {
            $currency_space = isset($company_settings['currency_space']) ? $company_settings['currency_space'] : '';
        }
        if (isset($company_settings['site_currency_symbol_name'])) {
            $defult_currancy = $company_settings['defult_currancy'];
            $defult_currancy_symbol = $company_settings['defult_currancy_symbol'];
            $currancy_symbol = $company_settings['site_currency_symbol_name'] == 'symbol' ? $defult_currancy_symbol : $defult_currancy;
        }
        
        // Force Indian format to use suffix position
        $symbol_position = 'post';
        
        // Use Indian number formatting
        $price = format_indian_currency($price);
        
        // Format with space before symbol when in post position
        if ($symbol_position == "post") {
            return $price . ' ' . $currancy_symbol;
        } else {
            return $currancy_symbol . ' ' . $price;
        }
    }

}

if (!function_exists('company_date_formate')) {

    function company_date_formate($date, $company_id = null, $workspace = null) {

        if (!empty($company_id) && empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id);
        } elseif (!empty($company_id) && !empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id, $workspace);
        } else {
            $company_settings = getCompanyAllSetting();
        }
        $date_formate = !empty($company_settings['site_date_format']) ? $company_settings['site_date_format'] : 'd-m-y';

        return date($date_formate, strtotime($date));
    }

}

if (!function_exists('super_currency_format_with_sym')) {

    function super_currency_format_with_sym($price) {

        $admin_settings = getAdminAllSetting();
        $symbol_position = 'pre';
        $currency_space = null;
        $symbol = '$';
        $format = '1';
        $number = explode('.', $price);
        $length = strlen(trim($number[0]));
        $float_number = isset($admin_settings['float_number']) && $admin_settings['float_number'] != 'dot' ? ',' : '.';

        if ($length > 3) {
            $decimal_separator = isset($admin_settings['decimal_separator']) && $admin_settings['decimal_separator'] === 'dot' ? '.' : ',';
            $thousand_separator = isset($admin_settings['thousand_separator']) && $admin_settings['thousand_separator'] === 'dot' ? '.' : ',';
        } else {
            $decimal_separator = isset($admin_settings['decimal_separator']) && $admin_settings['decimal_separator'] === 'dot' ? '.' : ',';
            $thousand_separator = isset($admin_settings['thousand_separator']) && $admin_settings['thousand_separator'] === 'dot' ? '.' : ',';
        }

        if (isset($admin_settings['site_currency_symbol_position']) && $admin_settings['site_currency_symbol_position'] == "post") {
            $symbol_position = 'post';
        }

        if (isset($admin_settings['defult_currancy_symbol'])) {
            $symbol = $admin_settings['defult_currancy_symbol'];
        }

        if (isset($admin_settings['currency_format'])) {
            $format = $admin_settings['currency_format'];
        }

        if (isset($admin_settings['currency_space'])) {
            $currency_space = isset($admin_settings['currency_space']) ? $admin_settings['currency_space'] : '';
        }
        if (isset($admin_settings['site_currency_symbol_name'])) {
            $defult_currancy = $admin_settings['defult_currancy'];
            $defult_currancy_symbol = $admin_settings['defult_currancy_symbol'];
            $symbol = $admin_settings['site_currency_symbol_name'] == 'symbol' ? $defult_currancy_symbol : $defult_currancy;
        }
        $price = number_format($price, $format, $decimal_separator, $thousand_separator);

        // if ($float_number == 'dot') {
        //     $price = preg_replace('/' . preg_quote($thousand_separator, '/') . '([^' . preg_quote($thousand_separator, '/') . ']*)$/', $float_number . '$1', $price);
        // } else {
        //     $price = preg_replace('/' . preg_quote($decimal_separator, '/') . '([^' . preg_quote($decimal_separator, '/') . ']*)$/', $float_number . '$1', $price);
        // }
        // return (
        //     ($symbol_position == "pre")  ?  $symbol : '') . ((isset($currency_space) && $currency_space) == 'withspace' ? ' ' : '')
        //     . number_format($price, $format, $decimal_separator, $thousand_separator) . ((isset($currency_space) && $currency_space) == 'withspace' ? ' ' : '') .
        //     (($symbol_position == "post") ?  $symbol : '');
        return (($symbol_position == "pre") ? $symbol : '') . ($currency_space == 'withspace' ? ' ' : '') . $price . ($currency_space == 'withspace' ? ' ' : '') . (($symbol_position == "post") ? $symbol : '');
    }

}

if (!function_exists('company_datetime_formate')) {

    function company_datetime_formate($date, $company_id = null, $workspace = null) {
        $company_settings = getCompanyAllSetting($company_id, $workspace);
        $date_formate = !empty($company_settings['site_date_format']) ? $company_settings['site_date_format'] : 'd-m-y';
        $time_formate = !empty($company_settings['site_time_format']) ? $company_settings['site_time_format'] : 'H:i';
        return date($date_formate . ' ' . $time_formate, strtotime($date));
    }

}
if (!function_exists('company_Time_formate')) {

    function company_Time_formate($time, $company_id = null, $workspace = null) {
        if (!empty($company_id) && empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id);
        } elseif (!empty($company_id) && !empty($workspace)) {
            $company_settings = getCompanyAllSetting($company_id, $workspace);
        } else {
            $company_settings = getCompanyAllSetting();
        }
        $time_formate = !empty($company_settings['site_time_format']) ? $company_settings['site_time_format'] : 'H:i';
        return date($time_formate, strtotime($time));
    }

}
// module price name
if (!function_exists('ModulePriceByName')) {

    function ModulePriceByName($module_name) {
        static $addons = [];
        static $resultArray = [];
        if (count($addons) == 0 && count($resultArray) == 0) {
            $addons = AddOn::all()->toArray();
            $resultArray = array_reduce($addons, function ($carry, $item) {
                // Check if both "module" and "name" keys exist in the current item
                if (isset($item['module'])) {
                    // Add a new key-value pair to the result array
                    $carry[$item['module']]['monthly_price'] = $item['monthly_price'];
                    $carry[$item['module']]['yearly_price'] = $item['yearly_price'];
                }
                return $carry;
            }, []);
        }

        $module = Module::find($module_name);

        $data = [];
        $data['monthly_price'] = $module->monthly_price;
        $data['yearly_price'] = $module->yearly_price;

        return $data;
    }

}
// invoice template Data

if (!function_exists('templateData')) {

    function templateData() {
        $arr = [];
        $arr['colors'] = [
            '003580',
            '666666',
            '6676ef',
            'f50102',
            'f9b034',
            'fbdd03',
            'c1d82f',
            '37a4e4',
            '8a7966',
            '6a737b',
            '050f2c',
            '0e3666',
            '3baeff',
            '3368e6',
            'b84592',
            'f64f81',
            'f66c5f',
            'fac168',
            '46de98',
            '40c7d0',
            'be0028',
            '2f9f45',
            '371676',
            '52325d',
            '511378',
            '0f3866',
            '48c0b6',
            '297cc0',
            'ffffff',
            '000',
        ];
        $arr['templates'] = [
            "template1" => "New York",
            "template2" => "Toronto",
            "template3" => "Rio",
            "template4" => "London",
            "template5" => "Istanbul",
            "template6" => "Mumbai",
            "template7" => "Hong Kong",
            "template8" => "Tokyo",
            "template9" => "Sydney",
            "template10" => "Paris",
        ];
        return $arr;
    }

}
if (!function_exists('AnnualLeaveCycle')) {

    function AnnualLeaveCycle() {
        $start_date = date('Y-m-d', strtotime(date('Y') . '-01-01 -1 day'));
        $end_date = date('Y-m-d', strtotime(date('Y') . '-12-31 +1 day'));

        $date['start_date'] = $start_date;
        $date['end_date'] = $end_date;

        return $date;
    }

}

// time tracker
if (!function_exists('second_to_time')) {

    function second_to_time($seconds = 0) {
        $H = floor($seconds / 3600);
        $i = ($seconds / 60) % 60;
        $s = $seconds % 60;
        $time = sprintf("%02d:%02d:%02d", $H, $i, $s);
        return $time;
    }

}


if (!function_exists('getPurchaseInvoiceStockBySiteId')) {

    function getPurchaseInvoiceStockBySiteId($siteId) {
        return DB::table('purchase_invoice_items as pii')
                        ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
                        ->join('materials as m', 'm.id', '=', 'pii.material_id')
                        ->join('units as u', 'u.id', '=', 'm.unit_id')
                        ->leftJoin('material_categories as mc', 'mc.id', '=', 'm.category_id')
                        ->where('pi.site_id', $siteId)
                        ->select(
                                'pii.material_id',
                                'm.name as material_name',
                                'm.price as material_price',
                                'u.name as unit_name',
                                'm.category_id',
                                'mc.name as category_name',
                                DB::raw('SUM(pii.quantity) as total_qty')
                        )
                        ->groupBy(
                                'pii.material_id',
                                'm.name',
                                'm.price',
                                'u.name',
                                'm.category_id',
                                'mc.name'
                        )
                        ->get();
    }

}




//if (!function_exists('getCurrentStockBySiteId')) {
//
//    /**
//     * Get current stock for a site, subtracting transfers and consumptions.
//     *
//     * @param  int  $siteId
//     * @param  int|null  $excludeConsumptionId  (optional) DailyConsumptionMaster ID to exclude when editing
//     * @return array
//     */
//    function getCurrentStockBySiteId($siteId) {
//        // Get purchased quantities
//        $purchased = DB::table('purchase_invoice_items as pii')
//            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
//            ->join('materials as m', 'm.id', '=', 'pii.material_id')
//            ->join('units as u', 'u.id', '=', 'm.unit_id')
//            ->leftJoin('material_categories as mc', 'mc.id', '=', 'm.category_id')
//            ->where('pi.site_id', $siteId)
//            ->select(
//                'pii.material_id',
//                'm.name as material_name',
//                'm.price as material_price',
//                'u.name as unit_name',
//                'm.category_id',
//                'mc.name as category_name',
//                DB::raw('SUM(pii.quantity) as purchased_qty')
//            )
//            ->groupBy(
//                'pii.material_id',
//                'm.name',
//                'm.price',
//                'u.name',
//                'm.category_id',
//                'mc.name'
//            )
//            ->get();
//
//        // Get transferred quantities (outgoing from this site)
//        $transferred = DB::table('material_transfer_items as mti')
//            ->join('material_transfers as mt', 'mt.id', '=', 'mti.material_transfer_id')
//            ->where('mt.from_site_id', $siteId)
//            ->select(
//                'mti.material_id',
//                DB::raw('SUM(mti.quantity) as transferred_qty')
//            )
//            ->groupBy('mti.material_id')
//            ->pluck('transferred_qty', 'material_id');
//
//        // Get daily consumption quantities at this site
//        
//        
//        $consumedQuery = DB::table('daily_consumption_details as dcd')
//    ->join('daily_consumption_masters as dc', 'dc.id', '=', 'dcd.daily_consumption_master_id')
//    ->where('dc.site_id', $siteId);
//
//
//
//        
//
//        $consumed = $consumedQuery
//            ->select(
//                'dcd.material_id',
//                DB::raw('SUM(dcd.quantity) as consumed_qty')
//            )
//            ->groupBy('dcd.material_id')
//            ->pluck('consumed_qty', 'material_id');
//
//        // Split into fuel vs all materials
//        $materials_fuel = [];
//        $materials_all  = [];
//
//        foreach ($purchased as $item) {
//            $materialId    = $item->material_id;
//            $transferredQty = $transferred[$materialId] ?? 0;
//            $consumedQty    = $consumed[$materialId] ?? 0;
//
//            $availableQty = max(0, $item->purchased_qty - $transferredQty - $consumedQty);
//
//            $materialData = [
//                'name'          => $item->material_name,
//                'unit'          => $item->unit_name,
//                'price'         => $item->material_price,
//                'total_qty'     => $availableQty,
//                'category_id'   => $item->category_id,
//                'category_name' => $item->category_name,
//            ];
//
////            if ((int)$item->category_id === 2) {
////                $materials_fuel[$materialId] = $materialData;
////            } else {
////                $materials_all[$materialId] = $materialData;
////            }
//            
//            
//            $materials[$materialId] = $materialData;
//        }
//
////        return [
////            'fuel' => $materials_fuel,
////            'all'  => $materials_all,
////        ];
//        
//        
//       return $materials;
//    }
//}
//if (!function_exists('getCurrentStockBySiteId')) {
//
//    /**
//     * Get current stock for a site, subtracting transfers and consumptions.
//     *
//     * @param  int       $siteId
//     * @param  int|null  $excludeConsumptionId  (optional) DailyConsumptionMaster ID to exclude when editing
//     * @return \Illuminate\Support\Collection
//     */
//    function getCurrentStockBySiteId($siteId) {
//        // Get purchased quantities
//        $purchased = DB::table('purchase_invoice_items as pii')
//            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
//            ->join('materials as m', 'm.id', '=', 'pii.material_id')
//            ->join('units as u', 'u.id', '=', 'm.unit_id')
//            ->leftJoin('material_categories as mc', 'mc.id', '=', 'm.category_id')
//            ->where('pi.site_id', $siteId)
//            ->select(
//                'pii.material_id',
//                'm.name as material_name',
//                'm.price as material_price',
//                'u.name as unit_name',
//                'm.category_id',
//                'mc.name as category_name',
//                DB::raw('SUM(pii.quantity) as purchased_qty')
//            )
//            ->groupBy(
//                'pii.material_id',
//                'm.name',
//                'm.price',
//                'u.name',
//                'm.category_id',
//                'mc.name'
//            )
//            ->get();
//
//        // Get transferred quantities (outgoing from this site)
//        $transferred = DB::table('material_transfer_items as mti')
//            ->join('material_transfers as mt', 'mt.id', '=', 'mti.material_transfer_id')
//            ->where('mt.from_site_id', $siteId)
//            ->select('mti.material_id', DB::raw('SUM(mti.quantity) as transferred_qty'))
//            ->groupBy('mti.material_id')
//            ->pluck('transferred_qty', 'material_id');
//
//        // Get daily consumption quantities at this site
//        $consumedQuery = DB::table('daily_consumption_details as dcd')
//            ->join('daily_consumption_masters as dc', 'dc.id', '=', 'dcd.daily_consumption_master_id')
//            ->where('dc.site_id', $siteId);
//
//        if ($excludeConsumptionId) {
//            $consumedQuery->where('dc.id', '!=', $excludeConsumptionId);
//        }
//
//        $consumed = $consumedQuery
//            ->select('dcd.material_id', DB::raw('SUM(dcd.quantity) as consumed_qty'))
//            ->groupBy('dcd.material_id')
//            ->pluck('consumed_qty', 'material_id');
//
//        // Build collection
//        $materials_array = collect();
//
//        foreach ($purchased as $item) {
//            $materialId     = $item->material_id;
//            $transferredQty = $transferred[$materialId] ?? 0;
//            $consumedQty    = $consumed[$materialId] ?? 0;
//
//            $availableQty = max(0, $item->purchased_qty - $transferredQty - $consumedQty);
//
//            $materials_array->put($materialId, [
//                'name'          => $item->material_name,
//                'unit'          => $item->unit_name,
//                'price'         => $item->material_price,
//                'total_qty'     => $availableQty,
//                'category_id'   => $item->category_id,
//                'category_name' => $item->category_name,
//            ]);
//        }
//
//        return $materials_array;
//    }
//}




if (!function_exists('getCurrentStockBySiteId')) {

    function getCurrentStockBySiteId(
            $siteId,
            $excludeConsumptionId = null,
            $excludeMaterialTransferId = null,
            $startDate = null,
            $endDate = null,
            $materialId = null,
            $useMaterialProjectStock = false
    ) {
        // Base materials list (ensures all materials are included, even if never purchased)
        $materials = DB::table('materials as m')
                ->join('units as u', 'u.id', '=', 'm.unit_id')
                ->leftJoin('material_categories as mc', 'mc.id', '=', 'm.category_id')
                ->select(
                        'm.id as material_id',
                        'm.name as material_name',
                        'm.price as material_price',
                        'm.reorder_level',
                        'u.name as unit_name',
                        'u.symbol as unit_symbol',
                        'm.category_id',
                        'mc.name as category_name'
                );

        if (!empty($materialId) && $materialId != 0) {
            $materials->where('m.id', $materialId);
        }

        // ✅ Sort by material name
        $materials = $materials->orderBy('m.name', 'asc')->get();

        // Purchases (from Purchase Invoices)
        $purchasedQuery = DB::table('purchase_invoice_items as pii')
                ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
                ->where('pi.site_id', $siteId);

        if (!empty($materialId) && $materialId != 0) {
            $purchasedQuery->where('pii.material_id', $materialId);
        }

        if ($startDate && $endDate) {
            $purchasedQuery->whereBetween('pi.invoice_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $purchasedQuery->where('pi.invoice_date', '>=', $startDate);
        } elseif ($endDate) {
            $purchasedQuery->where('pi.invoice_date', '<=', $endDate);
        }

        $purchased = $purchasedQuery
                ->select('pii.material_id', DB::raw('SUM(pii.quantity) as purchased_qty'))
                ->groupBy('pii.material_id')
                ->pluck('purchased_qty', 'material_id');

        // GRN (Direct Goods Receipt Notes)
        $grnQuery = DB::table('grn_items as gi')
                ->join('grns as g', 'g.id', '=', 'gi.grn_id')
                ->where('g.site_id', $siteId)
                ->whereIn('g.status', ['completed', 'approved', 'Completed']); // Include completed and approved GRNs

        if (!empty($materialId) && $materialId != 0) {
            $grnQuery->where('gi.material_id', $materialId);
        }

        if ($startDate && $endDate) {
            $grnQuery->whereBetween('g.grn_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $grnQuery->where('g.grn_date', '>=', $startDate);
        } elseif ($endDate) {
            $grnQuery->where('g.grn_date', '<=', $endDate);
        }

        $grnReceived = $grnQuery
                ->select('gi.material_id', DB::raw('SUM(gi.accepted_qty) as grn_qty'))
                ->groupBy('gi.material_id')
                ->pluck('grn_qty', 'material_id');

        // Transfers OUT
        $transferredOutQuery = DB::table('material_transfer_items as mti')
                ->join('material_transfers as mt', 'mt.id', '=', 'mti.material_transfer_id')
                ->where('mt.from_site_id', $siteId);

        if ($excludeMaterialTransferId) {
            $transferredOutQuery->where('mt.id', '!=', $excludeMaterialTransferId);
        }

        if (!empty($materialId) && $materialId != 0) {
            $transferredOutQuery->where('mti.material_id', $materialId);
        }

        if ($startDate && $endDate) {
            $transferredOutQuery->whereBetween('mt.record_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $transferredOutQuery->where('mt.record_date', '>=', $startDate);
        } elseif ($endDate) {
            $transferredOutQuery->where('mt.record_date', '<=', $endDate);
        }

        $transferredOut = $transferredOutQuery
                ->select('mti.material_id', DB::raw('SUM(mti.quantity) as transferred_out_qty'))
                ->groupBy('mti.material_id')
                ->pluck('transferred_out_qty', 'material_id');

        // Transfers IN
        $transferredInQuery = DB::table('material_transfer_items as mti')
                ->join('material_transfers as mt', 'mt.id', '=', 'mti.material_transfer_id')
                ->where('mt.to_site_id', $siteId);

        if ($excludeMaterialTransferId) {
            $transferredInQuery->where('mt.id', '!=', $excludeMaterialTransferId);
        }

        if (!empty($materialId) && $materialId != 0) {
            $transferredInQuery->where('mti.material_id', $materialId);
        }

        if ($startDate && $endDate) {
            $transferredInQuery->whereBetween('mt.record_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $transferredInQuery->where('mt.record_date', '>=', $startDate);
        } elseif ($endDate) {
            $transferredInQuery->where('mt.record_date', '<=', $endDate);
        }

        $transferredIn = $transferredInQuery
                ->select('mti.material_id', DB::raw('SUM(mti.quantity) as transferred_in_qty'))
                ->groupBy('mti.material_id')
                ->pluck('transferred_in_qty', 'material_id');

        // Consumption
        $consumedQuery = DB::table('daily_consumption_details as dcd')
                ->join('daily_consumption_masters as dc', 'dc.id', '=', 'dcd.daily_consumption_master_id')
                ->where('dc.site_id', $siteId);

        if ($excludeConsumptionId) {
            $consumedQuery->where('dc.id', '!=', $excludeConsumptionId);
        }

        if (!empty($materialId) && $materialId != 0) {
            $consumedQuery->where('dcd.material_id', $materialId);
        }

        if ($startDate && $endDate) {
            $consumedQuery->whereBetween('dc.consumption_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $consumedQuery->where('dc.consumption_date', '>=', $startDate);
        } elseif ($endDate) {
            $consumedQuery->where('dc.consumption_date', '<=', $endDate);
        }

        $consumed = $consumedQuery
                ->select('dcd.material_id', DB::raw('SUM(dcd.quantity) as consumed_qty'))
                ->groupBy('dcd.material_id')
                ->pluck('consumed_qty', 'material_id');

        // Get opening stock from material_project_stock table
        $openingStockQuery = DB::table('material_project_stock as mps')
                ->where('mps.project_id', $siteId);
                
        if (!empty($materialId) && $materialId != 0) {
            $openingStockQuery->where('mps.material_id', $materialId);
        }
        
        $openingStock = $openingStockQuery
                ->select('mps.material_id', 'mps.current_stock as opening_stock_qty')
                ->pluck('opening_stock_qty', 'material_id');

        // Merge and calculate available stock for ALL materials
        foreach ($materials as $item) {
            $currentMaterialId = $item->material_id;
            
            if ($useMaterialProjectStock) {
                // Use MaterialProjectStock table as primary source
                $item->total_qty = \App\Models\MaterialProjectStock::getCurrentStock($siteId, $currentMaterialId);
            } else {
                // Use calculation method (original logic + GRN + Opening Stock)
                $purchasedQty = $purchased[$currentMaterialId] ?? 0;
                $grnQty = $grnReceived[$currentMaterialId] ?? 0; // Add GRN quantities
                $transferredOutQty = $transferredOut[$currentMaterialId] ?? 0;
                $transferredInQty = $transferredIn[$currentMaterialId] ?? 0;
                $consumedQty = $consumed[$currentMaterialId] ?? 0;
                $openingStockQty = $openingStock[$currentMaterialId] ?? 0; // Add opening stock

                $item->total_qty = max(0, $openingStockQty + $purchasedQty + $grnQty + $transferredInQty - $transferredOutQty - $consumedQty);
            }
        }

        // ✅ If specific material was requested, return numeric value instead of collection
        if ($materialId !== null && $materialId !== '' && $materialId != 0) {
            $material = $materials->firstWhere('material_id', $materialId);
            return $material ? (float)$material->total_qty : 0;
        }

        // ✅ Return all materials sorted by name (only when no specific material requested)
        // Ensure we return a Collection even if materials is empty
        if ($materials instanceof \Illuminate\Support\Collection) {
            return $materials->values();
        } else {
            // Fallback: return empty collection if materials is not a Collection
            return collect([]);
        }
    }

}



if (!function_exists('getSitesWithWorkspace')) {

    /**
     * Get all projects/sites with workspace name appended,
     * excluding specific site IDs.
     *
     * @param  array  $excludeIds
     * @return \Illuminate\Support\Collection
     */
    function getSitesWithWorkspace(array $excludeIds = []) {
        // Fetch all workspaces as id => name
        $workspaces = WorkSpace::pluck('name', 'id');

        // Build query
        $query = Project::projectonly();
        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        // Map projects with workspace name
        return $query->get()->mapWithKeys(function ($project) use ($workspaces) {
                    $workspaceName = $workspaces[$project->workspace] ?? null;
                    $label = $project->name . ($workspaceName ? " ({$workspaceName})" : '');
                    return [$project->id => $label];
                });
    }

}

if (!function_exists('getSitesWithWorkspaceAndSiteId')) {

    /**
     * Get projects/sites with workspace name appended,
     * restricted to given site IDs.
     *
     * @param  array  $siteIds
     * @return \Illuminate\Support\Collection
     */
    function getSitesWithWorkspaceAndSiteId(array $siteIds = []) {
        // Fetch all workspaces as id => name
        $workspaces = WorkSpace::pluck('name', 'id');

        // Build query
        $query = Project::projectonly();
        if (!empty($siteIds)) {
            $query->whereIn('id', $siteIds);
        }

        // Map projects with workspace name
        return $query->get()->mapWithKeys(function ($project) use ($workspaces) {
                    // Use workspace_id column to look up name
                    $workspaceName = $workspaces[$project->workspace] ?? null;
                    $label = $project->name . ($workspaceName ? " ({$workspaceName})" : '');
                    return [$project->id => $label];
                });
    }

    if (!function_exists('getAllSitesWithWorkspace')) {

        /**
         * Get all projects/sites with workspace name appended,
         * excluding specific site IDs.
         *
         * @param  array  $excludeIds
         * @return \Illuminate\Support\Collection
         */
        function getAllSitesWithWorkspace() {
            // Fetch all workspaces as id => name
            $workspaces = WorkSpace::pluck('name', 'id');

            // Build query
            $query = Project::projectonly();

            // Map projects with workspace name
            return $query->get()->mapWithKeys(function ($project) use ($workspaces) {
                        $workspaceName = $workspaces[$project->workspace] ?? null;
                        $label = $project->name . ($workspaceName ? " ({$workspaceName})" : '');
                        return [$project->id => $label];
                    });
        }

    }
}

if (!function_exists('getWorkspaceIDFromSiteID')) {

    /**
     * Get the workspace ID for a given site/project ID.
     *
     * @param  int  $siteId
     * @return int|null
     */
    function getWorkspaceIDFromSiteID($siteId) {
//            dd($siteId);
        $project = Project::find($siteId);

        return $project ? $project->workspace : null;
    }

}


if (!function_exists('getFirstProjectIdWithUser')) {

    function getFirstProjectIdWithUser(int $workspace_id, int $user_id): ?int {
        $project = Project::where('workspace', $workspace_id)
                ->whereHas('users', function ($query) use ($user_id) {
                    $query->where('user_id', $user_id)
                            ->where('user_projects.is_active', 1); // filter on pivot column
                })
                ->orderBy('id', 'ASC')
                ->first();

        return $project ? $project->id : null;
    }

}

// Payment Module Helper Functions
if (!function_exists('getInvoiceBalance')) {

    /**
     * Get invoice balance (total - paid).
     *
     * @param int $invoiceId
     * @return float
     */
    function getInvoiceBalance($invoiceId) {
        return PaymentsModule::getInvoiceBalance($invoiceId);
    }

}




if (!function_exists('getSystemActiveModule')) {

    function getSystemActiveModule() {

        Cache::forget('system_active_module');

        return Cache::rememberForever('system_active_module', function () {

            $settings = [
                'activity',
                'announcement',
                'attendance',
                'branch',
                'consumption-log',
                'department',
                'designation',
                'documenttype',
                'employee',
                'event',
                'general-transfer',
                'grn',
                'holiday',
                'hrm',
                'indent',
                'leave',
                'leavetype',
                'machinery',
                'machinery-category',
                'machinery-payment-requests',
                'machinery-dpr',
                'man-power',
                'man-power-type',
                'manage-payment',
                'material',
                'material-category',
                
                'material-issue',
                'material-return',
                'material-transfer',
                'material-unit',
                'opening-stock',
                'payment-request',
                'project',
                'project-file',
                'project-document',
                'purchase-invoice',
                'purchase-order',
                'roles',
                'site-stock',
                'stock-ledger',
                'stock-report',
                'supplier',
                'supplier-advance',
                'supplier-category',
                'supplier-ledger',
                'spent',
                'tools-and-equipment',
                'user',
                'workspace',

//                        'setting',
//                        'taskly',
                        'milestone',
                        'task',
                        'taskstage',
                        'sub-task',
//                        'sidebar',
//                        'document',

            ];

            return $settings;
        });
    }

}



if (!function_exists('isWithinSiteRadius')) {

    /**
     * Check if employee login coordinates are within given radius (default 1 km) of site
     *
     * @param float $siteLat
     * @param float $siteLon
     * @param float $empLat
     * @param float $empLon
     * @param float $radiusKm
     * @return bool
     */
    function isWithinSiteRadius(float $siteLat, float $siteLon, float $empLat, float $empLon, float $radiusKm = 1): bool {
        $earthRadius = 6371; // km

        $lat1 = deg2rad($siteLat);
        $lon1 = deg2rad($siteLon);
        $lat2 = deg2rad($empLat);
        $lon2 = deg2rad($empLon);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) * sin($dlat / 2) +
                cos($lat1) * cos($lat2) *
                sin($dlon / 2) * sin($dlon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return $distance <= $radiusKm;
    }

}

// Helper function to upload generated file content (PDF, etc.)
if (!function_exists('upload_pdf_content')) {

    function upload_pdf_content($fileContent, $path, $fileName) {
        try {
            $storage_settings = getAdminAllSetting();

            // Fallback to local storage if storage setting is not configured
            if (!isset($storage_settings['storage_setting']) || empty($storage_settings['storage_setting'])) {
                $storageDisk = 'local';
            } else {
                $storageDisk = $storage_settings['storage_setting'];
            }

            // Configure cloud storage if needed
            if ($storageDisk == 's3') {
                config([
                    'filesystems.disks.s3.key' => $storage_settings['s3_key'],
                    'filesystems.disks.s3.secret' => $storage_settings['s3_secret'],
                    'filesystems.disks.s3.region' => $storage_settings['s3_region'],
                    'filesystems.disks.s3.bucket' => $storage_settings['s3_bucket'],
                ]);
            } elseif ($storageDisk == 'wasabi') {
                config([
                    'filesystems.disks.wasabi.key' => $storage_settings['wasabi_key'],
                    'filesystems.disks.wasabi.secret' => $storage_settings['wasabi_secret'],
                    'filesystems.disks.wasabi.region' => $storage_settings['wasabi_region'],
                    'filesystems.disks.wasabi.bucket' => $storage_settings['wasabi_bucket'],
                    'filesystems.disks.wasabi.root' => $storage_settings['wasabi_root'],
                    'filesystems.disks.wasabi.endpoint' => $storage_settings['wasabi_url']
                ]);
            }

            // Create directory if it doesn't exist
            $fullPath = $path . '/' . $fileName;
            if (!Storage::disk($storageDisk)->exists($path)) {
                Storage::disk($storageDisk)->makeDirectory($path);
            }

            // Save the file to configured storage
            $savedToStorage = Storage::disk($storageDisk)->put($fullPath, $fileContent);

            // Also save to local storage (project root uploads folder)
            if (!Storage::disk('local')->exists($path)) {
                Storage::disk('local')->makeDirectory($path);
            }
            $savedToLocal = Storage::disk('local')->put($fullPath, $fileContent);

            // Also save to public/uploads folder for web access
            if (!Storage::disk('public_folder')->exists($path)) {
                Storage::disk('public_folder')->makeDirectory($path);
            }
            $savedToPublic = Storage::disk('public_folder')->put($fullPath, $fileContent);

            if ($savedToStorage || $savedToLocal || $savedToPublic) {
                // Get the stored URL path
                if ($storageDisk == 's3' || $storageDisk == 'wasabi') {
                    $url = $fullPath;
                } else {
                    $url = 'uploads/' . $fullPath;
                }

                return [
                    'flag' => 1,
                    'msg' => 'File uploaded successfully',
                    'url' => $url
                ];
            } else {
                return [
                    'flag' => 0,
                    'msg' => 'Failed to upload file',
                    'url' => null
                ];
            }
        } catch (\Exception $e) {
            Log::error('upload_pdf_content error: ' . $e->getMessage());
            return [
                'flag' => 0,
                'msg' => $e->getMessage(),
                'url' => null
            ];
        }
    }

}

if (!function_exists('format_indian_currency')) {
    function format_indian_currency($amount)
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        // Remove existing commas/spaces
        $amount = str_replace([',', ' '], '', $amount);

        if (!is_numeric($amount)) {
            return $amount;
        }

        // Handle negative values
        $isNegative = $amount < 0;
        $amount = abs((float)$amount);

        // Format to 2 decimal places
        $amount = number_format($amount, 2, '.', '');

        // Split integer and decimal
        [$integer, $decimal] = explode('.', $amount);

        $length = strlen($integer);

        if ($length <= 3) {
            $result = $integer;
        } else {
            $lastThree = substr($integer, -3);
            $rest = substr($integer, 0, $length - 3);

            // Apply Indian grouping (2 digits)
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);

            $result = $rest . ',' . $lastThree;
        }

        return ($isNegative ? '-' : '') . $result . '.' . $decimal;
    }
}
