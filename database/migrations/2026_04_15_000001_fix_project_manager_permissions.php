<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find the 'project manage' permission
        $permission = Permission::where('name', 'project manage')->first();
        
        if (!$permission) {
            return; // Permission doesn't exist, nothing to do
        }

        // Find the 'Site / Project Manager' role
        $projectManagerRole = Role::where('name', 'Site / Project Manager')->first();
        
        if ($projectManagerRole) {
            // Check if permission is already assigned to avoid duplicate entry
            $existing = DB::table('permission_role')
                ->where('permission_id', $permission->id)
                ->where('role_id', $projectManagerRole->id)
                ->first();
            
            if (!$existing) {
                $projectManagerRole->givePermission($permission);
            }
        }

        // Find the 'Account Manager' role
        $accountManagerRole = Role::where('name', 'Account Manager')->first();
        
        if ($accountManagerRole) {
            // Check if permission is already assigned to avoid duplicate entry
            $existing = DB::table('permission_role')
                ->where('permission_id', $permission->id)
                ->where('role_id', $accountManagerRole->id)
                ->first();
            
            if (!$existing) {
                $accountManagerRole->givePermission($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find the 'project manage' permission
        $permission = Permission::where('name', 'project manage')->first();
        
        if (!$permission) {
            return; // Permission doesn't exist, nothing to do
        }

        // Remove from 'Site / Project Manager' role
        $projectManagerRole = Role::where('name', 'Site / Project Manager')->first();
        if ($projectManagerRole && $projectManagerRole->hasPermission($permission)) {
            $projectManagerRole->revokePermission($permission);
        }

        // Remove from 'Account Manager' role
        $accountManagerRole = Role::where('name', 'Account Manager')->first();
        if ($accountManagerRole && $accountManagerRole->hasPermission($permission)) {
            $accountManagerRole->revokePermission($permission);
        }
    }
};
