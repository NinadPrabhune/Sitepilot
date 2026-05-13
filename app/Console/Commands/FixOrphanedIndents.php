<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Indent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixOrphanedIndents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'indent:fix-orphaned {--delete : Permanently delete orphaned indents} {--mark : Mark orphaned indents with rejection reason}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix orphaned indents (indents without indent_items)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding orphaned indents...');

        $orphanedIndents = Indent::whereDoesntHave('items')->get();

        if ($orphanedIndents->isEmpty()) {
            $this->info('✓ No orphaned indents found.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$orphanedIndents->count()} orphaned indent(s):");
        $this->newLine();

        $tableData = [];
        foreach ($orphanedIndents as $indent) {
            $tableData[] = [
                $indent->id,
                $indent->indent_number,
                $indent->indent_date->format('Y-m-d'),
                $indent->status,
            ];
        }

        $this->table(
            ['ID', 'Indent Number', 'Date', 'Status'],
            $tableData
        );

        $this->newLine();

        if (!$this->option('delete') && !$this->option('mark')) {
            $this->error('Please specify an action: --delete to permanently delete, or --mark to add rejection reason');
            $this->info('Example: php artisan indent:fix-orphaned --delete');
            $this->info('Example: php artisan indent:fix-orphaned --mark');
            return Command::FAILURE;
        }

        if ($this->option('delete')) {
            if (!$this->confirm("Are you sure you want to permanently delete {$orphanedIndents->count()} orphaned indent(s)?")) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }

            DB::beginTransaction();
            try {
                $deletedCount = 0;
                foreach ($orphanedIndents as $indent) {
                    $indent->delete();
                    $deletedCount++;
                    $this->line("Deleted indent ID: {$indent->id} ({$indent->indent_number})");
                }

                DB::commit();
                $this->newLine();
                $this->info("✓ Successfully deleted {$deletedCount} orphaned indent(s).");

                Log::info('Orphaned indents deleted', ['count' => $deletedCount]);
                return Command::SUCCESS;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to delete orphaned indents: {$e->getMessage()}");
                Log::error('Failed to delete orphaned indents', ['error' => $e->getMessage()]);
                return Command::FAILURE;
            }
        }

        if ($this->option('mark')) {
            DB::beginTransaction();
            try {
                $markedCount = 0;
                foreach ($orphanedIndents as $indent) {
                    $indent->update([
                        'rejection_reason' => 'Orphaned indent - no items were created due to system error',
                        'status' => 'Closed'
                    ]);
                    $markedCount++;
                    $this->line("Marked indent ID: {$indent->id} ({$indent->indent_number})");
                }

                DB::commit();
                $this->newLine();
                $this->info("✓ Successfully marked {$markedCount} orphaned indent(s).");

                Log::info('Orphaned indents marked', ['count' => $markedCount]);
                return Command::SUCCESS;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to mark orphaned indents: {$e->getMessage()}");
                Log::error('Failed to mark orphaned indents', ['error' => $e->getMessage()]);
                return Command::FAILURE;
            }
        }

        return Command::FAILURE;
    }
}
