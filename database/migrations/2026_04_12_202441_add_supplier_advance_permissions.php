<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add supplier-advance permissions to the permissions table
        $permissions = [
            'supplier-advance manage',
            'supplier-advance create',
            'supplier-advance edit',
            'supplier-advance delete',
            'supplier-advance show',
            'supplier-advance export',
            'supplier-advance approve',
            'supplier-advance reject',
            'supplier-advance payment',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign permissions to admin role
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole) {
            foreach ($permissions as $permission) {
                $permissionId = DB::table('permissions')->where('name', $permission)->first()->id;
                DB::table('role_has_permissions')->insert([
                    'role_id' => $adminRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'supplier-advance manage',
            'supplier-advance create',
            'supplier-advance edit',
            'supplier-advance delete',
            'supplier-advance show',
            'supplier-advance export',
            'supplier-advance approve',
            'supplier-advance reject',
            'supplier-advance payment',
        ];

        foreach ($permissions as $permission) {
            $permissionRecord = DB::table('permissions')->where('name', $permission)->first();
            if ($permissionRecord) {
                // Only delete from role_has_permissions if the table exists
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->where('permission_id', $permissionRecord->id)->delete();
                }
                DB::table('permissions')->where('name', $permission)->delete();
            }
        }
    }
};
