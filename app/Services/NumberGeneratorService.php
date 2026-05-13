<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NumberGeneratorService
{
    /**
     * Generate next number (PREVIEW ONLY - no DB writes)
     * Accepts generic scopeId and dynamically determines scope column
     */
    public function generate(string $module, ?int $scopeId = null): string
    {
        if ($scopeId === null) {
            throw new \InvalidArgumentException("scope_id is required for {$module} number generation.");
        }

        $scopeType = config("numbering.scopes.{$module}", 'site');
        $scopeColumn = $this->getScopeColumn($module);
        
        // Validate scope exists
        $this->validateScope($scopeType, $scopeId);
        
        $config = $this->getNumberingConfig($module, $scopeType, $scopeId);
        $prefix = $config['prefix'];
        $startingNumber = $config['starting_number'];
        $paddingLength = $config['padding_length'];
        
        $lastNumber = $this->getLastNumber($module, $scopeId, $scopeColumn);
        
        if ($lastNumber) {
            $lastPrefix = $this->extractPrefix($lastNumber);
            if ($this->normalizePrefix($lastPrefix) !== $this->normalizePrefix($prefix)) {
                $nextNumber = $startingNumber;
            } else {
                $numericPart = $this->extractNumericPart($lastNumber, '');
                $nextNumber = $numericPart + 1;
            }
        } else {
            $nextNumber = $startingNumber;
        }
        
        $nextNumber = max($startingNumber, $nextNumber);
        $generatedNumber = $this->formatNumber($prefix, $nextNumber, $paddingLength);
        
        if (config('app.debug')) {
            Log::debug('Number Generated (Preview)', [
                'module' => $module,
                'scope_column' => $scopeColumn,
                'scope_id' => $scopeId,
                'number' => $generatedNumber,
            ]);
        }
        
        return $generatedNumber;
    }

    /**
     * Get scope column for module
     */
    private function getScopeColumn(string $module): string
    {
        $scopeType = config("numbering.scopes.{$module}", 'site');
        
        return match($scopeType) {
            'workspace' => 'workspace_id',
            default => 'site_id',
        };
    }

    /**
     * Validate scope exists
     */
    private function validateScope(string $scopeType, int $scopeId): void
    {
        if ($scopeType === 'workspace') {
            if (!\App\Models\WorkSpace::where('id', $scopeId)->exists()) {
                throw new \InvalidArgumentException("Workspace with ID {$scopeId} does not exist");
            }
        } else {
            if (!\Workdo\Taskly\Entities\Project::where('id', $scopeId)->exists()) {
                throw new \InvalidArgumentException("Site with ID {$scopeId} does not exist");
            }
        }
    }

    /**
     * Get numbering config with fallback logic
     * CRITICAL: Use versioned cache key
     */
    private function getNumberingConfig(string $module, string $scopeType, ?int $scopeId): array
    {
        // CRITICAL: Use versioned cache key
        $version = Cache::get("numbering_settings_version_{$module}", 1);
        $scopeIdValue = $scopeId ?? 'global';
        $cacheKey = "numbering_config_{$module}_{$scopeType}_{$scopeIdValue}_v{$version}";
        
        return Cache::remember($cacheKey, 3600, function () use ($module, $scopeType, $scopeId) {
            // Try scope-specific config first
            $config = DB::table('numbering_configs')
                ->where('module', $module)
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->first();
            
            // Fallback to global config
            if (!$config) {
                $config = DB::table('numbering_configs')
                    ->where('module', $module)
                    ->where('scope_type', $scopeType)
                    ->whereNull('scope_id')
                    ->first();
            }
            
            // Fallback to defaults
            if (!$config) {
                $defaults = [
                    'po' => ['prefix' => 'PO', 'starting_number' => 1, 'padding_length' => 5],
                    'machinery_payment' => ['prefix' => 'MPAY-', 'starting_number' => 1, 'padding_length' => 5],
                    'indent' => ['prefix' => 'IND', 'starting_number' => 1, 'padding_length' => 5],
                    'grn' => ['prefix' => 'GRN-', 'starting_number' => 1, 'padding_length' => 5],
                    'invoice' => ['prefix' => 'INV-', 'starting_number' => 1, 'padding_length' => 5],
                    'payment' => ['prefix' => 'PAY-', 'starting_number' => 1, 'padding_length' => 5],
                ];
                return $defaults[$module] ?? ['prefix' => strtoupper($module), 'starting_number' => 1, 'padding_length' => 5];
            }
            
            return [
                'prefix' => $config->prefix,
                'starting_number' => $config->starting_number,
                'padding_length' => $config->padding_length,
            ];
        });
    }

    /**
     * Invalidate numbering config cache
     * CRITICAL: Only increment version - forget() is not needed with versioned keys
     */
    public function invalidateConfigCache(string $module, string $scopeType, ?int $scopeId = null): void
    {
        // Version bump alone invalidates all versioned cache keys
        Cache::increment("numbering_settings_version_{$module}");
    }

    /**
     * Get last number for module and scope (data-driven approach)
     * Fetches last 100 records and extracts max numeric value
     * System starts fresh, so no full scan fallback needed
     * This prevents issues with manual inserts, migrations, or backdated records
     */
    private function getLastNumber(string $module, ?int $scopeId, string $scopeColumn): ?string
    {
        $tableMap = [
            'po' => 'purchase_orders',
            'machinery_payment' => 'machinery_payment_requests',
            'indent' => 'indents',
            'grn' => 'grns',
            'invoice' => 'purchase_invoices',
            'payment' => 'payments_module',
        ];
        
        $columnMap = [
            'po' => 'po_number',
            'machinery_payment' => 'payment_number',
            'indent' => 'indent_number',
            'grn' => 'grn_number',
            'invoice' => 'invoice_number',
            'payment' => 'payment_number',
        ];
        
        $table = $tableMap[$module] ?? null;
        $column = $columnMap[$module] ?? null;
        
        if (!$table || !$column) {
            Log::warning("Unknown module for number generation: {$module}");
            return null;
        }
        
        // Fetch last 100 records for this scope
        $query = DB::table($table)
            ->select($column)
            ->where($scopeColumn, $scopeId)
            ->orderBy('id', 'desc')
            ->limit(100);
        
        $results = $query->get();
        
        if ($results->isEmpty()) {
            return null;
        }
        
        // Extract max numeric value from records
        $maxNumeric = 0;
        $lastNumber = null;
        
        foreach ($results as $result) {
            $number = $result->$column;
            if (empty($number)) {
                continue; // Skip null or empty numbers
            }
            // CRITICAL: Always pass prefix context for safe extraction
            $numericPart = $this->extractNumericPart($number, '');
            if (!is_numeric($numericPart)) {
                continue; // Skip if parsing fails
            }
            if ($numericPart > $maxNumeric) {
                $maxNumeric = $numericPart;
                $lastNumber = $number;
            }
        }
        
        return $lastNumber;
    }

    /**
     * Extract numeric part from number string
     */
    private function extractNumericPart(string $number, string $prefix): int
    {
        preg_match('/(\d+)$/', $number, $matches);
        return isset($matches[1]) ? intval($matches[1]) : 0;
    }

    /**
     * Format number with prefix and padding
     */
    private function formatNumber(string $prefix, int $number, int $paddingLength): string
    {
        return $prefix . sprintf("%0{$paddingLength}d", $number);
    }

    /**
     * Generate number with DB row locking (dynamic scope column)
     */
    private function generateWithLock(string $module, int $scopeId): string
    {
        $scopeColumn = $this->getScopeColumn($module);
        $scopeType = config("numbering.scopes.{$module}", 'site');
        
        $config = $this->getNumberingConfig($module, $scopeType, $scopeId);
        $prefix = $config['prefix'];
        $startingNumber = $config['starting_number'];
        $paddingLength = $config['padding_length'];
        
        $tableMap = [
            'po' => 'purchase_orders',
            'machinery_payment' => 'machinery_payment_requests',
            'indent' => 'indents',
            'grn' => 'grns',
            'invoice' => 'purchase_invoices',
            'payment' => 'payments_module',
        ];
        
        $columnMap = [
            'po' => 'po_number',
            'machinery_payment' => 'payment_number',
            'indent' => 'indent_number',
            'grn' => 'grn_number',
            'invoice' => 'invoice_number',
            'payment' => 'payment_number',
        ];
        
        $table = $tableMap[$module] ?? null;
        $column = $columnMap[$module] ?? null;
        
        if (!$table || !$column) {
            throw new \InvalidArgumentException("Unknown module: {$module}");
        }
        
        // CRITICAL: Set session-level lock timeout to prevent deadlocks
        DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
        
        // Lock the last 100 records for this scope (dynamic scope column)
        $query = DB::table($table)
            ->select($column)
            ->where($scopeColumn, $scopeId)
            ->lockForUpdate()
            ->orderBy('id', 'desc')
            ->limit(100);
        
        $results = $query->get();
        
        // Extract max numeric value from locked records
        $maxNumeric = 0;
        $lastNumber = null;
        
        foreach ($results as $result) {
            $number = $result->$column;
            if (empty($number)) {
                continue; // Skip null or empty numbers
            }
            $numericPart = $this->extractNumericPart($number, '');
            if (!is_numeric($numericPart)) {
                continue;
            }
            if ($numericPart > $maxNumeric) {
                $maxNumeric = $numericPart;
                $lastNumber = $number;
            }
        }
        
        if ($lastNumber) {
            // CRITICAL: Normalize prefix comparison to handle PO vs PO- edge case
            $lastPrefix = $this->extractPrefix($lastNumber);
            if ($this->normalizePrefix($lastPrefix) !== $this->normalizePrefix($prefix)) {
                $nextNumber = $startingNumber;
            } else {
                $nextNumber = $maxNumeric + 1;
            }
        } else {
            $nextNumber = $startingNumber;
        }
        
        $nextNumber = max($startingNumber, $nextNumber);
        $generatedNumber = $this->formatNumber($prefix, $nextNumber, $paddingLength);
        
        if (config('app.debug')) {
            Log::debug('Number Generated with Lock', [
                'module' => $module,
                'scope_column' => $scopeColumn,
                'scope_id' => $scopeId,
                'number' => $generatedNumber,
            ]);
        }
        
        return $generatedNumber;
    }

    /**
     * Extract prefix from number (for comparison)
     */
    private function extractPrefix(string $number): string
    {
        preg_match('/^([A-Za-z-]+)/', $number, $matches);
        return $matches[1] ?? '';
    }

    /**
     * Normalize prefix for comparison (handles PO vs PO- edge case)
     */
    private function normalizePrefix(string $prefix): string
    {
        return rtrim($prefix, '-');
    }

    /**
     * Generate number with retry logic (includes idempotency check)
     * CRITICAL: Lock + insert must be in SAME transaction
     */
    public function generateWithRetry(string $module, int $scopeId, callable $createCallback, int $maxRetries = 3, ?string $idempotencyKey = null)
    {
        return DB::transaction(function () use ($module, $scopeId, $createCallback, $maxRetries, $idempotencyKey) {
            // Check idempotency first (inside transaction)
            if ($idempotencyKey) {
                $existing = $this->checkIdempotency($module, $idempotencyKey);
                if ($existing) {
                    Log::info('Idempotency hit - returning existing record', [
                        'module' => $module,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                    return $existing;
                }
            }
            
            for ($i = 0; $i < $maxRetries; $i++) {
                try {
                    $startTime = microtime(true);
                    
                    $number = $this->generateWithLock($module, $scopeId);
                    
                    $latency = microtime(true) - $startTime;
                    
                    // Check lock latency
                    $this->checkLockLatency($latency);
                    
                    $result = $createCallback($number);
                    
                    // Log metrics
                    $this->logMetrics($module, $scopeId, $latency, $i, $latency);
                    
                    return $result;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($this->isDuplicateKeyError($e)) {
                        Log::warning('Duplicate key error - retrying', [
                            'module' => $module,
                            'scope_id' => $scopeId,
                            'attempt' => $i + 1,
                        ]);
                        continue;
                    }
                    throw $e;
                } catch (\Exception $e) {
                    $this->logSkippedNumber($module, $scopeId, $e->getMessage());
                    throw $e;
                }
            }
            
            throw new \Exception("Failed to generate unique number for {$module} after {$maxRetries} retries");
        });
    }

    /**
     * Check idempotency key for existing record (scope-specific)
     */
    private function checkIdempotency(string $module, string $idempotencyKey): ?object
    {
        $tableMap = [
            'po' => 'purchase_orders',
            'machinery_payment' => 'machinery_payment_requests',
            'indent' => 'indents',
            'grn' => 'grns',
            'invoice' => 'purchase_invoices',
            'payment' => 'payments_module',
        ];
        
        $scopeColumnMap = [
            'po' => 'workspace_id',
            'machinery_payment' => 'site_id',
            'indent' => 'site_id',
            'grn' => 'site_id',
            'invoice' => 'site_id',
            'payment' => 'site_id',
        ];
        
        $table = $tableMap[$module] ?? null;
        $scopeColumn = $scopeColumnMap[$module] ?? null;
        
        if (!$table || !$scopeColumn) {
            return null;
        }
        
        // Get current scope ID from context
        $scopeId = $this->getCurrentScopeId($module);
        
        return DB::table($table)
            ->where('idempotency_key', $idempotencyKey)
            ->where($scopeColumn, $scopeId)
            ->first();
    }

    /**
     * Get current scope ID from context
     */
    private function getCurrentScopeId(string $module): int
    {
        $scopeType = config("numbering.scopes.{$module}", 'site');
        
        if ($scopeType === 'workspace') {
            return getActiveWorkSpace();
        }
        
        return getActiveProject();
    }

    /**
     * Check if error is duplicate key violation (strict check)
     */
    private function isDuplicateKeyError(\Exception $e): bool
    {
        if ($e instanceof \Illuminate\Database\QueryException) {
            $errorCode = $e->errorInfo[1] ?? null;
            // MySQL duplicate key error code = 1062
            return $errorCode == 1062;
        }
        return false;
    }

    /**
     * Generate number with lock preview (dry run with actual lock)
     * For admin testing to see real next number under lock
     * CRITICAL: Guard against production abuse
     */
    public function generateWithLockPreview(string $module, int $scopeId): string
    {
        // Guard: Only allow in non-production or for admin users
        if (app()->environment('production') && !auth()->user()?->hasRole(['super-admin', 'admin'])) {
            throw new \Exception('Lock preview only available for admins in production');
        }
        
        return DB::transaction(function () use ($module, $scopeId) {
            return $this->generateWithLock($module, $scopeId);
        });
    }

    /**
     * Log skipped number for audit trail
     */
    private function logSkippedNumber(string $module, int $scopeId, string $reason): void
    {
        try {
            DB::table('skipped_numbers')->insert([
                'module' => $module,
                'scope_id' => $scopeId,
                'number' => 'N/A',
                'reason' => $reason,
                'exception_message' => substr($reason, 0, 1000),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log skipped number', [
                'module' => $module,
                'scope_id' => $scopeId,
                'log_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check lock latency and trigger circuit breaker if needed
     */
    private function checkLockLatency(float $latency): void
    {
        $threshold = config('numbering.lock_latency_threshold', 1.0); // 1 second
        
        if ($latency > $threshold) {
            Log::critical('Lock latency exceeded threshold', [
                'latency_ms' => round($latency * 1000, 2),
                'threshold_ms' => $threshold * 1000,
            ]);
            
            // CRITICAL: Define explicit behavior when exceeded
            if (config('numbering.reject_on_high_latency', true)) {
                throw new \Exception('Number generation temporarily overloaded. Please retry in a few seconds.');
            }
            
            // Alternative: Fallback to queue if configured
            if (config('numbering.queue_on_high_latency', false)) {
                // Queue the number generation for later processing
                // Implementation depends on your queue setup
            }
        }
    }

    /**
     * Log metrics for observability
     */
    private function logMetrics(string $module, int $scopeId, float $generationTime, int $retryCount, float $lockLatency): void
    {
        Log::info('Number generation metrics', [
            'module' => $module,
            'scope_id' => $scopeId,
            'generation_time_ms' => round($generationTime * 1000, 2),
            'retry_count' => $retryCount,
            'lock_latency_ms' => round($lockLatency * 1000, 2),
            'user_id' => auth()->id(),
        ]);
    }
}
