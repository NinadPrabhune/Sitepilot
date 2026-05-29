<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/daily-progress-reports', 'GET');
$request->headers->set('Accept', 'application/json');

try {
    $controller = $app->make(App\Http\Controllers\Api\DailyProgressReportApiController::class);
    $response = $controller->index($request);
    $content = $response->getContent();
    echo $content . "\n";
} catch (Exception $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
