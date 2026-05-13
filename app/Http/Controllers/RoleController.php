<?php

namespace App\Http\Controllers;

use App\Events\CreateRole;
use App\Events\UpdateRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class RoleController extends Controller
{
    public function index()
    {

        if(Auth::user()->isAbleTo('roles manage'))
        {
            // $roles = Role::where('created_by', '=', creatorId())->where('status',0)->orderBy('id')->paginate(11);
// $roles = Role::where('status',0)->orderBy('id')->paginate(11);
            
//            dd(getCompanyOwnerId());
            
            $roles = Role::where('created_by', '=', getCompanyOwnerId())->where('status',0)->orderBy('id')->paginate(11);
            
            return view('role.index',compact('roles'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function create()
    {
        if(Auth::user()->isAbleTo('roles create'))
        {
            $user = \Auth::user();
            
            if($user->type == 'super admin')
            {
                $permissions = Permission::all()->pluck('name', 'id')->toArray();
            }
            else
            {
                $permissions = new Collection();
                foreach($user->roles as $role)
                {
                    $permissions = $permissions->merge($role->permissions);
                }
                $permissions = $permissions->pluck('name', 'id')->toArray();
            }
            
            $modules = array_merge(['General'],getshowModuleList());
            
            return view('role.create', compact('permissions','modules'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }


    public function store(Request $request)
    {
        if(Auth::user()->isAbleTo('roles create'))
        {

            $validator = \Validator::make(
                $request->all(), [
                    'name' => 'required|max:100|unique:roles,name,NULL,id,created_by,' . \Auth::user()->id,
                ]
            );

            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $name             = $request['name'];
            $role             = new Role();
            $role->name       = $name;
            $role->created_by = creatorId();
            $role->save();

            // Grant all permissions to admin and company roles by default
            if(strtolower($name) == 'admin' || strtolower($name) == 'company')
            {
                $allPermissions = Permission::all();
                foreach($allPermissions as $permission)
                {
                    $role->givePermission($permission);
                }
            }
            else
            {
                $permissions = $request['permissions'];
                foreach($permissions as $permission)
                {
                    $p = Permission::where('id', '=', $permission)->firstOrFail();
                    $role->givePermission($p);
                }
            }

            event(new CreateRole($request,$role,$request['permissions'] ?? []));
            return redirect()->route('roles.index')->with('success','The role has been created successfully');
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function show(Request $request)
    {
        return redirect()->back();
    }

    public function edit(Role $role)
    {
        if(Auth::user()->isAbleTo('roles edit'))
        {
            $user = \Auth::user();
            $permissions = Permission::all()->pluck('name', 'id')->toArray();
            
            $modules = array_merge(['General'],getshowModuleList());
            return view('role.edit', compact('role', 'permissions','modules'));
        }
        else
        {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

    }
    
    public function update(Request $request, Role $role)
{
    if(Auth::user()->isAbleTo('roles edit'))
    {
        $validator = \Validator::make(
            $request->all(), [
                'name' => 'required|max:100|unique:roles,name,' . $role['id'] . ',id,created_by,' . \Auth::user()->id,
                'permissions' => 'required',
            ]
        );

        if($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        $role->update(['name' => $request->name]);

        // Sync permissions safely
        $role->syncPermissions($request->permissions);

        event(new UpdateRole($request, $role, $request->permissions));

        sideMenuCacheForget('company');
        return redirect()->route('roles.index')->with('success','The role details are updated successfully');
    } else {
        return redirect()->back()->with('error', __('Permission denied.'));
    }
}


//    public function update(Request $request, Role $role)
//    {
//        if(Auth::user()->isAbleTo('roles edit'))
//        {
//            $validator = \Validator::make(
//                $request->all(), [
//                                    'name' => 'required|max:100|unique:roles,name,' . $role['id'] . ',id,created_by,' . \Auth::user()->id,
//                                    'permissions' => 'required',
//                                ]
//            );
//            if($validator->fails())
//            {
//                $messages = $validator->getMessageBag();
//
//                return redirect()->back()->with('error', $messages->first());
//            }
//            $permissions = $request['permissions'];
//            $role->fill(['name'=>$request->name])->save();
//
//            $p_all = Permission::all();
//
//            foreach($p_all as $p)
//            {
//                $role->removePermission($p);
//            }
//
//            foreach($permissions as $permission)
//            {
//                $p = Permission::where('id', '=', $permission)->firstOrFail();
//                $role->givePermission($p);
//            }
//            event(new UpdateRole($request,$role,$permissions));
//
//            sideMenuCacheForget('company');
//            return redirect()->route('roles.index')->with('success','The role details are updated successfully');
//        } else {
//            return redirect()->back()->with('error', __('Permission denied.'));
//        }
//    }


    public function destroy(Role $role)
    {
        if(Auth::user()->isAbleTo('roles delete'))
        {
            $role->delete();
            return redirect()->route('roles.index')->with('success', 'The role has been deleted');
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
