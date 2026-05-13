<?php

namespace App\Support\Finance;

use Illuminate\Support\Facades\Log;

trait HandlesDeadlocks
{
    protected function withDeadlockRetry(callable $callback, int $maxAttempts = 3, int $delayMs = 100)
    {
        $attempt = 1;
        
        while ($attempt <= $maxAttempts) {
            try {
                return $callback();
            } catch (\Illuminate\Database\QueryException $e) {
                if ($this->isDeadlock($e)) {
                    Log::warning('Deadlock detected, retrying', [
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                    ]);
                    
                    if ($attempt >= $maxAttempts) {
                        throw $e;
                    }
                    
                    usleep($delayMs * $attempt * 1000);
                    $attempt++;
                } else {
                    throw $e;
                }
            }
        }
        
        throw new \Exception('Transaction failed after ' . $maxAttempts . ' attempts');
    }
    
    protected function isDeadlock(\Illuminate\Database\QueryException $e): bool
    {
        return $e->getCode() == 40001 || 
               str_contains($e->getMessage(), 'Deadlock') || 
               str_contains($e->getMessage(), 'deadlock');
    }
}
