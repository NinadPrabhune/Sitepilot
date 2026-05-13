<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Create permissions for machinery monthly report system
        $permissions = [
            ['name' => 'monthly-control manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'machinery-payment manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'machinery-billing manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('permissions')->insert($permissions);

        // Get admin role
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        
        if ($adminRole) {
            // Get permission IDs
            $permissionIds = DB::table('permissions')
                ->whereIn('name', ['monthly-control manage', 'machinery-payment manage', 'machinery-billing manage'])
                ->pluck('id');

            // Attach permissions to admin role
            $rolePermissions = $permissionIds->map(function ($permissionId) use ($adminRole) {
                return [
                    'permission_id' => $permissionId,
                    'role_id' => $adminRole->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });

            DB::table('role_has_permissions')->insert($rolePermissions->toArray());
        }
    }

    public function down()
    {
        // Remove permissions
        DB::table('permissions')
            ->whereIn('name', ['monthly-control manage', 'machinery-payment manage', 'machinery-billing manage'])
            ->delete();

        // Remove role permissions
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole) {
            DB::table('role_has_permissions')
                ->where('role_id', $adminRole->id)
                ->whereIn('permission_id', function ($query) {
                    $query->select('id')
                        ->from('permissions')
                        ->whereIn('name', ['monthly-control manage', 'machinery-payment manage', 'machinery-billing manage']);
                })
                ->delete();
        }
    }
};
