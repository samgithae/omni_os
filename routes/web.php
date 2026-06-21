<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailSequenceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
});

require __DIR__.'/settings.php';