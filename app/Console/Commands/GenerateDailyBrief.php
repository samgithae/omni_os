<?php

namespace App\Console\Commands;

use App\Models\ActivityEvent;
use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Services\ActivityLogger;
use App\Services\WinLossService;
use Illuminate\Console\Command;

class GenerateDailyBrief extends Command
{
    protected $signature = 'activity:daily-brief';
    protected $description = 'Generate daily brief and post to Activity Feed';

    public function handle(): int
    {
        $logger = app(ActivityLogger::class);
        $brief = $this->buildBrief();

        $logger->log([
            'source' => 'laravel.control-tower.daily-brief',
            'event_type' => 'daily_brief',
            'title' => 'Daily brief — ' . now()->format('M j'),
            'body' => $brief,
            'severity' => 'info',
        ]);

        $this->info('Daily brief posted to Activity Feed.');
        return self::SUCCESS;
    }

    private function buildBrief(): string
    {
        $brands = Brand::all();
        $lines = [];

        $lines[] = "System Overview — " . now()->format('M j, Y');
        $lines[] = str_repeat('─', 30);
        $lines[] = "";

        // Funnel summary
        $winloss = app(WinLossService::class);
        $funnel = $winloss->funnel();
        $rates = $winloss->rates();

        $lines[] = "Pipeline Funnel:";
        $lines[] = "  Leads: {$funnel['leads']} → Email: {$funnel['with_email']} → Emailed: {$funnel['emailed']} → Replied: {$funnel['replied']} → Interested: {$funnel['interested']}";
        $lines[] = "  Enrichment: {$funnel['enrichment_rate']}% | Reply: {$funnel['reply_rate']}% | Interest: {$funnel['interest_rate']}%";
        $lines[] = "";

        if ($rates['sent'] > 0) {
            $lines[] = "Email Engagement:";
            $lines[] = "  Sent: {$rates['sent']} | Open: {$rates['open_rate']}% | Click: {$rates['click_rate']}% | Reply: {$rates['reply_rate']}%";
            $lines[] = "";
        }

        foreach ($brands as $brand) {
            $lines[] = "{$brand->name}:";

            $totalLeads = Lead::where('brand_id', $brand->id)->count();
            $newLeads = Lead::where('brand_id', $brand->id)->where('status', 'new')->count();
            $enriched = Lead::where('brand_id', $brand->id)->where('status', 'enriched')->count();
            $noEmail = Lead::where('brand_id', $brand->id)->where('status', 'no_email_found')->count();
            $interested = Lead::where('brand_id', $brand->id)->where('status', 'interested')->count();

            $pendingEmails = EmailMessage::where('brand_id', $brand->id)
                ->where('approval_status', 'pending')->count();
            $needsContent = EmailMessage::where('brand_id', $brand->id)
                ->where('approval_status', 'needs_content')->count();
            $sentToday = EmailMessage::where('brand_id', $brand->id)
                ->where('status', 'sent')
                ->whereDate('sent_at', today())->count();

            $lines[] = "  • Leads: {$totalLeads} total ({$newLeads} new, {$enriched} enriched, {$noEmail} no email)";
            $lines[] = "  • Interested: {$interested}";
            $lines[] = "  • Emails: {$pendingEmails} pending approval, {$needsContent} needs content";
            if ($sentToday > 0) {
                $lines[] = "  • Sent today: {$sentToday}";
            }

            $lines[] = "";
        }

        // System health
        $totalEvents = ActivityEvent::whereDate('created_at', today())->count();
        $queueHealth = $this->checkQueueHealth();

        $lines[] = "System:";
        $lines[] = "  • Activity events today: {$totalEvents}";
        $lines[] = "  • Queue workers: {$queueHealth}";
        $lines[] = "";

        // Check for anything noteworthy
        $recentInterested = Lead::where('status', 'interested')
            ->whereDate('updated_at', today())->count();
        if ($recentInterested > 0) {
            $lines[] = "🔥 {$recentInterested} lead(s) marked interested today — check Telegram!";
        }

        return implode("\n", $lines);
    }

    private function checkQueueHealth(): string
    {
        try {
            $output = shell_exec('sudo supervisorctl status omni-os-queue-worker:* 2>/dev/null');
            if ($output) {
                $lines = explode("\n", trim($output));
                $running = 0;
                foreach ($lines as $line) {
                    if (str_contains($line, 'RUNNING')) $running++;
                }
                return "{$running}/2 running";
            }
        } catch (\Throwable $e) {
            // Silently fail
        }
        return 'unknown';
    }
}
