<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Lead;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class PublishHiringSignalDigest extends Command
{
    protected $signature = 'leads:hiring-signal-digest
        {--brand=ujuziplus : Brand slug}
        {--dry-run : Display the digest without posting}';

    protected $description = 'Publish a consolidated daily digest of Hiring Deer pipeline results to the activity feed';

    public function handle(ActivityLogger $logger): int
    {
        $brandSlug = $this->option('brand');
        $brand = Brand::where('slug', $brandSlug)->first();
        $dryRun = (bool) $this->option('dry-run');

        if (! $brand) {
            $this->error("Brand '{$brandSlug}' not found.");

            return self::FAILURE;
        }

        // Get today's Hiring Deer leads (mined today = created_at is today)
        $today = now()->startOfDay();
        $leads = Lead::where('brand_id', $brand->id)
            ->where('source', 'like', 'hiring_signal_%')
            ->where('created_at', '>=', $today)
            ->get();

        if ($leads->isEmpty()) {
            $this->info('No Hiring Deer leads mined today. Skipping digest.');

            return self::SUCCESS;
        }

        // Aggregate stats
        $total = $leads->count();
        $highPriority = $leads->where('hiring_signal_score', '>=', 80)->count();
        $hrContactsFound = $leads->filter(fn ($l) => ! empty($l->raw_data['hiring_signal']['hr_contact']['name'] ?? null))->count();

        // Source breakdown
        $sources = $leads->groupBy('source')->map->count()->toArray();

        // Industries
        $industries = [];

        // Top 10 by hiring_signal_score
        $top10 = $leads->sortByDesc('hiring_signal_score')->take(10);

        $top10Lines = '';
        $rank = 1;
        foreach ($top10 as $lead) {
            $signal = $lead->raw_data['hiring_signal'] ?? [];
            $titles = array_slice($signal['vacancy_titles'] ?? [], 0, 3);
            $titleStr = ! empty($titles) ? implode(', ', $titles) : 'N/A';
            $score = $lead->hiring_signal_score ?? 0;
            $vacancyCount = $signal['vacancy_count'] ?? 0;
            $top10Lines .= "{$rank}. {$lead->company_name} (score {$score}) — {$vacancyCount} vacancies: {$titleStr}\n";
            $rank++;
        }

        // Source breakdown string
        $sourceParts = [];
        foreach ($sources as $source => $count) {
            $label = str_replace('hiring_signal_', '', $source);
            $sourceParts[] = "{$label}: {$count}";
        }
        $sourceStr = implode(', ', $sourceParts);

        $industryStr = ! empty($industries) ? implode(', ', $industries) : 'Various';
        $body = "📊 <b>Daily Hiring Intel Digest</b>\n\n"
            ."🔍 Companies qualified: <b>{$total}</b>\n"
            ."⭐ High priority (score ≥80): <b>{$highPriority}</b>\n"
            ."👤 HR contacts found: <b>{$hrContactsFound}</b>\n"
            ."🏢 Sources: {$sourceStr}\n\n"
            ."<b>Top 10 Opportunities</b>\n"
            .$top10Lines;

        if ($dryRun) {
            $this->line("=== DRY RUN: Digest would be posted ===");
            $this->line("Title: Hiring Intelligence: {$total} qualified, {$highPriority} high priority");
            $this->line($body);

            return self::SUCCESS;
        }

        $logger->log([
            'source' => 'laravel.cli.hiring-signal-digest',
            'event_type' => 'mining_run',
            'title' => "Hiring Intelligence: {$total} qualified, {$highPriority} high priority",
            'body' => $body,
            'metadata' => [
                'total' => $total,
                'high_priority' => $highPriority,
                'hr_contacts_found' => $hrContactsFound,
                'sources' => $sources,
            ],
            'severity' => $highPriority > 0 ? 'success' : 'info',
        ]);

        $this->info("Digest posted: {$total} companies, {$highPriority} high-priority.");

        return self::SUCCESS;
    }
}
