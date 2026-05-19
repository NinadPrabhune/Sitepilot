<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Initialize Laravel
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DATABASE TABLES CHECK ===\n\n";

echo "leave_request_dates table: " . (Schema::hasTable('leave_request_dates') ? 'EXISTS' : 'MISSING') . "\n";
echo "attendances table: " . (Schema::hasTable('attendances') ? 'EXISTS' : 'MISSING') . "\n";

if (Schema::hasTable('leave_request_dates')) {
    $row = DB::select("SELECT COUNT(*) as cnt FROM leave_request_dates")[0];
    echo "leave_request_dates count: {$row->cnt}\n";
}

$columns = DB::select("DESCRIBE attendances");
echo "\nAttendances columns:\n";
foreach ($columns as $col) {
    echo "  - {$col->Field}\n";
}

// Check if columns exist
$hasLeaveRequestId = Schema::hasColumn('attendances', 'leave_request_id');
$hasLeaveRequestDateId = Schema::hasColumn('attendances', 'leave_request_date_id');

echo "\nleave_request_id column: " . ($hasLeaveRequestId ? 'EXISTS' : 'MISSING') . "\n";
echo "leave_request_date_id column: " . ($hasLeaveRequestDateId ? 'EXISTS' : 'MISSING') . "\n";