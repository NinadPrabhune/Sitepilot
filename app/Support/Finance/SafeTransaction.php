<?php

namespace App\Support\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait SafeTransaction
{
    protected function safeTransaction(callable $callback)
    {
        try {
            return DB::transaction($callback);
        } catch (\Exception $e) {
            Log::error('Transaction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
