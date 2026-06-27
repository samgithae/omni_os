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
        $url = 'https://api.telegram.org/' . $path;
        $method = $request->method();
        $body = $request->getContent();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $headers = [];
        $ct = $request->header('Content-Type');
        if ($ct) {
            $headers[] = 'Content-Type: ' . $ct;
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return response($res ?: '', $http ?: 502)
            ->header('Content-Type', 'application/json');
    }
}
