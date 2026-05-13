<?php

namespace App\Policies;

use App\Models\DailyProgressReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Daily Progress Report Policy
 * Role-based access control for DPR operations
 */
class DailyProgressReportPolicy
{
    /**
     * Determine whether the user can view any DPRs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super admin', 'admin', 'company', 'site engineer', 'accounts']);
    }

    /**
     * Determine whether the user can view the DPR.
     */
    public function view(User $user, DailyProgressReport $dpr): bool
    {
        // Admin and accounts can view all DPRs
        if ($user->hasRole(['super admin', 'admin', 'company', 'accounts'])) {
            return true;
        }
        
        // Site engineers can only view DPRs from their sites
        if ($user->hasRole('site engineer')) {
            return $user->sites()->where('id', $dpr->site_id)->exists();
        }
        
        return false;
    }

    /**
     * Determine whether the user can create DPRs.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super admin', 'admin', 'company', 'site engineer']);
    }

    /**
     * Determine whether the user can update the DPR.
     */
    public function update(User $user, DailyProgressReport $dpr): bool
    {
        // Admin can always update
        if ($user->hasRole(['super admin', 'admin', 'company'])) {
            return true;
        }
        
        // Accounts can update before approval
        if ($user->hasRole('accounts') && !$dpr->is_locked) {
            return true;
        }
        
        // Site engineers can only update their own unlocked DPRs
        if ($user->hasRole('site engineer') && !$dpr->is_locked) {
            return $dpr->created_by === $user->id && 
                   $user->sites()->where('id', $dpr->site_id)->exists();
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the DPR.
     */
    public function delete(User $user, DailyProgressReport $dpr): bool
    {
        // Only admin can delete DPRs
        if (!$user->hasRole(['super admin', 'admin'])) {
            return false;
        }
        
        // Cannot delete if financially linked
        if ($dpr->is_locked || $dpr->hasPaymentRequest() || $dpr->hasLedgerEntries()) {
            return false;
        }
        
        return true;
    }

    /**
     * Determine whether the user can approve the DPR.
     */
    public function approve(User $user, DailyProgressReport $dpr): bool
    {
        return $user->hasRole(['super admin', 'admin', 'accounts']);
    }

    /**
     * Determine whether the user can lock the DPR.
     */
    public function lock(User $user, DailyProgressReport $dpr): bool
    {
        return $user->hasRole(['super admin', 'admin', 'accounts']);
    }

    /**
     * Determine whether the user can unlock the DPR.
     */
    public function unlock(User $user, DailyProgressReport $dpr): bool
    {
        return $user->hasRole(['super admin', 'admin']);
    }

    /**
     * Determine whether the user can reverse the DPR.
     */
    public function reverse(User $user, DailyProgressReport $dpr): bool
    {
        return $user->hasRole(['super admin', 'admin', 'accounts']);
    }

    /**
     * Determine whether the user can view DPR reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasRole(['super admin', 'admin', 'company', 'accounts']);
    }

    /**
     * Determine whether the user can export DPR data.
     */
    public function export(User $user): bool
    {
        return $user->hasRole(['super admin', 'admin', 'company', 'accounts']);
    }

    /**
     * Determine whether the user can access DPR audit logs.
     */
    public function viewAuditLogs(User $user): bool
    {
        return $user->hasRole(['super admin', 'admin', 'accounts']);
    }

    /**
     * Determine whether the user can run integrity checks.
     */
    public function runIntegrityChecks(User $user): bool
    {
        return $user->hasRole(['super admin', 'admin', 'accounts']);
    }

    /**
     * Determine whether the user can manage financial periods.
     */
    public function manageFinancialPeriods(User $user): bool
    {
        return $user->hasRole(['super admin', 'admin', 'accounts']);
    }

    /**
     * Determine whether the user can manage machinery rates.
     */
    public function manageMachineryRates(User $user): bool
    {
        return $user->hasRole(['super admin', 'admin', 'accounts']);
    }
}
