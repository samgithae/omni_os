<?php

use App\Http\Middleware\AgentTokenAuth;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\ProcessSequenceProgressions;
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
        $schedule->command('queue:prune-failed --hours=336')
            ->dailyAt('02:30')
            ->description('Clean up failed queue jobs older than 14 days');

        // Send approved/queued emails — every 15 minutes.
        $schedule->command('emails:send-batch --limit=20')
            ->everyFifteenMinutes()
            ->withoutOverlapping(5)
            ->description('Send approved emails via SMTP2GO with safe-send discipline')
            ->appendOutputTo(storage_path('logs/email-send.log'));

        // Email generation pipeline check — every 60 minutes (tracks Hermes cron)
        $schedule->command('emails:generate-content --limit=10')
            ->everyThirtyMinutes()
            ->withoutOverlapping(10)
            ->description('Check enriched leads for missing email content and log pipeline status')
            ->appendOutputTo(storage_path('logs/email-generation.log'));

        // Notify Telegram of pending approvals — every 30 minutes
        $schedule->command('emails:notify-telegram --limit=15')
            ->everyThirtyMinutes()
            ->withoutOverlapping(10)
            ->description('Send pending email approval requests to Telegram with content preview')
            ->appendOutputTo(storage_path('logs/telegram-approval.log'));

        // Sequence progression — daily at 5 AM (before approval batch)
        $schedule->job(new ProcessSequenceProgressions)
            ->dailyAt('05:00')
            ->withoutOverlapping(60)
            ->description('Progress email sequences: schedule next steps for leads (weekdays only)')
            ->appendOutputTo(storage_path('logs/sequence-progression.log'));

        // Poll Telegram for approval replies — every 5 minutes (reduced from 1min)
        $schedule->command('telegram:poll-approvals')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->description('Poll Telegram for approval replies (text commands + inline callbacks)')
            ->appendOutputTo(storage_path('logs/telegram-poll.log'));

        // Daily brief — posted to Activity Feed at 7 AM
        $schedule->command('activity:daily-brief')
            ->dailyAt('07:00')
            ->description('Generate daily system overview brief with funnel metrics')
            ->appendOutputTo(storage_path('logs/daily-brief.log'));

        // Recalculate lead scores — daily at 3 AM
        $schedule->command('leads:score')
            ->dailyAt('03:00')
            ->withoutOverlapping(30)
            ->description('Recalculate lead scores (segment, completeness, engagement, email confidence)')
            ->appendOutputTo(storage_path('logs/lead-scoring.log'));

        // Win-loss report — weekly on Mondays at 6 AM
        $schedule->command('winloss:generate')
            ->weeklyOn(1, '06:00')
            ->description('Generate win-loss report from reply outcomes and pipeline metrics')
            ->appendOutputTo(storage_path('logs/winloss.log'));

        // Poll IMAP inbox for replies — every 10 minutes
        $schedule->command('inbox:poll --days=3 --limit=30')
            ->everyTenMinutes()
            ->withoutOverlapping(5)
            ->description('Poll IMAP inbox for lead replies and create Reply records')
            ->appendOutputTo(storage_path('logs/inbox-poll.log'));

        // Clean up orphaned cron job run records — every 30 minutes
        $schedule->command('cron:cleanup-runs --older-than=30')
            ->everyThirtyMinutes()
            ->description('Mark stuck running cron job records as failed');

        // Lead mining pipeline monitor — every 2 hours (tracks Hermes mining crons)
        $schedule->command('leads:monitor-mining --hours=2')
            ->everyTwoHours()
            ->withoutOverlapping(30)
            ->description('Monitor lead mining pipeline: check Hermes mining crons are producing leads');

        // Hiring Signal Pipeline — Phase 1: Tokyo mines job boards (daily, starting 00:00 EAT)
        // Staggered per source to spread load, completes by ~02:00
        // Then Phase 2: Bogotá enrichment runs on newly mined leads
        $schedule->command('leads:mine-hiring-signals --source=brightermonday')
            ->dailyAt('00:00')
            ->withoutOverlapping(30)
            ->description('Hiring Deer: Mine BrighterMonday Kenya')
            ->appendOutputTo(storage_path('logs/hiring-signal-mining.log'));

        $schedule->command('leads:mine-hiring-signals --source=fuzu')
            ->dailyAt('00:10')
            ->withoutOverlapping(30)
            ->description('Hiring Deer: Mine Fuzu')
            ->appendOutputTo(storage_path('logs/hiring-signal-mining.log'));

        $schedule->command('leads:mine-hiring-signals --source=myjobmag')
            ->dailyAt('00:20')
            ->withoutOverlapping(30)
            ->description('Hiring Deer: Mine MyJobMag Kenya')
            ->appendOutputTo(storage_path('logs/hiring-signal-mining.log'));

        $schedule->command('leads:mine-hiring-signals --source=corporatestaffing')
            ->dailyAt('00:30')
            ->withoutOverlapping(30)
            ->description('Hiring Deer: Mine Corporate Staffing Services')
            ->appendOutputTo(storage_path('logs/hiring-signal-mining.log'));

        $schedule->command('leads:mine-hiring-signals --source=glassdoor')
            ->dailyAt('00:40')
            ->withoutOverlapping(30)
            ->description('Hiring Deer: Mine Glassdoor Jobs')
            ->appendOutputTo(storage_path('logs/hiring-signal-mining.log'));

        $schedule->command('leads:mine-hiring-signals --source=company_careers')
            ->dailyAt('00:50')
            ->withoutOverlapping(60)
            ->description('Hiring Deer: Crawl company careers pages')
            ->appendOutputTo(storage_path('logs/hiring-signal-mining.log'));

        $schedule->command('leads:mine-hiring-signals --source=google_jobs')
            ->dailyAt('01:20')
            ->withoutOverlapping(30)
            ->description('Hiring Deer: Scan Google Jobs (schema.org)')
            ->appendOutputTo(storage_path('logs/hiring-signal-mining.log'));

        $schedule->command('leads:mine-hiring-signals --source=linkedin')
            ->dailyAt('01:35')
            ->withoutOverlapping(30)
            ->description('Hiring Deer: Mine LinkedIn Jobs')
            ->appendOutputTo(storage_path('logs/hiring-signal-mining.log'));

        // Phase 2: Enrichment — runs on newly mined Hiring Deer leads (after Tokyo finishes)
        $schedule->command('leads:enrich-batch --segment=deer --limit=100')
            ->dailyAt('02:00')
            ->withoutOverlapping(60)
            ->description('Hiring Deer: Enrich newly mined leads')
            ->appendOutputTo(storage_path('logs/hiring-signal-enrichment.log'));

        // Consolidated daily digest — posts one card to activity feed (The Professor)
        $schedule->command('leads:hiring-signal-digest')
            ->dailyAt('02:10')
            ->withoutOverlapping(10)
            ->description('Hiring Deer: Publish consolidated daily digest to activity feed')
            ->appendOutputTo(storage_path('logs/hiring-signal-digest.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'agent.token' => AgentTokenAuth::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'analytics/jobs/*/run',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->create();
