<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class RoleBasedUI
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Add role-based UI data to response
        if ($response instanceof \Illuminate\Http\Response && $request->ajax()) {
            $this->addRoleDataToResponse($response);
        }
        
        return $response;
    }
    
    /**
     * Add role-based data to response
     */
    private function addRoleDataToResponse($response)
    {
        $user = Auth::user();
        if (!$user) return;
        
        $roleData = $this->getUserRoleData($user);
        
        // Add role data as meta tag for JavaScript access
        $content = $response->getOriginalContent();
        
        if (is_string($content)) {
            $roleMeta = '<meta name="user-role" content="' . htmlspecialchars(json_encode($roleData)) . '">';
            
            // Insert meta tag after head tag
            $content = preg_replace('/(<head[^>]*>)/', '$1' . $roleMeta, $content);
            
            $response->setContent($content);
        }
    }
    
    /**
     * Get user role data
     */
    private function getUserRoleData($user)
    {
        $role = $this->getUserRole($user);
        
        return [
            'role' => $role,
            'permissions' => $this->getRolePermissions($role),
            'ui_config' => $this->getRoleUIConfig($role),
            'can_approve_payments' => $this->canApprovePayments($role),
            'can_close_month' => $this->canCloseMonth($role),
            'can_view_audit' => $this->canViewAudit($role),
            'can_override_locks' => $this->canOverrideLocks($role),
            'restricted_actions' => $this->getRestrictedActions($role)
        ];
    }
    
    /**
     * Get user role
     */
    private function getUserRole($user)
    {
        // Check user role based on your role system
        // This could be based on roles table, permissions, or user attributes
        
        if ($user->hasRole('admin')) {
            return 'admin';
        } elseif ($user->hasRole('finance')) {
            return 'finance';
        } elseif ($user->hasRole('supervisor')) {
            return 'supervisor';
        } elseif ($user->hasRole('site_engineer')) {
            return 'site_engineer';
        } elseif ($user->hasRole('operator')) {
            return 'operator';
        }
        
        return 'user'; // Default role
    }
    
    /**
     * Get role permissions
     */
    private function getRolePermissions($role)
    {
        $permissions = [
            'admin' => [
                'machinery.create' => true,
                'machinery.edit' => true,
                'machinery.delete' => true,
                'machinery_payment.create' => true,
                'machinery_payment.approve' => true,
                'machinery_payment.reject' => true,
                'machinery_payment.paid' => true,
                'machinery_payment.delete' => true,
                'diesel.create' => true,
                'diesel.edit' => true,
                'diesel.delete' => true,
                'monthly_closing.close' => true,
                'monthly_closing.reopen' => true,
                'audit.view' => true,
                'audit.export' => true,
                'reports.view' => true,
                'reports.export' => true,
                'users.manage' => true,
                'settings.edit' => true,
                'override.locks' => true,
                'correction.workflow' => true
            ],
            'finance' => [
                'machinery.view' => true,
                'machinery_payment.view' => true,
                'machinery_payment.approve' => true,
                'machinery_payment.reject' => true,
                'machinery_payment.paid' => true,
                'diesel.view' => true,
                'monthly_closing.close' => true,
                'audit.view' => true,
                'reports.view' => true,
                'reports.export' => true,
                'supplier.statement' => true
            ],
            'supervisor' => [
                'machinery.view' => true,
                'machinery_payment.create' => true,
                'machinery_payment.view' => true,
                'machinery_payment.submit' => true,
                'dpr.create' => true,
                'dpr.edit' => true,
                'dpr.view' => true,
                'diesel.create' => true,
                'diesel.view' => true,
                'reports.view' => true
            ],
            'site_engineer' => [
                'machinery.view' => true,
                'dpr.create' => true,
                'dpr.edit' => true,
                'dpr.view' => true,
                'diesel.create' => true,
                'diesel.view' => true,
                'reports.view' => true
            ],
            'operator' => [
                'machinery.view' => true,
                'dpr.create' => true,
                'dpr.edit' => true,
                'dpr.view' => true,
                'diesel.view' => true
            ],
            'user' => [
                'machinery.view' => true,
                'dpr.view' => true,
                'diesel.view' => true,
                'reports.view' => true
            ]
        ];
        
        return $permissions[$role] ?? [];
    }
    
    /**
     * Get role UI configuration
     */
    private function getRoleUIConfig($role)
    {
        $configs = [
            'admin' => [
                'show_admin_panel' => true,
                'show_finance_menu' => true,
                'show_audit_menu' => true,
                'show_reports_menu' => true,
                'show_settings_menu' => true,
                'show_advanced_controls' => true,
                'show_correction_workflow' => true,
                'show_override_options' => true,
                'show_bulk_actions' => true,
                'show_import_export' => true
            ],
            'finance' => [
                'show_admin_panel' => false,
                'show_finance_menu' => true,
                'show_audit_menu' => true,
                'show_reports_menu' => true,
                'show_settings_menu' => false,
                'show_advanced_controls' => true,
                'show_correction_workflow' => false,
                'show_override_options' => false,
                'show_bulk_actions' => true,
                'show_import_export' => true
            ],
            'supervisor' => [
                'show_admin_panel' => false,
                'show_finance_menu' => false,
                'show_audit_menu' => false,
                'show_reports_menu' => true,
                'show_settings_menu' => false,
                'show_advanced_controls' => false,
                'show_correction_workflow' => false,
                'show_override_options' => false,
                'show_bulk_actions' => false,
                'show_import_export' => false
            ],
            'site_engineer' => [
                'show_admin_panel' => false,
                'show_finance_menu' => false,
                'show_audit_menu' => false,
                'show_reports_menu' => false,
                'show_settings_menu' => false,
                'show_advanced_controls' => false,
                'show_correction_workflow' => false,
                'show_override_options' => false,
                'show_bulk_actions' => false,
                'show_import_export' => false,
                'mobile_optimized' => true
            ],
            'operator' => [
                'show_admin_panel' => false,
                'show_finance_menu' => false,
                'show_audit_menu' => false,
                'show_reports_menu' => false,
                'show_settings_menu' => false,
                'show_advanced_controls' => false,
                'show_correction_workflow' => false,
                'show_override_options' => false,
                'show_bulk_actions' => false,
                'show_import_export' => false,
                'mobile_optimized' => true,
                'simplified_ui' => true
            ],
            'user' => [
                'show_admin_panel' => false,
                'show_finance_menu' => false,
                'show_audit_menu' => false,
                'show_reports_menu' => false,
                'show_settings_menu' => false,
                'show_advanced_controls' => false,
                'show_correction_workflow' => false,
                'show_override_options' => false,
                'show_bulk_actions' => false,
                'show_import_export' => false,
                'read_only' => true
            ]
        ];
        
        return $configs[$role] ?? [];
    }
    
    /**
     * Check if role can approve payments
     */
    private function canApprovePayments($role)
    {
        return in_array($role, ['admin', 'finance']);
    }
    
    /**
     * Check if role can close month
     */
    private function canCloseMonth($role)
    {
        return in_array($role, ['admin', 'finance']);
    }
    
    /**
     * Check if role can view audit
     */
    private function canViewAudit($role)
    {
        return in_array($role, ['admin', 'finance']);
    }
    
    /**
     * Check if role can override locks
     */
    private function canOverrideLocks($role)
    {
        return $role === 'admin';
    }
    
    /**
     * Get restricted actions for role
     */
    private function getRestrictedActions($role)
    {
        $restricted = [
            'operator' => ['delete', 'approve', 'reject', 'close_month', 'override'],
            'site_engineer' => ['delete', 'approve', 'reject', 'close_month', 'override'],
            'supervisor' => ['delete', 'approve', 'reject', 'close_month', 'override'],
            'finance' => ['delete', 'override'],
            'user' => ['create', 'edit', 'delete', 'approve', 'reject', 'close_month', 'override']
        ];
        
        return $restricted[$role] ?? [];
    }
}
