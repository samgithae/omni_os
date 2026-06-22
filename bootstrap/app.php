<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Keep queue housekeeping running once cron is installed on Linux.
        $schedule->command('queue:prune-failed --hours=336')->dailyAt('02:30');

        // Send approved/queued emails — every 15 minutes during business hours.
        $schedule->command('emails:send-batch --limit=20')
            ->everyFifteenMinutes()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/email-send.log'));

        // Notify Telegram of pending approvals — every 30 minutes
        $schedule->command('emails:notify-telegram --limit=15')
            ->everyThirtyMinutes()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/telegram-approval.log'));

        // Sequence progression — daily at 5 AM (before approval batch)
        $schedule->job(new \App\Jobs\ProcessSequenceProgressions)
            ->dailyAt('05:00')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/sequence-progression.log'));

        // Poll Telegram for approval replies — every minute (limited by cron frequency)
        $schedule->command('telegram:poll-approvals')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->appendOutputTo(storage_path('logs/telegram-poll.log'));

        // Daily brief — posted to Activity Feed at 7 AM
        $schedule->command('activity:daily-brief')
            ->dailyAt('07:00')
            ->appendOutputTo(storage_path('logs/daily-brief.log'));

        // Recalculate lead scores — daily at 3 AM (after backup, before daily brief)
        $schedule->command('leads:score')
            ->dailyAt('03:00')
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/lead-scoring.log'));

        // Win-loss report — weekly on Mondays at 6 AM (before daily brief)
        $schedule->command('winloss:generate')
            ->weeklyOn(1, '06:00')
            ->appendOutputTo(storage_path('logs/winloss.log'));

        // Poll IMAP inbox for replies — every 10 minutes during business hours
        $schedule->command('inbox:poll --days=3 --limit=30')
            ->everyTenMinutes()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/inbox-poll.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->create();
