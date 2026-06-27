<?php

namespace App\Http\Controllers\Api;

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
        $url = 'https://telegram-api.hudutech.co.ke/' . $path;
        $method = $request->method();
        $body = $request->getContent();

        $response = Http::timeout(20)->withOptions([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ])->send($method, $url, [
            'body' => $body,
            'headers' => [
                'Content-Type' => $request->header('Content-Type', 'application/json'),
            ],
        ]);

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }
}
