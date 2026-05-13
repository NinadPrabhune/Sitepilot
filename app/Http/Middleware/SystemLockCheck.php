<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemLock;

class SystemLockCheck
{
    public function handle(Request $request, Closure $next)
    {
        \Log::info('SystemLockCheck Middleware - Route: ' . $request->route()->getName());
        \Log::info('SystemLockCheck Middleware - Method: ' . $request->method());
        
        $workspaceId = getActiveWorkSpace();

        if (SystemLock::isLocked($workspaceId)) {
            $lock = SystemLock::getActiveLock($workspaceId);
            
            // Allow view-only operations
            if ($request->isMethod('GET')) {
                return $next($request);
            }

            // Block all write operations
            return response()->json([
                'success' => false,
                'message' => 'System is currently locked for maintenance.',
                'lock_reason' => $lock->lock_reason ?? 'System maintenance in progress',
                'locked_at' => $lock->locked_at,
            ], 503);
        }

        return $next($request);
    }
}
