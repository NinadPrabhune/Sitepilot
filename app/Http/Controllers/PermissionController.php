<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Facades\ModuleFacade as Module;;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     *
     * @authenticated
     * @response view="permission.index"
     */
    public function index()
    {
        if(Auth::user()->isAbleTo('permission manage'))
        {
            $permissions = Permission::all();
            $modules = array_merge(['General'],getModuleList());
            return view('permission.index',compact('permissions','modules'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new permission.
     *
     * @authenticated
     * @response view="permission.create"
     */
    public function create()
    {
        $roles = Role::get();
        $modules = array_merge(['General'],getModuleList());
        return view('permission.create',compact('modules','roles'));
    }

    /**
     * Store a newly created permission.
     *
     * @authenticated
     * @bodyParam name string required Permission name (max 40 chars). Example: create-post
     * @bodyParam module string required Module name. Example: General
     * @bodyParam roles array nullable Role IDs to assign permission to.
     * @bodyParam roles.* integer Role ID.
     * @response redirect
     */
    public function store(Request $request)
    {
        $this->validate(
            $request, [
                'name' => 'required|max:40',
                'module'=> 'required'
            ]
        );

        $name             = $request['name'];
        $permission       = new Permission();
        $permission->name = $name;
        $permission->module = $request['module'];

        $roles = $request['roles'];

        $permission->save();

        if(!empty($request['roles']))
        {
            foreach($roles as $role)
            {
                $r          = Role::where('id', '=', $role)->firstOrFail();
                $permission = Permission::where('name', '=', $name)->first();
                if(!$r->hasPermission($name))
                {
                    $r->givePermission($permission);
                }
            }
        }

        return redirect()->route('permissions.index')->with(
            'success', 'Permission ' . $permission->name . ' added!'
        );


    }


    /**
     * Show the form for editing the specified permission.
     *
     * @authenticated
     * @urlParam permission int required Permission ID.
     * @response view="permission.edit"
     */
    public function edit(Permission $permission)
    {

        $roles = Role::where('created_by', '=', \Auth::user()->id)->get();
        $modules = array_merge(['General'],getModuleList());
        return view('permission.edit', compact('roles', 'permission','modules'));


    }


    /**
     * Update the specified permission.
     *
     * @authenticated
     * @urlParam permission int required Permission ID.
     * @bodyParam name string required Permission name (max 40 chars). Example: create-post
     * @response redirect
     */
    public function update(Request $request, Permission $permission)
    {

        $permission = Permission::findOrFail($permission['id']);
        $this->validate(
            $request, [
                        'name' => 'required|max:40',
                    ]
        );
        $input = $request->all();
        $permission->fill($input)->save();

        return redirect()->route('permissions.index')->with(
            'success', 'Permission ' . $permission->name . ' updated!'
        );


    }

    /**
     * Remove the specified permission.
     *
     * @authenticated
     * @urlParam id int required Permission ID.
     * @response redirect
     */
    public function destroy($id)
    {

        $permission = Permission::findOrFail($id);
        $permission->delete();

        return redirect()->route('permissions.index')->with( 'success', 'The permission has been deleted' );

    }
}
