<?php

use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MiningTargetController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SuppressionController;
use App\Http\Middleware\ApiTokenAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(ApiTokenAuth::class)->group(function () {

    // Stats
    Route::get('stats', [StatsController::class, 'index']);

    // Leads
    Route::get('leads', [LeadController::class, 'index']);
    Route::post('leads/bulk', [LeadController::class, 'bulkCreate']);
    Route::patch('leads/{lead}/enrich', [LeadController::class, 'enrich']);

    // Mining targets
    Route::get('mining-targets', [MiningTargetController::class, 'index']);
    Route::patch('mining-targets/{miningTarget}/mined', [MiningTargetController::class, 'markMined']);

    // Suppressions
    Route::get('suppressions/check', [SuppressionController::class, 'check']);

});
