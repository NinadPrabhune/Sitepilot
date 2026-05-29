<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = Illuminate\Http\Request::capture()
);

// Now try to run the query
use App\Models\DailyProgressReport;

try {
    $query = DailyProgressReport::where('status', 0)
        ->with([
            'machinery:id,name',
            'consumptionMaster.details' => function ($q) {
                $q->select('id', 'daily_consumption_master_id', 'material_id', 'quantity')
                    ->with(['material' => function ($mq) {
                        $mq->select('id', 'name', 'unit_id')
                            ->with(['unit:id,name']);
                    }]);
            }
        ]);

    $reports = $query->orderBy('date', 'desc')->get();

    echo "Query succeeded. Found " . $reports->count() . " reports.\n";
} catch (\Exception $e) {
    echo "Query failed: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

?>