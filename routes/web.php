<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BrandsController;
use App\Http\Controllers\BrandSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailMessagesController;
use App\Http\Controllers\EmailSequenceController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MiningTargetController;
use App\Http\Controllers\SequenceConfigController;
use App\Http\Controllers\SequenceScheduleController;
use App\Http\Controllers\SuppressionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Brand switcher — set active brand in session (used by Vue pages)
    Route::post('/brand/switch', function (Request $request) {
        $brandId = $request->input('brand_id');
        if ($brandId === null || $brandId === 'null' || $brandId === '') {
            session()->forget('active_brand_id');
        } else {
            session(['active_brand_id' => (int) $brandId]);
        }

        return back();
    })->name('brand.switch');

    // Leads — Vue page with score, filters, and sorting
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');

    // Analytics — funnel, win-loss, engagement rates
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Inbox — reply reading + compose
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/conversation/{lead}', [InboxController::class, 'conversation'])->name('inbox.conversation');
    Route::post('/inbox/{lead}/reply', [InboxController::class, 'reply'])->name('inbox.reply');

    // Brand settings — Vue settings page
    Route::get('/brands/{brand:slug}/settings', [BrandSettingsController::class, 'edit'])->name('brands.settings');
    Route::put('/brands/{brand:slug}/settings', [BrandSettingsController::class, 'update'])->name('brands.settings.update');

    // Cron Jobs — monitoring page
    Route::get('/analytics/jobs', [JobsController::class, 'index'])->name('jobs.index');
    Route::get('/analytics/jobs/history', [JobsController::class, 'history'])->name('jobs.history');
    Route::post('/analytics/jobs/{jobName}/run', [JobsController::class, 'run'])->name('jobs.run');

    // Email sequences — the operator's primary workspace
    Route::get('/email-sequences', [EmailSequenceController::class, 'index'])
        ->name('email-sequences.index');
    Route::post('/email-sequences/approve', [EmailSequenceController::class, 'bulkApprove'])
        ->name('email-sequences.bulk-approve');
    Route::post('/email-sequences/reject', [EmailSequenceController::class, 'bulkReject'])
        ->name('email-sequences.bulk-reject');
    Route::post('/email-sequences/{emailMessage}/approve', [EmailSequenceController::class, 'approve'])
        ->name('email-sequences.approve');
    Route::post('/email-sequences/{emailMessage}/reject', [EmailSequenceController::class, 'reject'])
        ->name('email-sequences.reject');
    // Activity feed — system-wide narration
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/activity/poll', [ActivityController::class, 'poll'])->name('activity.poll');
    Route::get('/activity/load-more', [ActivityController::class, 'loadMore'])->name('activity.load-more');
    Route::post('/activity/{event}/comments', [ActivityController::class, 'storeComment'])->name('activity.store-comment');

    // Agents — Vue roster page
    Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');

    // Sequence Configs
    Route::get('/sequence-configs', [SequenceConfigController::class, 'index'])->name('sequence-configs.index');
    Route::get('/sequence-configs/create', [SequenceConfigController::class, 'create'])->name('sequence-configs.create');
    Route::post('/sequence-configs', [SequenceConfigController::class, 'store'])->name('sequence-configs.store');
    Route::get('/sequence-configs/{brandSequenceConfig}/edit', [SequenceConfigController::class, 'edit'])->name('sequence-configs.edit');
    Route::put('/sequence-configs/{brandSequenceConfig}', [SequenceConfigController::class, 'update'])->name('sequence-configs.update');
    Route::delete('/sequence-configs/{brandSequenceConfig}', [SequenceConfigController::class, 'destroy'])->name('sequence-configs.destroy');

    // Sequence Schedules
    Route::get('/sequence-schedules', [SequenceScheduleController::class, 'index'])->name('sequence-schedules.index');
    Route::post('/sequence-schedules', [SequenceScheduleController::class, 'store'])->name('sequence-schedules.store');
    Route::put('/sequence-schedules/{sequenceSchedule}', [SequenceScheduleController::class, 'update'])->name('sequence-schedules.update');
    Route::delete('/sequence-schedules/{sequenceSchedule}', [SequenceScheduleController::class, 'destroy'])->name('sequence-schedules.destroy');

    // Suppressions
    Route::get('/suppressions', [SuppressionController::class, 'index'])->name('suppressions.index');
    Route::post('/suppressions', [SuppressionController::class, 'store'])->name('suppressions.store');
    Route::delete('/suppressions/{suppression}', [SuppressionController::class, 'destroy'])->name('suppressions.destroy');

    // Mining Targets
    Route::get('/mining-targets', [MiningTargetController::class, 'index'])->name('mining-targets.index');
    Route::post('/mining-targets', [MiningTargetController::class, 'store'])->name('mining-targets.store');
    Route::post('/mining-targets/{miningTarget}/toggle', [MiningTargetController::class, 'toggleActive'])->name('mining-targets.toggle');
    Route::delete('/mining-targets/{miningTarget}', [MiningTargetController::class, 'destroy'])->name('mining-targets.destroy');

    // Brands
    Route::get('/brands', [BrandsController::class, 'index'])->name('brands.index');
    Route::get('/brands/{brand}/edit', [BrandsController::class, 'edit'])->name('brands.edit');
    Route::put('/brands/{brand}', [BrandsController::class, 'update'])->name('brands.update');

    // Email Messages
    Route::get('/email-messages', [EmailMessagesController::class, 'index'])->name('email-messages.index');
    Route::get('/email-messages/{emailMessage}', [EmailMessagesController::class, 'show'])->name('email-messages.show');
});

require __DIR__.'/settings.php';
