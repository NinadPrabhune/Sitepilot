<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking attendance image data...\n";

$records = \Workdo\Hrm\Entities\Attendance::whereNotNull('clock_in_image')
    ->orWhereNotNull('clock_out_image')
    ->limit(5)
    ->get(['clock_in_image', 'clock_out_image']);

foreach ($records as $record) {
    echo "Clock In: " . ($record->clock_in_image ?? 'null') . " | Clock Out: " . ($record->clock_out_image ?? 'null') . "\n";
}

echo "\nDone.\n";
