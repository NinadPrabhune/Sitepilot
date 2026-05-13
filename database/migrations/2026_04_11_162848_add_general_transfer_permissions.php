<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            [
                'name' => 'general-transfer manage',
                'guard_name' => 'web',
                'module' => 'General',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'general-transfer create',
                'guard_name' => 'web',
                'module' => 'General',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'general-transfer edit',
                'guard_name' => 'web',
                'module' => 'General',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'general-transfer delete',
                'guard_name' => 'web',
                'module' => 'General',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'general-transfer show',
                'guard_name' => 'web',
                'module' => 'General',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'general-transfer export',
                'guard_name' => 'web',
                'module' => 'General',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($permissions as $perm) {
            $existing = Permission::where('name', $perm['name'])->first();
            if (!$existing) {
                $permission = Permission::create($perm);
                
                // Assign to company role
                $companyRole = Role::where('name', 'company')->first();
                if ($companyRole && !$companyRole->hasPermission($perm['name'])) {
                    $companyRole->givePermission($permission);
                }
                
                // Assign to super admin role
                $superAdminRole = Role::where('name', 'super admin')->first();
                if ($superAdminRole && !$superAdminRole->hasPermission($perm['name'])) {
                    $superAdminRole->givePermission($permission);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'general-transfer manage',
            'general-transfer create',
            'general-transfer edit',
            'general-transfer delete',
            'general-transfer show',
            'general-transfer export',
        ];

        foreach ($permissions as $name) {
            $permission = Permission::where('name', $name)->first();
            if ($permission) {
                try {
                    // Remove from roles using the relationship
                    $permission->roles()->detach();
                    $permission->delete();
                } catch (\Exception $e) {
                    // If there's an issue with permission deletion, continue
                    // This prevents migration rollback from failing
                }
            }
        }
    }
};
