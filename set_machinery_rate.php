<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Machinery;

// Set rate for Machinery 18
$machinery = Machinery::find(18);
if ($machinery) {
    $machinery->rate = 1000; // ₹1000 per hour
    $machinery->save();
    echo "✅ Set Machinery ID 18 rate to ₹1000/hour\n";
    echo "Machinery: {$machinery->name}\n";
    echo "Rate: {$machinery->rate}\n";
} else {
    echo "❌ Machinery ID 18 not found\n";
}
