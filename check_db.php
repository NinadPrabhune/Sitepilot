<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DATABASE INVESTIGATION REPORT ===" . PHP_EOL;
echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL;
echo PHP_EOL;

// Check key tables
$tables = [
    'users' => 'App\Models\User',
    'work_spaces' => 'App\Models\WorkSpace', 
    'settings' => 'App\Models\Setting',
    'migrations' => 'DB::table("migrations")',
    'activities' => 'App\Models\Activity',
    'purchase_invoices' => 'App\Models\PurchaseInvoice',
    'payments_module' => 'App\Models\PaymentsModule'
];

foreach ($tables as $table => $model) {
    try {
        if (strpos($model, 'DB::') === 0) {
            $count = DB::table($table)->count();
        } else {
            $count = $model::count();
        }
        echo sprintf("%-20s: %d records", $table, $count) . PHP_EOL;
    } catch (Exception $e) {
        echo sprintf("%-20s: ERROR - %s", $table, $e->getMessage()) . PHP_EOL;
    }
}

echo PHP_EOL;

// Check migration batches
try {
    $batches = DB::table('migrations')
        ->select('batch', DB::raw('COUNT(*) as count'))
        ->groupBy('batch')
        ->orderBy('batch', 'desc')
        ->get();
    
    echo "Migration Batches:" . PHP_EOL;
    foreach ($batches as $batch) {
        echo "  Batch {$batch->batch}: {$batch->count} migrations" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error checking migration batches: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// Check recent activity (if activities table exists)
try {
    $recent = DB::table('activities')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get(['id', 'type', 'user_id', 'created_at']);
    
    if ($recent->count() > 0) {
        echo "Recent Activities:" . PHP_EOL;
        foreach ($recent as $activity) {
            echo "  ID: {$activity->id}, Type: {$activity->type}, User: {$activity->user_id}, Time: {$activity->created_at}" . PHP_EOL;
        }
    } else {
        echo "No recent activities found" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "No activities table or error: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;
