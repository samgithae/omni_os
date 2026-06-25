<?php
require "/srv/omni_os/vendor/autoload.php";
$app = require "/srv/omni_os/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Agent;
use App\Models\ActivityEvent;

$tokyo = Agent::where("codename", "tokyo")->first();
$token = $tokyo->generateToken();

echo "LAST4: " . $tokyo->token_last_four . "\n";

// Test via file_get_contents through nginx
$authHdr = "Authorization: Bearer " . $token;
$ctHdr = "Content-Type: application/json";
$body = json_encode([
    "source" => "t.f5",
    "event_type" => "system",
    "title" => "final test",
    "severity" => "info",
]);
$opts = [
    "http" => [
        "method" => "POST",
        "header" => $authHdr . "\r\n" . $ctHdr . "\r\n",
        "content" => $body,
        "timeout" => 5,
    ],
];
$result = @file_get_contents("http://127.0.0.1/api/v1/events", false, stream_context_create($opts));
$code = $http_response_header[0] ?? "N/A";
echo "CODE: $code\n";
echo "RESULT: " . var_export($result, true) . "\n";

if (strpos($code, "201") !== false) {
    $ev = ActivityEvent::where("source", "t.f5")->latest()->first();
    echo "Event agent_id: " . ($ev->agent_id ?? "null") . " (expected: " . $tokyo->id . ")\n";
    echo "ATTRIBUTION: " . ($ev->agent_id === $tokyo->id ? "PASS" : "FAIL") . "\n";

    $tokyo->refresh();
    echo "LAST_ACTIVE: " . ($tokyo->last_active_at ? $tokyo->last_active_at->format("c") : "never") . "\n";
}

// Cleanup
ActivityEvent::whereIn("source", ["t.f5"])->delete();

// Check middleware debug log
echo "\n--- MIDDLEWARE DEBUG LOG ---\n";
echo file_get_contents("/tmp/mw_debug.log");
unlink("/tmp/mw_debug.log");
