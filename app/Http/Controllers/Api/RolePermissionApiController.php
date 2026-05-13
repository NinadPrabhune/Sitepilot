<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @group Role Permissions
 * Endpoints for retrieving user roles and permissions
 */
use Illuminate\Support\Facades\Auth;

class RolePermissionApiController extends Controller
{
    /**
     * Get authenticated user roles and permissions
     */
    public function index()
    {
        try {
            $AuthUser = Auth::user();

            if (!$AuthUser) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Fetch roles with permissions (only id and name)
            $roles = $AuthUser->roles()
                ->select('id', 'name')
                ->with(['permissions' => function ($query) {
                    $query->select('id', 'name');
                }])
                ->get();

            // Fetch direct permissions assigned to the user (optional)
            $permissions = $AuthUser->permissions()
                ->select('id', 'name')
                ->get();

            return response()->json([
                'status' => 1,
                'data' => [
                    'user' => [
                        'id' => $AuthUser->id,
                        'name' => $AuthUser->name,
                        'email' => $AuthUser->email,
                    ],
                    'roles' => $roles,
                    'permissions' => $permissions,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
