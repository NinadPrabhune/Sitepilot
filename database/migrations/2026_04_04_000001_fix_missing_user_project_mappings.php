<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $projectsNeedingFix = DB::table('projects')
            ->select('projects.id', 'projects.created_by', 'projects.workspace')
            ->leftJoin('user_projects', 'projects.id', '=', 'user_projects.project_id')
            ->whereNull('user_projects.id')
            ->get();

        foreach ($projectsNeedingFix as $project) {
            DB::table('user_projects')->updateOrInsert(
                ['user_id' => $project->created_by, 'project_id' => $project->id],
                ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        Log::info('Project user mapping repair completed', [
            'fixed_count' => $projectsNeedingFix->count(),
            'project_ids' => $projectsNeedingFix->pluck('id')->toArray(),
        ]);
    }

    public function down(): void
    {
    }
};