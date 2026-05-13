<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Production Security Middleware
 * Enforces production environment security policies
 */
class ProductionSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // ✅ PRODUCTION ENVIRONMENT CHECKS
        if (App::environment('production')) {
            
            // 🔴 BLOCK DEBUG ACCESS
            if (config('app.debug')) {
                Log::critical('DEBUG MODE ENABLED IN PRODUCTION', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                ]);
                
                // Block access if debug is enabled in production
                abort(503, 'System configuration error. Contact administrator.');
            }
            
            // 🔴 BLOCK ADMIN ROUTES FROM NON-ADMIN USERS
            $this->blockUnauthorizedAdminAccess($request);
            
            // 🔴 BLOCK API ACCESS WITHOUT AUTHENTICATION
            $this->blockUnauthorizedApiAccess($request);
            
            // 🔴 LOG SUSPICIOUS ACTIVITY
            $this->logSuspiciousActivity($request);
        }
        
        return $next($request);
    }
    
    /**
     * Block unauthorized admin route access
     */
    private function blockUnauthorizedAdminAccess(Request $request): void
    {
        $adminRoutes = [
            'admin/*',
            'dashboard',
            'settings',
            'users',
            'roles',
            'permissions',
            'system/*',
            'backup',
            'logs',
        ];
        
        foreach ($adminRoutes as $pattern) {
            if ($request->is($pattern)) {
                $user = $request->user();
                
                if (!$user || !$user->hasAnyRole(['super admin', 'admin'])) {
                    Log::warning('Unauthorized admin access attempt', [
                        'ip' => $request->ip(),
                        'user_id' => $user?->id,
                        'url' => $request->fullUrl(),
                        'pattern' => $pattern,
                    ]);
                    
                    abort(403, 'Unauthorized access to admin area.');
                }
            }
        }
    }
    
    /**
     * Block unauthorized API access
     */
    private function blockUnauthorizedApiAccess(Request $request): void
    {
        if ($request->is('api/*') && !$request->user()) {
            Log::warning('Unauthorized API access attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
            ]);
            
            abort(401, 'Unauthorized API access.');
        }
    }
    
    /**
     * Log suspicious activity patterns
     */
    private function logSuspiciousActivity(Request $request): void
    {
        $user = $request->user();
        $ip = $request->ip();
        
        // Check for rapid requests (potential bot/attack)
        $key = "requests_{$ip}_" . date('Y-m-d-H');
        $requestCount = cache()->increment($key, 1, now()->addHour());
        
        if ($requestCount > 1000) { // More than 1000 requests per hour
            Log::warning('High request frequency detected', [
                'ip' => $ip,
                'user_id' => $user?->id,
                'request_count' => $requestCount,
                'url' => $request->fullUrl(),
            ]);
        }
        
        // Check for unusual user agent patterns
        $userAgent = $request->userAgent();
        $suspiciousAgents = [
            'curl',
            'wget',
            'python',
            'java',
            'bot',
            'crawler',
            'scanner',
        ];
        
        foreach ($suspiciousAgents as $agent) {
            if (stripos($userAgent, $agent) !== false && !$request->is('api/*')) {
                Log::info('Suspicious user agent detected', [
                    'ip' => $ip,
                    'user_id' => $user?->id,
                    'user_agent' => $userAgent,
                    'url' => $request->fullUrl(),
                ]);
                break;
            }
        }
        
        // Check for access to sensitive routes
        $sensitiveRoutes = [
            'daily-progress-reports/*/delete',
            'machinery-ledgers/*',
            'financial-periods/*',
            'machinery-rates/*',
            'integrity-checks',
            'backup',
            'restore',
        ];
        
        foreach ($sensitiveRoutes as $pattern) {
            if ($request->is($pattern)) {
                Log::info('Access to sensitive route', [
                    'ip' => $ip,
                    'user_id' => $user?->id,
                    'user_role' => $user?->roles->pluck('name')->first(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);
            }
        }
    }
}
