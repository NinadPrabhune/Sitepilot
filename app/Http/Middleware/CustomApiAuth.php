<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomApiAuth {

    public function handle(Request $request, Closure $next): Response {
        if (!$request->user()) {
            return response()->json(['status' => 0, 'message' => 'Unauthenticated'], 401);
        }
        $request->merge(['user_id' => $request->user()->id]);

        // MODULE CHECK
        $module = $request->segment(2);
        if ($module) {
            $request->merge(['module_name' => $module]);
        }
        $request->route()->forgetParameter('module');

        return $next($request);
    }
}
