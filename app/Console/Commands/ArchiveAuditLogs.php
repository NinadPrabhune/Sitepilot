<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveAuditLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'numbering:archive-audit-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive numbering audit logs older than 90 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Archive logs older than 90 days
        $deleted = DB::table('numbering_config_logs')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();
        
        $this->info("Archived {$deleted} audit log entries successfully");
        
        return 0;
    }
}
