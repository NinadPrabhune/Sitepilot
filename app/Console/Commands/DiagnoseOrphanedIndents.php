<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Indent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiagnoseOrphanedIndents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'indent:diagnose-orphaned';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose indents that have no indent_items (orphaned indents)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Diagnosing orphaned indents...');

        // Find indents with no items
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
                $indent->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table(
            ['ID', 'Indent Number', 'Date', 'Status', 'Created At'],
            $tableData
        );

        $this->newLine();
        $this->warn('These indents exist in the database but have no associated items.');
        $this->info('To fix this issue:');
        $this->info('1. Check the Laravel logs for detailed error messages: tail -f storage/logs/laravel.log');
        $this->info('2. Review the indent creation process to identify why items are not being saved');
        $this->info('3. Use the indent:fix-orphaned command to delete or mark these indents');
        $this->newLine();

        // Log to file for record-keeping
        Log::warning('Orphaned indents detected', [
            'count' => $orphanedIndents->count(),
            'indent_ids' => $orphanedIndents->pluck('id')->toArray()
        ]);

        return Command::FAILURE;
    }
}
