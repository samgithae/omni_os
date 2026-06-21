<?php

use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MiningTargetController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SuppressionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\ActivityEventController;
use App\Http\Middleware\ApiTokenAuth;
use Illuminate\Support\Facades\Route;

// Webhooks (no Bearer token — use their own auth)
Route::post('webhooks/smtp2go', [WebhookController::class, 'smtp2go']);

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

    // Emails
    Route::get('emails', [EmailController::class, 'index']);
    Route::post('emails', [EmailController::class, 'store']);
    Route::post('emails/{email}/approve', [EmailController::class, 'approve']);
    Route::post('emails/{email}/reject', [EmailController::class, 'reject']);
    Route::post('emails/send-batch', [EmailController::class, 'sendBatch']);

    // Activity events
    Route::post('events', [ActivityEventController::class, 'store']);

});
