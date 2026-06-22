<?php
$token = app()->make('config')->get('services.telegram.bot_token');
$response = \Illuminate\Support\Facades\Http::timeout(10)->get(
    'https://api.telegram.org/bot' . $token . '/getWebhookInfo'
);
$data = $response->json();
echo 'ok: ' . ($data['ok'] ?? 'false') . "\n";
if (isset($data['result'])) {
    echo 'url: ' . ($data['result']['url'] ?? 'not set') . "\n";
    echo 'has_custom_certificate: ' . ($data['result']['has_custom_certificate'] ?? '?') . "\n";
    echo 'pending_update_count: ' . ($data['result']['pending_update_count'] ?? 0) . "\n";
} else {
    echo 'error: ' . ($data['description'] ?? 'unknown') . "\n";
}