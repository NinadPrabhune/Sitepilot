<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ValidateProjectAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get project ID from route parameters
        $projectId = $request->route('projectId') ?? $request->route('project_id');

        if (!$projectId) {
            return $next($request);
        }

        // Check if user has access to this project
        $hasAccess = $this->userHasProjectAccess($user, $projectId);

        if (!$hasAccess) {
            Log::warning("Unauthorized project access attempt", [
                'user_id' => $user->id,
                'project_id' => $projectId,
                'ip' => $request->ip(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'You do not have access to this project',
                    'status' => 403
                ], 403);
            }

            return redirect()->back()->with('error', __('You do not have access to this project'));
        }

        return $next($request);
    }

    /**
     * Check if user has access to project
     */
    private function userHasProjectAccess($user, $projectId): bool
    {
        // Super admin has access to everything
        if ($user->type === 'super admin') {
            return true;
        }

        // Check if user is assigned to the project
        $hasAccess = \Workdo\Taskly\Entities\UserProject::where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->exists();

        return $hasAccess;
    }
}
