<?php

namespace App\Services;

use App\Models\User;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\UserProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectUserService
{
    /**
     * Assign all Admin and Company users to a project
     * 
     * @param int $projectId The project ID
     * @param int|null $workspaceId Optional workspace ID to filter users
     * @return array ['assigned' => int, 'skipped' => int]
     */
    public function assignAdminsToProject(int $projectId, ?int $workspaceId = null): array
    {
        $result = ['assigned' => 0, 'skipped' => 0];

        try {
            // Get all Admin and Company users
            $adminUsersQuery = User::whereIn('type', ['Admin', 'company']);
            
            if ($workspaceId) {
                $adminUsersQuery->where(function($q) use ($workspaceId) {
                    $q->where('active_workspace', $workspaceId)
                      ->orWhere('workspace_id', $workspaceId);
                });
            }
            
            $adminUsers = $adminUsersQuery->get();

            if ($adminUsers->isEmpty()) {
                Log::info("ProjectUserService: No Admin/Company users found for project {$projectId}");
                return $result;
            }

            foreach ($adminUsers as $user) {
                // Check if user already has access
                $exists = UserProject::where('user_id', $user->id)
                    ->where('project_id', $projectId)
                    ->exists();

                if ($exists) {
                    $result['skipped']++;
                    continue;
                }

                // Assign user to project
                UserProject::create([
                    'user_id' => $user->id,
                    'project_id' => $projectId,
                ]);

                $result['assigned']++;
            }

            Log::info("ProjectUserService: Assigned {$result['assigned']} admins to project {$projectId}, skipped {$result['skipped']}");

        } catch (\Exception $e) {
            Log::error("ProjectUserService: Error assigning admins to project {$projectId}: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Assign all Admin and Company users to all projects
     * This is used by the Artisan command
     * 
     * @param int|null $workspaceId Optional workspace ID to filter users and projects
     * @return array ['projects_processed' => int, 'users_assigned' => int, 'users_skipped' => int]
     */
    public function assignAdminsToAllProjects(?int $workspaceId = null): array
    {
        $result = [
            'projects_processed' => 0,
            'users_assigned' => 0,
            'users_skipped' => 0,
        ];

        try {
            // Get all Admin and Company users
            $adminUsersQuery = User::whereIn('type', ['Admin', 'company']);
            
            if ($workspaceId) {
                $adminUsersQuery->where(function($q) use ($workspaceId) {
                    $q->where('active_workspace', $workspaceId)
                      ->orWhere('workspace_id', $workspaceId);
                });
            }
            
            $adminUsers = $adminUsersQuery->get();

            if ($adminUsers->isEmpty()) {
                Log::info("ProjectUserService: No Admin/Company users found");
                return $result;
            }

            // Get all projects (chunked for memory efficiency)
            $projectsQuery = Project::query();
            
            if ($workspaceId) {
                $projectsQuery->where('workspace', $workspaceId);
            }

            $projectsQuery->chunk(100, function($projects) use ($adminUsers, &$result) {
                foreach ($projects as $project) {
                    $result['projects_processed']++;

                    foreach ($adminUsers as $user) {
                        // Check if user already has access
                        $exists = UserProject::where('user_id', $user->id)
                            ->where('project_id', $project->id)
                            ->exists();

                        if ($exists) {
                            $result['users_skipped']++;
                            continue;
                        }

                        // Assign user to project
                        UserProject::create([
                            'user_id' => $user->id,
                            'project_id' => $project->id,
                        ]);

                        $result['users_assigned']++;
                    }
                }
            });

            Log::info("ProjectUserService: Processed {$result['projects_processed']} projects, assigned {$result['users_assigned']} users, skipped {$result['users_skipped']}");

        } catch (\Exception $e) {
            Log::error("ProjectUserService: Error in assignAdminsToAllProjects: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Bulk assign using DB operations for better performance
     * 
     * @param int $projectId The project ID
     * @param int|null $workspaceId Optional workspace ID to filter users
     * @return array ['assigned' => int, 'skipped' => int]
     */
    public function bulkAssignAdminsToProject(int $projectId, ?int $workspaceId = null): array
    {
        $result = ['assigned' => 0, 'skipped' => 0];

        try {
            // Get all Admin and Company users
            $adminUsersQuery = User::whereIn('type', ['Admin', 'company']);
            
            if ($workspaceId) {
                $adminUsersQuery->where(function($q) use ($workspaceId) {
                    $q->where('active_workspace', $workspaceId)
                      ->orWhere('workspace_id', $workspaceId);
                });
            }
            
            $adminUserIds = $adminUsersQuery->pluck('id');

            if ($adminUserIds->isEmpty()) {
                Log::info("ProjectUserService: No Admin/Company users found for project {$projectId}");
                return $result;
            }

            // Get already assigned user IDs for this project
            $existingUserIds = DB::table('user_projects')
                ->where('project_id', $projectId)
                ->pluck('user_id');

            // Get user IDs that need to be assigned
            $userIdsToAssign = $adminUserIds->diff($existingUserIds);

            if ($userIdsToAssign->isEmpty()) {
                $result['skipped'] = $adminUserIds->count();
                Log::info("ProjectUserService: All admins already assigned to project {$projectId}");
                return $result;
            }

            // Bulk insert new assignments
            $insertData = [];
            foreach ($userIdsToAssign as $userId) {
                $insertData[] = [
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('user_projects')->insert($insertData);

            $result['assigned'] = $userIdsToAssign->count();
            $result['skipped'] = $existingUserIds->intersect($adminUserIds)->count();

            Log::info("ProjectUserService: Bulk assigned {$result['assigned']} admins to project {$projectId}, skipped {$result['skipped']}");

        } catch (\Exception $e) {
            Log::error("ProjectUserService: Error in bulk assign admins to project {$projectId}: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Bulk assign using DB operations for all projects
     * 
     * @param int|null $workspaceId Optional workspace ID to filter users and projects
     * @return array ['projects_processed' => int, 'users_assigned' => int]
     */
    public function bulkAssignAdminsToAllProjects(?int $workspaceId = null): array
    {
        $result = [
            'projects_processed' => 0,
            'users_assigned' => 0,
        ];

        try {
            // Get all Admin and Company users
            $adminUsersQuery = User::whereIn('type', ['Admin', 'company']);
            
            if ($workspaceId) {
                $adminUsersQuery->where(function($q) use ($workspaceId) {
                    $q->where('active_workspace', $workspaceId)
                      ->orWhere('workspace_id', $workspaceId);
                });
            }
            
            $adminUserIds = $adminUsersQuery->pluck('id');

            if ($adminUserIds->isEmpty()) {
                Log::info("ProjectUserService: No Admin/Company users found");
                return $result;
            }

            // Get all projects (chunked for memory efficiency)
            $projectsQuery = Project::query();
            
            if ($workspaceId) {
                $projectsQuery->where('workspace', $workspaceId);
            }

            $projectsQuery->chunk(100, function($projects) use ($adminUserIds, &$result) {
                foreach ($projects as $project) {
                    $result['projects_processed']++;

                    // Get already assigned user IDs for this project
                    $existingUserIds = DB::table('user_projects')
                        ->where('project_id', $project->id)
                        ->pluck('user_id');

                    // Get user IDs that need to be assigned
                    $userIdsToAssign = $adminUserIds->diff($existingUserIds);

                    if ($userIdsToAssign->isEmpty()) {
                        continue;
                    }

                    // Bulk insert new assignments
                    $insertData = [];
                    foreach ($userIdsToAssign as $userId) {
                        $insertData[] = [
                            'user_id' => $userId,
                            'project_id' => $project->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    DB::table('user_projects')->insert($insertData);
                    $result['users_assigned'] += $userIdsToAssign->count();
                }
            });

            Log::info("ProjectUserService: Bulk processed {$result['projects_processed']} projects, assigned {$result['users_assigned']} users");

        } catch (\Exception $e) {
            Log::error("ProjectUserService: Error in bulk assign admins to all projects: " . $e->getMessage());
        }

        return $result;
    }
}