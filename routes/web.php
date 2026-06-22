<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\EmailSequenceController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Leads — Vue page with score, filters, and sorting
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');

    // Analytics — funnel, win-loss, engagement rates
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Inbox — reply reading + compose
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/conversation/{lead}', [InboxController::class, 'conversation'])->name('inbox.conversation');
    Route::post('/inbox/{lead}/reply', [InboxController::class, 'reply'])->name('inbox.reply');

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
});

require __DIR__.'/settings.php';