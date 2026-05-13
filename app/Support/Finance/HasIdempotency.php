<?php

namespace App\Support\Finance;

trait HasIdempotency
{
    protected function checkIdempotency(string $key, string $modelClass, string $column = 'idempotency_key', ?int $workspaceId = null)
    {
        if (empty($key)) {
            return null;
        }
        
        $query = $modelClass::where($column, $key);
        
        // CRITICAL: Scope idempotency to workspace to prevent cross-context conflicts
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }
        
        return $query->first();
    }
    
    protected function generateIdempotencyKey(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * CRITICAL: Handle duplicate idempotency key safely
     * Turns idempotency into safe retry mechanism, not just constraint
     */
    protected function handleIdempotencyConflict(\Illuminate\Database\QueryException $e, int $workspaceId, string $idempotencyKey, string $modelClass)
    {
        if ($this->isDuplicateIdempotency($e)) {
            return $modelClass::where('workspace_id', $workspaceId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
        }
        throw $e;
    }
    
    protected function isDuplicateIdempotency(\Illuminate\Database\QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'Duplicate entry') ||
               str_contains($e->getMessage(), 'UNIQUE constraint');
    }
}
