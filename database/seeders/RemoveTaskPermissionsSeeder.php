<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RemoveTaskPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Taskly task permissions to remove
        $taskPermissions = [
            'taskly manage',
            'taskly setup manage',
            'taskly dashboard manage',
            'project manage',
            'project create',
            'project edit',
            'project delete',
            'project show',
            'project invite user',
            'project report manage',
            'project import',
            'project setting',
            'project finance manage',
            'project dashboard manage',
            'team member remove',
            'team client remove',
            'bug manage',
            'bug create',
            'bug edit',
            'bug delete',
            'bug show',
            'bug move',
            'bug comments create',
            'bug comments delete',
            'bug file uploads',
            'bug file delete',
            'bugstage manage',
            'bugstage edit',
            'bugstage delete',
            'bugstage show',
            'milestone manage',
            'milestone create',
            'milestone edit',
            'milestone delete',
            'milestone show',
            'task manage',
            'task create',
            'task edit',
            'task delete',
            'task show',
            'task move',
            'task file manage',
            'task file uploads',
            'task file delete',
            'task file show',
            'task comment manage',
            'task comment create',
            'task comment edit',
            'task comment delete',
            'task comment show',
            'taskstage manage',
            'taskstage edit',
            'taskstage delete',
            'taskstage show',
            'sub-task manage',
            'sub-task create',
            'sub-task edit',
            'sub-task delete',
            'project-document manage',
            'project-document create',
            'project-document edit',
            'project-document delete',
            'project-document show',
            'project-document export',
        ];

        // Get all roles
        $roles = Role::all();

        foreach ($roles as $role) {
            foreach ($taskPermissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && $role->hasPermission($permissionName)) {
                    $role->removePermission($permission);
                    echo "Removed permission '{$permissionName}' from role '{$role->name}'\n";
                }
            }
        }

        echo "\nTask permissions removed from all roles successfully.\n";
    }
}
