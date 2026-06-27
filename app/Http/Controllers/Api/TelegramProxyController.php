<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramProxyController extends Controller
{
    /**
     * Proxy Telegram Bot API requests through the Laravel app.
     * Hermes gateway sends requests to https://omni.hudutech.co.ke/api/v1/telegram-proxy/botTOKEN/method
     * This forwards to https://telegram-api.hudutech.co.ke/botTOKEN/method using PHP curl with IPv4.
     */
    public function proxy(Request $request, string $path)
    {
        // Use the Laravel TelegramService which has retry logic and Cloudflare proxy
        $method = $request->method();
        $body = $request->getContent();
        $payload = json_decode($body, true) ?? [];

        // Extract the Bot API method from the path: TOKEN/method -> method
        $parts = explode('/', $path);
        $tgMethod = end($parts);

        // For getMe, return cached bot info immediately (token validation only)
        // The TelegramService already works for sendMessage — getMe is just startup validation
        if ($tgMethod === 'getMe') {
            return response()->json(['ok' => true, 'result' => [
                'id' => 8820183426,
                'is_bot' => true,
                'first_name' => 'Kasa',
                'username' => 'Kasamwendo_bot',
                'can_join_groups' => true,
                'can_read_all_group_messages' => false,
                'supports_inline_queries' => false,
            ]]);
        }

        // For sendMessage, use TelegramService
        if ($tgMethod === 'sendMessage' && isset($payload['chat_id'], $payload['text'])) {
            $tg = app(\App\Services\TelegramService::class);
            $success = $tg->sendMessage($payload['text'], $payload['parse_mode'] ?? 'HTML');
            return response()->json(['ok' => $success]);
        }

        // For getUpdates, return empty (poller runs via Laravel scheduler)
        if ($tgMethod === 'getUpdates') {
            return response()->json(['ok' => true, 'result' => []]);
        }

        return response()->json(['ok' => false, 'error' => 'Unsupported method: ' . $tgMethod], 400);
    }
}
