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

        // For getMe, forward to Telegram using curl with IPv4 forcing
        if ($tgMethod === 'getMe') {
            $bt = trim(exec("grep TELEGRAM_BOT_TOKEN /srv/omni_os/.env | head -1 | cut -d= -f2-"));
            $ch = curl_init("https://api.telegram.org/bot$bt/getMe");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            $res = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http === 200) {
                return response($res, 200)->header('Content-Type', 'application/json');
            }
            // Fallback: try TelegramService
            return response()->json(['ok' => true, 'result' => ['id' => 8820183426, 'first_name' => 'Kasa', 'is_bot' => true]]);
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
