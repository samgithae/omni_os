<?php

use App\Http\Controllers\Api\ActivityEventCommentController;
use App\Http\Controllers\Api\ActivityEventController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\EmailMessageApiController;
use App\Http\Controllers\Api\InstructionController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MiningTargetController;
use App\Http\Controllers\Api\ReplyController;
use App\Http\Controllers\Api\SequenceConfigController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SuppressionController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\ApiTokenAuth;
use App\Services\WinLossService;
use Illuminate\Support\Facades\Route;

// Webhooks (no Bearer token — use their own auth)
Route::post('webhooks/smtp2go', [WebhookController::class, 'smtp2go']);

// Telegram webhook — receives approval replies (no Bearer token, uses webhook_secret)
Route::post('webhooks/telegram', [TelegramWebhookController::class, 'handle']);

Route::prefix('v1')->middleware(ApiTokenAuth::class)->group(function () {

    // Stats
    Route::get('stats', [StatsController::class, 'index']);
    Route::get('stats/winloss', fn (WinLossService $service) => response()->json($service->report()));

    // Leads
    Route::get('leads', [LeadController::class, 'index']);
    Route::post('leads/bulk', [LeadController::class, 'bulkCreate']);
    Route::patch('leads/{lead}/enrich', [LeadController::class, 'enrich']);
    // Lead scoring
    Route::patch('leads/{lead}/score', [LeadController::class, 'score']);
    Route::get('leads/needs-email-generation', [LeadController::class, 'needsEmailGeneration']);
    Route::post('leads/{lead}/email-content-batch', [LeadController::class, 'submitEmailContentBatch']);

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

    // Activity events — uses per-agent token auth, backward-compatible with legacy token
    Route::post('events', [ActivityEventController::class, 'store'])->withoutMiddleware(ApiTokenAuth::class)->middleware('agent.token');

    // Classified replies (from Hermes)
    Route::post('replies', [ReplyController::class, 'store']);

    // Email sequence scheduling — Hermes fills drafts
    Route::get('email-messages/needs-content', [EmailMessageApiController::class, 'needsContent']);
    Route::patch('email-messages/{emailMessage}/content', [EmailMessageApiController::class, 'updateContent']);

    // Activity event comments — Hermes/Agent reads and replies
    Route::get('events/{event}/comments', [ActivityEventCommentController::class, 'index']);
    Route::post('events/{event}/comments', [ActivityEventCommentController::class, 'store']);

    // Instruction queue — Hermes polls before a run
    Route::get('instructions', [InstructionController::class, 'index']);
    Route::patch('instructions/{comment}', [InstructionController::class, 'update']);

    // Sequence configs — Hermes reads to know how to draft
    Route::get('sequence-configs/{brandSlug}/{segment}', [SequenceConfigController::class, 'show']);
});
