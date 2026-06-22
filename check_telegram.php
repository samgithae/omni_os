<?php
$token = config('services.telegram.bot_token');
$response = \Illuminate\Support\Facades\Http::timeout(10)->get(
    'https://api.telegram.org/bot' . $token . '/getUpdates',
    ['offset' => 1, 'limit' => 10]
);
$data = $response->json();
echo 'OK: ' . ($data['ok'] ? 'true' : 'false') . "\n";
echo 'Results: ' . count($data['result'] ?? []) . "\n";
foreach ($data['result'] ?? [] as $update) {
    $cb = $update['callback_query'] ?? null;
    if ($cb) {
        echo '  Callback: data=' . $cb['data'] . ' from=' . ($cb['from']['first_name'] ?? '?') . " id=" . ($cb['id'] ?? '?') . "\n";
    } elseif ($update['message']) {
        echo '  Message: text=' . ($update['message']['text'] ?? 'none') . " from=" . ($update['message']['from']['first_name'] ?? '?') . "\n";
    }
}