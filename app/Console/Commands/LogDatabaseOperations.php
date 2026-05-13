<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LogDatabaseOperations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:log-database-operations {--check : Check current database status} {--watch : Watch for changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Log database operations and detect data loss';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('check')) {
            $this->checkDatabaseStatus();
        } elseif ($this->option('watch')) {
            $this->watchDatabaseChanges();
        } else {
            $this->logCurrentState();
        }
    }

    private function logCurrentState()
    {
        $this->info('Logging current database state...');
        
        $tables = DB::select('SHOW TABLES');
        $totalTables = count($tables);
        $emptyTables = 0;
        $tablesWithData = 0;
        
        $tableStatus = [];
        
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            $count = DB::table($tableName)->count();
            
            if ($count == 0) {
                $emptyTables++;
            } else {
                $tablesWithData++;
            }
            
            $tableStatus[] = [
                'table' => $tableName,
                'count' => $count
            ];
        }
        
        $logData = [
            'timestamp' => Carbon::now()->toISOString(),
            'total_tables' => $totalTables,
            'tables_with_data' => $tablesWithData,
            'empty_tables' => $emptyTables,
            'user_count' => DB::table('users')->count(),
            'critical_tables' => [
                'users' => DB::table('users')->count(),
                'work_spaces' => DB::table('work_spaces')->count(),
                'settings' => DB::table('settings')->count(),
                'migrations' => DB::table('migrations')->count()
            ]
        ];
        
        Log::critical('DATABASE_STATE_SNAPSHOT', $logData);
        
        $this->info('Database state logged to Laravel logs');
        $this->info("Total tables: {$totalTables}");
        $this->info("Tables with data: {$tablesWithData}");
        $this->info("Empty tables: {$emptyTables}");
        $this->info("Users count: {$logData['user_count']}");
        
        // Alert if critical tables are empty
        if ($logData['user_count'] == 0) {
            Log::emergency('CRITICAL_DATA_LOSS_DETECTED', [
                'message' => 'Users table is empty!',
                'timestamp' => Carbon::now()->toISOString(),
                'user_count' => 0
            ]);
            $this->error('⚠️  CRITICAL: Users table is empty!');
        }
        
        if ($emptyTables > ($totalTables * 0.8)) {
            Log::emergency('MASSIVE_DATA_LOSS_DETECTED', [
                'message' => 'More than 80% of tables are empty',
                'timestamp' => Carbon::now()->toISOString(),
                'empty_tables' => $emptyTables,
                'total_tables' => $totalTables,
                'percentage_empty' => round(($emptyTables / $totalTables) * 100, 2)
            ]);
            $this->error('⚠️  CRITICAL: Massive data loss detected!');
        }
    }

    private function checkDatabaseStatus()
    {
        $this->info('Checking database status...');
        
        $userCount = DB::table('users')->count();
        $workspaceCount = DB::table('work_spaces')->count();
        $settingsCount = DB::table('settings')->count();
        
        $this->table(
            ['Table', 'Record Count', 'Status'],
            [
                ['users', $userCount, $userCount > 0 ? '✅ OK' : '❌ EMPTY'],
                ['work_spaces', $workspaceCount, $workspaceCount > 0 ? '✅ OK' : '❌ EMPTY'],
                ['settings', $settingsCount, $settingsCount > 0 ? '✅ OK' : '❌ EMPTY'],
            ]
        );
        
        if ($userCount == 0) {
            $this->error('Users table is empty - immediate action required!');
            Log::alert('DATABASE_CHECK_USERS_EMPTY', [
                'timestamp' => Carbon::now()->toISOString(),
                'user_count' => 0
            ]);
        }
    }

    private function watchDatabaseChanges()
    {
        $this->info('Watching for database changes... (Press Ctrl+C to stop)');
        
        $previousState = [];
        
        while (true) {
            $currentState = [
                'users' => DB::table('users')->count(),
                'work_spaces' => DB::table('work_spaces')->count(),
                'settings' => DB::table('settings')->count(),
                'timestamp' => Carbon::now()->timestamp
            ];
            
            if (!empty($previousState)) {
                foreach ($currentState as $table => $count) {
                    if ($count !== $previousState[$table]) {
                        $change = $count - $previousState[$table];
                        $direction = $change > 0 ? 'increased' : 'decreased';
                        
                        Log::warning('TABLE_COUNT_CHANGED', [
                            'table' => $table,
                            'previous_count' => $previousState[$table],
                            'current_count' => $count,
                            'change' => $change,
                            'direction' => $direction,
                            'timestamp' => Carbon::now()->toISOString()
                        ]);
                        
                        $this->warn("Table '{$table}' {$direction} by " . abs($change) . " records");
                        
                        if ($table === 'users' && $count == 0) {
                            Log::emergency('USERS_TABLE_WIPED', [
                                'message' => 'Users table was wiped during monitoring',
                                'timestamp' => Carbon::now()->toISOString(),
                                'previous_count' => $previousState[$table],
                                'current_count' => 0
                            ]);
                            $this->error('🚨 EMERGENCY: Users table was wiped!');
                        }
                    }
                }
            }
            
            $previousState = $currentState;
            sleep(5); // Check every 5 seconds
        }
    }
}
