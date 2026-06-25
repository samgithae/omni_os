<?php
/**
 * Acceptance tests for WO#1 — Agent Registry
 * Run on Linux: php /tmp/test_wo1.php
 */

require '/srv/omni_os/vendor/autoload.php';
$app = require '/srv/omni_os/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Agent;
use App\Models\ActivityEvent;
use Illuminate\Support\Facades\Http;

$base = 'http://127.0.0.1';
$legacyToken = config('app.omni_api_token');
$tokyo = Agent::where('codename', 'tokyo')->first();
$professor = Agent::where('codename', 'the_professor')->first();
$tokyoToken = $tokyo->generateToken();
$profToken = $professor->generateToken();

$pass = 0; $fail = 0;
function check(string $label, bool $condition, string $detail = '') {
    global $pass, $fail;
    if ($condition) { $pass++; echo "  ✅ $label\n"; }
    else { $fail++; echo "  ❌ $label — $detail\n"; }
}

// Helper: POST an event and return [httpCode, body]
function postEvent(string $token, array $extra = []): array {
    global $base;
    $payload = array_merge([
        'source' => 'test.wo1',
        'event_type' => 'system',
        'title' => 'WO#1 acceptance test',
        'severity' => 'info',
    ], $extra);

    $ch = curl_init("$base/api/v1/events");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
}

echo "\n=== TEST 1: Token attribution ===\n";
[$code, $body] = postEvent($tokyoToken, ['source' => 'test.attribution']);
check("POST returns 201", $code === 201);
$event = ActivityEvent::where('source', 'test.attribution')->latest()->first();
check("agent_id = Tokyo's id", $event && $event->agent_id === $tokyo->id);
$tokyo->refresh();
check("last_active_at touched", $tokyo->last_active_at !== null);

echo "\n=== TEST 2: Backward-compat (legacy token) ===\n";
[$code, $body] = postEvent($legacyToken, ['source' => 'test.backward']);
check("POST returns 200/201", in_array($code, [200, 201]));
$event = ActivityEvent::where('source', 'test.backward')->latest()->first();
check("agent_id = null (system)", $event && $event->agent_id === null);

echo "\n=== TEST 3: Spoof / Revoke ===\n";
[$code, ] = postEvent('garbage_token_here123');
check("Garbage token → 401", $code === 401);

$tokyo->update(['is_active' => false]);
[$code, ] = postEvent($tokyoToken, ['source' => 'test.revoked']);
check("Revoked agent token → 401", $code === 401);
$tokyo->update(['is_active' => true]);

echo "\n=== TEST 4: Override (codename wins over token) ===\n";
[$code, $body] = postEvent($profToken, [
    'source' => 'test.override',
    'agent_codename' => 'tokyo',
]);
check("POST returns 201", $code === 201);
$event = ActivityEvent::where('source', 'test.override')->latest()->first();
check("Attributed to Tokyo (codename override)", $event && $event->agent_id === $tokyo->id);

echo "\n=== RESULTS ===\n";
echo "$pass passed, $fail failed\n\n";

// Clean up test events (keep DB clean)
ActivityEvent::whereIn('source', ['test.attribution', 'test.backward', 'test.override'])->delete();
