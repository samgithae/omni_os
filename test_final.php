<?php
require "/srv/omni_os/vendor/autoload.php";
$app = require "/srv/omni_os/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Agent;
use App\Models\ActivityEvent;

$tokyo = Agent::where("codename", "tokyo")->first();
$token = $tokyo->generateToken();

echo "TOKEN: " . $token . "\n";
echo "LAST4: " . $tokyo->token_last_four . "\n";

// Verify hash lookup works
$hash = hash("sha256", $token);
$found = Agent::where("token_hash", $hash)->where("is_active", true)->first();
echo "DB lookup by hash: " . ($found ? $found->display_name : "NO") . "\n";

// Test via HTTP
$ch = curl_init("http://127.0.0.1/api/v1/events");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        "source" => "t.final",
        "event_type" => "system",
        "title" => "final verification",
        "severity" => "info",
    ]),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json",
    ],
    CURLOPT_TIMEOUT => 5,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP POST result: " . $code . " - " . $body . "\n";
curl_close($ch);

if ($code === 201) {
    $event = ActivityEvent::where("source", "t.final")->latest()->first();
    echo "Event agent_id: " . ($event->agent_id ?? "null") . " (expected: " . $tokyo->id . ")\n";
    echo "Attribution: " . ($event->agent_id === $tokyo->id ? "PASS" : "FAIL") . "\n";
    
    // Test backward compat
    $legacy = config("app.omni_api_token");
    $ch = curl_init("http://127.0.0.1/api/v1/events");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "source" => "t.bc2",
            "event_type" => "system",
            "title" => "BC test",
            "severity" => "info",
        ]),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $legacy,
            "Content-Type: application/json",
        ],
        CURLOPT_TIMEOUT => 5,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "Legacy token POST: " . $code . " - " . $body . "\n";
    curl_close($ch);
    
    // Cleanup
    ActivityEvent::whereIn("source", ["t.final", "t.bc2"])->delete();
}
