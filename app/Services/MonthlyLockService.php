<?php

namespace App\Services;

use App\Models\MonthlyLock;
use Illuminate\Support\Facades\Log;

class MonthlyLockService
{
    /**
     * Lock a month
     */
    public function lock(int $month, int $year, int $workspaceId, int $userId): MonthlyLock
    {
        // Check if already locked
        if ($this->isLocked($month, $year, $workspaceId)) {
            throw new \Exception("Month {$month}/{$year} is already locked");
        }

        $lock = MonthlyLock::lock($month, $year, $workspaceId, $userId);
        
        Log::info('Month locked', [
            'month' => $month,
            'year' => $year,
            'workspace_id' => $workspaceId,
            'locked_by' => $userId,
        ]);

        return $lock;
    }

    /**
     * Unlock a month
     */
    public function unlock(int $month, int $year, int $workspaceId): bool
    {
        $result = MonthlyLock::unlock($month, $year, $workspaceId);
        
        if ($result) {
            Log::info('Month unlocked', [
                'month' => $month,
                'year' => $year,
                'workspace_id' => $workspaceId,
            ]);
        }

        return $result;
    }

    /**
     * Check if month is locked
     */
    public function isLocked(int $month, int $year, int $workspaceId): bool
    {
        return MonthlyLock::isLocked($month, $year, $workspaceId);
    }

    /**
     * Get lock details
     */
    public function getLock(int $month, int $year, int $workspaceId): ?MonthlyLock
    {
        return MonthlyLock::getLock($month, $year, $workspaceId);
    }

    /**
     * Get all locked months for workspace
     */
    public function getLockedMonths(int $workspaceId): \Illuminate\Support\Collection
    {
        return MonthlyLock::where('workspace_id', $workspaceId)
            ->where('is_locked', true)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
    }

    /**
     * Validate if operation is allowed on month
     */
    public function validateOperation(int $month, int $year, int $workspaceId, string $operation): void
    {
        if ($this->isLocked($month, $year, $workspaceId)) {
            $allowedOperations = ['view', 'export'];
            
            if (!in_array($operation, $allowedOperations)) {
                throw new \Exception("Cannot {$operation}: Month {$month}/{$year} is locked");
            }
        }
    }
}
