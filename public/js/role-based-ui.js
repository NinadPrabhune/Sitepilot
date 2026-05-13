/**
 * Role-Based UI Controls
 * Handles UI visibility and functionality based on user roles and permissions
 */

class RoleBasedUI {
    constructor() {
        this.userRole = null;
        this.permissions = {};
        this.uiConfig = {};
        this.restrictedActions = [];
        
        this.init();
    }
    
    /**
     * Initialize role-based UI
     */
    init() {
        this.loadUserRoleData();
        this.setupEventListeners();
        this.applyRoleRestrictions();
        this.setupPermissionChecks();
    }
    
    /**
     * Load user role data from meta tag
     */
    loadUserRoleData() {
        const metaTag = document.querySelector('meta[name="user-role"]');
        if (metaTag) {
            try {
                const roleData = JSON.parse(metaTag.getAttribute('content'));
                this.userRole = roleData.role;
                this.permissions = roleData.permissions || {};
                this.uiConfig = roleData.ui_config || {};
                this.restrictedActions = roleData.restricted_actions || [];
            } catch (e) {
                console.error('Error parsing user role data:', e);
            }
        }
    }
    
    /**
     * Setup event listeners for role-based actions
     */
    setupEventListeners() {
        // Intercept button clicks
        document.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                const button = e.target.tagName === 'BUTTON' ? e.target : e.target.closest('button');
                this.checkButtonPermission(button);
            }
        });
        
        // Intercept form submissions
        document.addEventListener('submit', (e) => {
            this.checkFormPermission(e.target);
        });
        
        // Intercept navigation
        document.addEventListener('click', (e) => {
            if (e.target.tagName === 'A' || e.target.closest('a')) {
                const link = e.target.tagName === 'A' ? e.target : e.target.closest('a');
                this.checkLinkPermission(link);
            }
        });
    }
    
    /**
     * Apply role restrictions to UI elements
     */
    applyRoleRestrictions() {
        // Hide/show menu items based on role
        this.applyMenuRestrictions();
        
        // Hide/show action buttons
        this.applyActionRestrictions();
        
        // Disable/enable form fields
        this.applyFieldRestrictions();
        
        // Show/hide admin panels
        this.applyPanelRestrictions();
        
        // Apply mobile optimizations
        this.applyMobileOptimizations();
    }
    
    /**
     * Apply menu restrictions
     */
    applyMenuRestrictions() {
        const menuItems = {
            '.admin-menu': this.uiConfig.show_admin_panel,
            '.finance-menu': this.uiConfig.show_finance_menu,
            '.audit-menu': this.uiConfig.show_audit_menu,
            '.reports-menu': this.uiConfig.show_reports_menu,
            '.settings-menu': this.uiConfig.show_settings_menu
        };
        
        Object.entries(menuItems).forEach(([selector, show]) => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(el => {
                el.style.display = show ? 'block' : 'none';
            });
        });
    }
    
    /**
     * Apply action restrictions
     */
    applyActionRestrictions() {
        // Hide restricted action buttons
        const restrictedButtons = document.querySelectorAll('[data-action]');
        restrictedButtons.forEach(button => {
            const action = button.getAttribute('data-action');
            if (this.restrictedActions.includes(action)) {
                button.style.display = 'none';
            }
        });
        
        // Disable buttons for restricted permissions
        const permissionButtons = document.querySelectorAll('[data-permission]');
        permissionButtons.forEach(button => {
            const permission = button.getAttribute('data-permission');
            if (!this.hasPermission(permission)) {
                button.disabled = true;
                button.title = 'You do not have permission for this action';
                button.classList.add('disabled');
            }
        });
    }
    
    /**
     * Apply field restrictions
     */
    applyFieldRestrictions() {
        if (this.uiConfig.read_only) {
            // Make all form fields read-only
            const inputs = document.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.readOnly = true;
                input.disabled = true;
            });
        }
        
        // Disable advanced controls
        if (!this.uiConfig.show_advanced_controls) {
            const advancedControls = document.querySelectorAll('.advanced-control');
            advancedControls.forEach(control => {
                control.disabled = true;
                control.style.opacity = '0.5';
            });
        }
    }
    
    /**
     * Apply panel restrictions
     */
    applyPanelRestrictions() {
        // Hide admin-only panels
        const adminPanels = document.querySelectorAll('.admin-panel');
        adminPanels.forEach(panel => {
            panel.style.display = this.uiConfig.show_admin_panel ? 'block' : 'none';
        });
        
        // Hide finance-only panels
        const financePanels = document.querySelectorAll('.finance-panel');
        financePanels.forEach(panel => {
            panel.style.display = this.uiConfig.show_finance_menu ? 'block' : 'none';
        });
    }
    
    /**
     * Apply mobile optimizations
     */
    applyMobileOptimizations() {
        if (this.uiConfig.mobile_optimized) {
            document.body.classList.add('mobile-optimized');
        }
        
        if (this.uiConfig.simplified_ui) {
            document.body.classList.add('simplified-ui');
        }
    }
    
    /**
     * Setup permission checking functions
     */
    setupPermissionChecks() {
        // Make permission checking available globally
        window.roleUI = {
            hasPermission: (permission) => this.hasPermission(permission),
            canApprovePayments: () => this.canApprovePayments(),
            canCloseMonth: () => this.canCloseMonth(),
            canViewAudit: () => this.canViewAudit(),
            canOverrideLocks: () => this.canOverrideLocks(),
            getUserRole: () => this.userRole,
            showPermissionError: (action) => this.showPermissionError(action)
        };
    }
    
    /**
     * Check if user has permission
     */
    hasPermission(permission) {
        return this.permissions[permission] === true;
    }
    
    /**
     * Check if user can approve payments
     */
    canApprovePayments() {
        return this.permissions['machinery_payment.approve'] === true;
    }
    
    /**
     * Check if user can close month
     */
    canCloseMonth() {
        return this.permissions['monthly_closing.close'] === true;
    }
    
    /**
     * Check if user can view audit
     */
    canViewAudit() {
        return this.permissions['audit.view'] === true;
    }
    
    /**
     * Check if user can override locks
     */
    canOverrideLocks() {
        return this.permissions['override.locks'] === true;
    }
    
    /**
     * Check button permission before action
     */
    checkButtonPermission(button) {
        const action = button.getAttribute('data-action');
        const permission = button.getAttribute('data-permission');
        
        if (action && this.restrictedActions.includes(action)) {
            e.preventDefault();
            this.showPermissionError(action);
            return false;
        }
        
        if (permission && !this.hasPermission(permission)) {
            e.preventDefault();
            this.showPermissionError(permission);
            return false;
        }
        
        return true;
    }
    
    /**
     * Check form permission before submission
     */
    checkFormPermission(form) {
        const permission = form.getAttribute('data-permission');
        
        if (permission && !this.hasPermission(permission)) {
            e.preventDefault();
            this.showPermissionError(permission);
            return false;
        }
        
        return true;
    }
    
    /**
     * Check link permission before navigation
     */
    checkLinkPermission(link) {
        const permission = link.getAttribute('data-permission');
        
        if (permission && !this.hasPermission(permission)) {
            e.preventDefault();
            this.showPermissionError(permission);
            return false;
        }
        
        return true;
    }
    
    /**
     * Show permission error message
     */
    showPermissionError(action) {
        const message = `You do not have permission to ${action.replace('.', ' ')}.`;
        
        // Create toast notification
        this.showToast(message, 'error');
        
        // Log to console for debugging
        console.warn(`Permission denied: ${action} for role ${this.userRole}`);
    }
    
    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.role-ui-toast');
        existingToasts.forEach(toast => toast.remove());
        
        // Create new toast
        const toast = document.createElement('div');
        toast.className = `role-ui-toast alert alert-${type === 'error' ? 'danger' : type} position-fixed`;
        toast.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        `;
        
        toast.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <span>${message}</span>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
    
    /**
     * Show/hide elements based on permission
     */
    toggleByPermission(selector, permission, show = true) {
        const elements = document.querySelectorAll(selector);
        const hasPermission = this.hasPermission(permission);
        
        elements.forEach(element => {
            element.style.display = (hasPermission && show) || (!hasPermission && !show) ? 'block' : 'none';
        });
    }
    
    /**
     * Disable/enable elements based on permission
     */
    disableByPermission(selector, permission, disable = true) {
        const elements = document.querySelectorAll(selector);
        const hasPermission = this.hasPermission(permission);
        
        elements.forEach(element => {
            element.disabled = (hasPermission && disable) || (!hasPermission && !disable);
            element.classList.toggle('disabled', element.disabled);
        });
    }
    
    /**
     * Update UI based on role change (for admin testing)
     */
    updateRole(newRole) {
        this.userRole = newRole;
        this.permissions = this.getRolePermissions(newRole);
        this.uiConfig = this.getRoleUIConfig(newRole);
        this.restrictedActions = this.getRestrictedActions(newRole);
        
        // Re-apply restrictions
        this.applyRoleRestrictions();
        
        // Show notification
        this.showToast(`Role changed to: ${newRole}`, 'info');
    }
    
    /**
     * Get role permissions (helper for testing)
     */
    getRolePermissions(role) {
        const permissions = {
            'admin': {
                'machinery.create': true,
                'machinery.edit': true,
                'machinery.delete': true,
                'machinery_payment.approve': true,
                'monthly_closing.close': true,
                'audit.view': true,
                'override.locks': true
            },
            'finance': {
                'machinery.view': true,
                'machinery_payment.approve': true,
                'monthly_closing.close': true,
                'audit.view': true
            },
            'supervisor': {
                'machinery.view': true,
                'machinery_payment.create': true,
                'dpr.create': true
            },
            'site_engineer': {
                'machinery.view': true,
                'dpr.create': true,
                'diesel.create': true
            },
            'operator': {
                'machinery.view': true,
                'dpr.create': true
            },
            'user': {
                'machinery.view': true,
                'dpr.view': true
            }
        };
        
        return permissions[role] || {};
    }
    
    /**
     * Get role UI config (helper for testing)
     */
    getRoleUIConfig(role) {
        const configs = {
            'admin': {
                'show_admin_panel': true,
                'show_finance_menu': true,
                'show_audit_menu': true,
                'show_advanced_controls': true
            },
            'finance': {
                'show_admin_panel': false,
                'show_finance_menu': true,
                'show_audit_menu': true,
                'show_advanced_controls': true
            },
            'supervisor': {
                'show_admin_panel': false,
                'show_finance_menu': false,
                'show_advanced_controls': false
            },
            'site_engineer': {
                'show_admin_panel': false,
                'show_finance_menu': false,
                'show_advanced_controls': false,
                'mobile_optimized': true
            },
            'operator': {
                'show_admin_panel': false,
                'show_finance_menu': false,
                'show_advanced_controls': false,
                'mobile_optimized': true,
                'simplified_ui': true
            },
            'user': {
                'show_admin_panel': false,
                'show_finance_menu': false,
                'show_advanced_controls': false,
                'read_only': true
            }
        };
        
        return configs[role] || {};
    }
    
    /**
     * Get restricted actions (helper for testing)
     */
    getRestrictedActions(role) {
        const restricted = {
            'operator': ['delete', 'approve', 'reject', 'close_month'],
            'site_engineer': ['delete', 'approve', 'reject', 'close_month'],
            'supervisor': ['delete', 'approve', 'reject', 'close_month'],
            'finance': ['delete'],
            'user': ['create', 'edit', 'delete', 'approve', 'reject', 'close_month']
        };
        
        return restricted[role] || [];
    }
}

// Initialize role-based UI when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.roleBasedUI = new RoleBasedUI();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RoleBasedUI;
}
