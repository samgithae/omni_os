<?php
// Test PATCH endpoint via HTTP
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EmailMessage;

$msg = EmailMessage::where('approval_status', 'needs_content')
    ->whereHas('brand', function($q) { $q->where('slug', 'ujuziplus'); })
    ->first();

if (!$msg) {
    echo "No needs_content messages found.\n";
    exit(1);
}

echo "Testing PATCH on EmailMessage ID: {$msg->id}\n";
echo "Current approval_status: {$msg->approval_status}\n\n";

// Simulate the HTTP request
$request = \Illuminate\Http\Request::create(
    "/api/v1/email-messages/{$msg->id}/content",
    'PATCH',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
    json_encode(['subject' => 'Test subject from HTTP', 'body' => '<p>Test body from HTTP</p>'])
);

// Bind the route parameter
$request->setRouteResolver(function () use ($msg) {
    $route = new \Illuminate\Routing\Route(['PATCH'], "api/v1/email-messages/{emailMessage}/content", []);
    $route->setParameter('emailMessage', $msg->id);
    return $route;
});

try {
    $controller = new App\Http\Controllers\Api\EmailMessageApiController();
    $response = $controller->updateContent($request, $msg);
    echo "Controller response status: " . $response->getStatusCode() . "\n";
    echo "Controller response body: " . $response->getContent() . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
}

// Check the actual state
$msg->refresh();
echo "\nFinal state:\n";
echo "  approval_status: {$msg->approval_status}\n";
echo "  subject: {$msg->subject}\n";
echo "  body: " . (empty($msg->body) ? 'empty' : 'has content') . "\n";
