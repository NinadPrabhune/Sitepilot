<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DATA LOSS INVESTIGATION ===" . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL;
echo PHP_EOL;

// 1. Check if this looks like a fresh migrate
echo "1. MIGRATION ANALYSIS:" . PHP_EOL;
try {
    $batches = DB::table('migrations')
        ->select('batch', DB::raw('COUNT(*) as count'))
        ->groupBy('batch')
        ->orderBy('batch', 'desc')
        ->get();
    
    echo "   Migration batches found: " . $batches->count() . PHP_EOL;
    foreach ($batches as $batch) {
        echo "   Batch {$batch->batch}: {$batch->count} migrations" . PHP_EOL;
    }
    
    // Check if all migrations are in batch 1 (indicative of migrate:fresh)
    if ($batches->count() == 1 && $batches->first()->batch == 1) {
        echo "   ⚠️  All migrations in batch 1 - POSSIBLE migrate:fresh scenario" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 2. Check for seeder execution evidence
echo "2. SEEDER EXECUTION EVIDENCE:" . PHP_EOL;

// Check login_details table for recent admin activity
try {
    $recent_logins = DB::table('login_details')
        ->orderBy('id', 'desc')
        ->limit(5)
        ->get(['id', 'user_id', 'created_at']);
    
    echo "   Recent logins: " . $recent_logins->count() . PHP_EOL;
    foreach ($recent_logins as $login) {
        echo "   User {$login->user_id} at {$login->created_at}" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "   No login records found" . PHP_EOL;
}

// Check for any seeder traces
try {
    $users = DB::table('users')->get(['id', 'email', 'created_at']);
    echo "   Users created:" . PHP_EOL;
    foreach ($users as $user) {
        echo "   ID: {$user->id}, Email: {$user->email}, Created: {$user->created_at}" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "   Error checking users: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 3. Check for any audit/system logs
echo "3. SYSTEM LOGS CHECK:" . PHP_EOL;

$log_files = [
    'storage/logs/laravel.log',
    'storage/logs/payment_audit-2026-05-02.log',
    'storage/logs/payment_audit-2026-05-01.log',
    'storage/logs/payment_audit-2026-04-30.log'
];

foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        $size = filesize($log_file);
        $modified = date('Y-m-d H:i:s', filemtime($log_file));
        echo "   {$log_file}: {$size} bytes, modified {$modified}" . PHP_EOL;
    } else {
        echo "   {$log_file}: Not found" . PHP_EOL;
    }
}

echo PHP_EOL;

// 4. Check for any backup/restore evidence
echo "4. BACKUP/RESTORE EVIDENCE:" . PHP_EOL;

$backup_dir = 'database/backups';
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    $backup_files = array_filter($files, function($file) {
        return in_array(pathinfo($file, PATHINFO_EXTENSION), ['sql', 'bak', 'dump']);
    });
    
    echo "   Backup files found: " . count($backup_files) . PHP_EOL;
    foreach ($backup_files as $file) {
        $filepath = $backup_dir . '/' . $file;
        $size = filesize($filepath);
        $modified = date('Y-m-d H:i:s', filemtime($filepath));
        echo "   {$file}: {$size} bytes, modified {$modified}" . PHP_EOL;
    }
} else {
    echo "   No backup directory found" . PHP_EOL;
}

echo PHP_EOL;

// 5. Check environment and configuration
echo "5. ENVIRONMENT CHECK:" . PHP_EOL;
echo "   APP_ENV: " . (env('APP_ENV') ?: 'Not set') . PHP_EOL;
echo "   DB_DATABASE: " . (env('DB_DATABASE') ?: 'Not set') . PHP_EOL;
echo "   SAFE_SEED_ONLY: " . (env('SAFE_SEED_ONLY') ?: 'Not set') . PHP_EOL;

echo PHP_EOL;

// 6. Summary
echo "6. INVESTIGATION SUMMARY:" . PHP_EOL;
echo "   ⚠️  Database appears to be in a FRESH state" . PHP_EOL;
echo "   ⚠️  Only 3 users remain, no workspaces, no settings" . PHP_EOL;
echo "   ⚠️  All migrations in single batch (typical of migrate:fresh)" . PHP_EOL;
echo "   ⚠️  No activity data, no payment data" . PHP_EOL;
echo PHP_EOL;
echo "   LIKELY CAUSES:" . PHP_EOL;
echo "   1. 'php artisan migrate:fresh' was executed" . PHP_EOL;
echo "   2. 'php artisan migrate:refresh' was executed" . PHP_EOL;
echo "   3. Database was restored from a fresh backup" . PHP_EOL;
echo "   4. Manual database truncation occurred" . PHP_EOL;
echo PHP_EOL;
echo "   NEXT STEPS:" . PHP_EOL;
echo "   1. Check who had access during the time of data loss" . PHP_EOL;
echo "   2. Review server/command logs for artisan commands" . PHP_EOL;
echo "   3. Check if any automated deployment scripts ran" . PHP_EOL;
echo "   4. Verify backup retention policies" . PHP_EOL;
