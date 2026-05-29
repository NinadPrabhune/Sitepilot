<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

/** @var Illuminate\Contracts\Container\Container $container */
$container = $app;

// Make a fake request
$request = Illuminate\Http\Request::create('/api/daily-progress-reports', 'GET', [
    // Add any query parameters if needed
]);

// Set the user for authentication (we need to authenticate the request)
// We'll try to get a user from the database
use App\Models\User;
$user = User::first();
if (!$user) {
    echo "No user found in the database. Please create a user first.\n";
    exit(1);
}

// Set the user as the currently authenticated user for Sanctum
// We'll manually set the user on the request
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Also set the user for the auth facade
// We'll use the auth:sanctum guard
$auth = $container->make('auth');
$auth->setUser($user);

// Now instantiate the controller
$controller = $app->make(App\Http\Controllers\Api\DailyProgressReportApiController::class);

try {
    $response = $controller->index($request);
    echo "Controller executed successfully.\n";
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response content: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "Controller threw an exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

?>