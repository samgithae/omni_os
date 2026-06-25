<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\WinLossService;
use Illuminate\Console\Command;

class GenerateWinLossReport extends Command
{
    protected $signature = 'winloss:generate
                            {--json : Output raw JSON instead of summary}';

    protected $description = 'Generate win-loss report from reply outcomes and pipeline metrics';

    public function handle(WinLossService $service): int
    {
        $this->info('=== Win-Loss Report ===');

        $report = $service->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Print funnel
        $f = $report['funnel'];
        $this->info('Funnel:');
        $this->line("  Leads: {$f['leads']}");
        $this->line("  With email: {$f['with_email']} ({$f['enrichment_rate']}% enrichment)");
        $this->line("  Emailed: {$f['emailed']} ({$f['email_coverage']}% coverage)");
        $this->line("  Replied: {$f['replied']} ({$f['reply_rate']}% reply rate)");
        $this->line("  Interested: {$f['interested']} ({$f['interest_rate']}% interest rate)");
        $this->line("  Overall conversion: {$f['overall_conversion']}%");

        // Print email rates
        $r = $report['rates'];
        $this->newLine();
        $this->info('Email engagement:');
        $this->line("  Sent: {$r['sent']}");
        $this->line("  Opened: {$r['opened']} ({$r['open_rate']}% open rate)");
        $this->line("  Clicked: {$r['clicked']} ({$r['click_rate']}% click rate)");
        $this->line("  Replied: {$r['replied']} ({$r['reply_rate']}% reply rate)");

        // Print top categories
        $this->newLine();
        $this->info('Top categories by replies:');
        foreach (array_slice($report['by_category'], 0, 5) as $cat) {
            $this->line("  {$cat['dimension']}: {$cat['leads']} leads, {$cat['replied']} replied, {$cat['interested']} interested");
        }

        // Print reply outcomes
        $this->newLine();
        $this->info('Reply outcomes:');
        $o = $report['reply_outcomes'];
        foreach ($o['counts'] as $type => $count) {
            $pct = $o['percentages'][$type] ?? 0;
            $this->line("  {$type}: {$count} ({$pct}%)");
        }

        // Post to activity feed
        $body = $this->buildBriefBody($report);

        app(ActivityLogger::class)->log([
            'brand_id' => null,
            'source' => 'laravel.winloss',
            'event_type' => 'system',
            'title' => 'Win-loss report — '.now()->format('M j'),
            'body' => $body,
            'metadata' => $report,
            'severity' => 'info',
        ]);

        $this->newLine();
        $this->info('Report posted to Activity Feed.');

        return self::SUCCESS;
    }

    private function buildBriefBody(array $report): string
    {
        $f = $report['funnel'];
        $r = $report['rates'];
        $o = $report['reply_outcomes'];

        $lines = [];
        $lines[] = 'Pipeline funnel:';
        $lines[] = "  Leads: {$f['leads']} → Email: {$f['with_email']} → Emailed: {$f['emailed']} → Replied: {$f['replied']} → Interested: {$f['interested']}";
        $lines[] = "  Enrichment: {$f['enrichment_rate']}% | Reply: {$f['reply_rate']}% | Interest: {$f['interest_rate']}%";
        $lines[] = '';
        $lines[] = 'Email engagement:';
        $lines[] = "  Sent: {$r['sent']} | Open: {$r['open_rate']}% | Click: {$r['click_rate']}% | Reply: {$r['reply_rate']}%";
        $lines[] = '';

        if ($o['total'] > 0) {
            $lines[] = "Reply outcomes ({$o['total']} total):";
            foreach ($o['counts'] as $type => $count) {
                if ($count > 0) {
                    $pct = $o['percentages'][$type] ?? 0;
                    $lines[] = "  {$type}: {$count} ({$pct}%)";
                }
            }
        } else {
            $lines[] = 'No replies classified yet.';
        }

        $lines[] = '';
        $lines[] = 'Top categories:';
        foreach (array_slice($report['by_category'], 0, 5) as $cat) {
            $lines[] = "  {$cat['dimension']}: {$cat['leads']} leads, {$cat['interested']} interested";
        }

        return implode("\n", $lines);
    }
}
