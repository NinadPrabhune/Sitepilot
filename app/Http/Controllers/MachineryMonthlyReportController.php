<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class MachineryMonthlyReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Renderable
    {
        // Debug: Check if user is authenticated
        if (!Auth::check()) {
            abort(403, 'User not authenticated.');
        }

        $user = Auth::user();
        
        // Debug: Log user details
        \Log::info('Machinery Monthly Report Access Attempt', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_type' => $user->type,
        ]);

        // Debug: Check all user permissions
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        \Log::info('User Permissions', ['permissions' => $userPermissions]);
        
        // Debug: Check specific permission
        $hasPermission = $user->isAbleTo('machinery-monthly-report manage');
        \Log::info('Permission Check', ['has_machinery_monthly_report_permission' => $hasPermission]);

        if (!$hasPermission) {
            abort(403, 'Permission denied. User does not have "machinery-monthly-report manage" permission.');
        }

        try {
            // You can add your logic here to fetch monthly report data
            // For now, returning a basic view
            return view('machinery-monthly-report.index');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load machinery monthly report: ' . $e->getMessage()]);
        }
    }
}
