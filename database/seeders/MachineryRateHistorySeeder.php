<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Machinery Rate History Seeder
 * Populates initial rate history for existing machinery
 */
class MachineryRateHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Get all machinery with their current rates
            $machinery = DB::table('machineries')
                ->select('id', 'rate', 'created_at')
                ->whereNotNull('rate')
                ->get();

            $seededCount = 0;
            $skippedCount = 0;

            foreach ($machinery as $machine) {
                // Check if rate history already exists
                $existingHistory = DB::table('machinery_rate_history')
                    ->where('machinery_id', $machine->id)
                    ->exists();

                if ($existingHistory) {
                    $skippedCount++;
                    continue;
                }

                // Create initial rate history entry
                $effectiveDate = is_string($machine->created_at) 
                    ? substr($machine->created_at, 0, 10) 
                    : $machine->created_at->format('Y-m-d');
                
                DB::table('machinery_rate_history')->insert([
                    'machinery_id' => $machine->id,
                    'rate' => $machine->rate,
                    'effective_from' => $effectiveDate,
                    'created_by' => 1, // System user
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $seededCount++;
            }

            Log::info('Machinery Rate History Seeder completed', [
                'seeded_count' => $seededCount,
                'skipped_count' => $skippedCount,
                'total_machinery' => $machinery->count(),
            ]);

            $this->command->info("Machinery Rate History seeded: {$seededCount} entries created, {$skippedCount} skipped (already exists)");

        } catch (\Exception $e) {
            Log::error('Machinery Rate History Seeder failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->command->error('Seeder failed: ' . $e->getMessage());
        }
    }
}
