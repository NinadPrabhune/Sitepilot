<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigratePaymentRequestsTo3Step extends Seeder
{
    /**
     * Run the database seeds for 3-step workflow migration.
     */
    public function run(): void
    {
        Log::info('Starting 3-step workflow migration for machinery payment requests');
        
        $migratedCount = 0;
        $errors = [];
        
        try {
            DB::transaction(function () use (&$migratedCount, &$errors) {
                // Convert verified → approved
                $verifiedCount = MachineryPaymentRequest::where('status', 'verified')->update([
                    'status' => 'approved',
                    'approved_by' => DB::raw('verified_by'),
                    'approved_at' => DB::raw('verified_at')
                ]);
                
                $migratedCount += $verifiedCount;
                Log::info("Converted {$verifiedCount} verified requests to approved");
                
                // Convert locked → approved  
                $lockedCount = MachineryPaymentRequest::where('status', 'locked')->update([
                    'status' => 'approved',
                    'approved_by' => DB::raw('locked_by'),
                    'approved_at' => DB::raw('locked_at')
                ]);
                
                $migratedCount += $lockedCount;
                Log::info("Converted {$lockedCount} locked requests to approved");
                
                // Handle any edge cases
                $edgeCases = MachineryPaymentRequest::whereIn('status', ['hold', 'draft'])
                    ->where(function($query) {
                        $query->whereNotNull('verified_by')
                              ->orWhereNotNull('locked_by');
                    })
                    ->get();
                
                foreach ($edgeCases as $request) {
                    if ($request->verified_by) {
                        $request->update([
                            'status' => 'approved',
                            'approved_by' => $request->verified_by,
                            'approved_at' => $request->verified_at
                        ]);
                        $migratedCount++;
                    } elseif ($request->locked_by) {
                        $request->update([
                            'status' => 'approved',
                            'approved_by' => $request->locked_by,
                            'approved_at' => $request->locked_at
                        ]);
                        $migratedCount++;
                    }
                }
                
                Log::info("Handled {$edgeCases->count()} edge cases");
                
                // Validate migration integrity
                $invalidStates = MachineryPaymentRequest::whereIn('status', ['verified', 'locked'])->count();
                if ($invalidStates > 0) {
                    $errors[] = "Found {$invalidStates} requests still in old states";
                    Log::error("Migration integrity check failed: {$invalidStates} requests in old states");
                }
                
                // Log final status distribution
                $statusCounts = MachineryPaymentRequest::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();
                
                Log::info('Final status distribution after migration', $statusCounts);
            });
            
            Log::info("3-step workflow migration completed successfully. Total migrated: {$migratedCount}");
            
            if (!empty($errors)) {
                Log::warning('Migration completed with errors', $errors);
            }
            
        } catch (\Exception $e) {
            Log::error('3-step workflow migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Reverse the migration (for rollback purposes)
     */
    public function rollback(): void
    {
        Log::info('Rolling back 3-step workflow migration');
        
        try {
            DB::transaction(function () {
                // Convert approved back to verified if it has verified data
                $verifiedRollback = MachineryPaymentRequest::where('status', 'approved')
                    ->whereNotNull('verified_by')
                    ->whereNull('locked_by')
                    ->update([
                        'status' => 'verified',
                        'verified_by' => DB::raw('approved_by'),
                        'verified_at' => DB::raw('approved_at'),
                        'approved_by' => null,
                        'approved_at' => null
                    ]);
                
                // Convert approved back to locked if it has locked data
                $lockedRollback = MachineryPaymentRequest::where('status', 'approved')
                    ->whereNotNull('locked_by')
                    ->update([
                        'status' => 'locked',
                        'locked_by' => DB::raw('approved_by'),
                        'locked_at' => DB::raw('approved_at'),
                        'approved_by' => null,
                        'approved_at' => null
                    ]);
                
                Log::info("Rollback completed: {$verifiedRollback} to verified, {$lockedRollback} to locked");
            });
            
        } catch (\Exception $e) {
            Log::error('Rollback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
