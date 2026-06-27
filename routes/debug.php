<?php

use Illuminate\Support\Facades\Route;

Route::get('debug-token', function () {
    $expected = config('app.omni_api_token');

    return response()->json([
        'expected' => $expected,
        'expected_len' => strlen($expected),
        'env_value' => env('OMNI_API_TOKEN'),
    ]);
});
