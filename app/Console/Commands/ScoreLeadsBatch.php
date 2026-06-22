<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Lead;
use App\Services\ActivityLogger;
use App\Services\LeadScoringService;
use Illuminate\Console\Command;

class ScoreLeadsBatch extends Command
{
    protected $signature = 'leads:score
                            {--brand= : Brand slug to score (default: all brands)}
                            {--segment= : Segment to score (rabbit|deer|mouse|elephant)}
                            {--limit= : Max leads to process}
                            {--dry-run : Show what would be scored without writing}';

    protected $description = 'Recalculate lead scores based on segment, data completeness, email confidence, and engagement';

    private LeadScoringService $scorer;

    public function handle(LeadScoringService $scorer): int
    {
        $this->scorer = $scorer;

        $this->info('=== Lead Scoring Batch ===');

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written.');
        }

        $query = Lead::query()->with(['emailMessages', 'events', 'brand']);

        // Brand filter
        if ($this->option('brand')) {
            $brand = Brand::where('slug', $this->option('brand'))->first();
            if (!$brand) {
                $this->error("Brand not found: {$this->option('brand')}");
                return self::FAILURE;
            }
            $query->where('brand_id', $brand->id);
            $this->info("Brand: {$brand->name}");
        }

        // Segment filter
        if ($this->option('segment')) {
            $query->where('segment', $this->option('segment'));
            $this->info("Segment: {$this->option('segment')}");
        }

        // Limit
        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $total = (clone $query)->count();
        $this->info("Leads to score: {$total}");

        if ($total === 0) {
            $this->warn('No leads found matching criteria.');
            return self::SUCCESS;
        }

        $stats = [
            'scored' => 0,
            'changed' => 0,
            'avg_score' => 0,
            'score_sum' => 0,
            'tier_counts' => [
                'hot' => 0,
                'warm' => 0,
                'moderate' => 0,
                'cold' => 0,
                'frigid' => 0,
            ],
        ];

        $bar = $this->output->createProgressBar(min($total, $this->option('limit') ?: $total));
        $bar->start();

        $query->chunkById(200, function ($leads) use ($dryRun, &$stats, $bar) {
            foreach ($leads as $lead) {
                $oldScore = $lead->score;

                if ($dryRun) {
                    $result = $this->scorer->calculate($lead);
                    $newScore = $result['score'];
                    $stats['scored']++;
                    $stats['score_sum'] += $newScore;
                    $stats['tier_counts'][LeadScoringService::tier($newScore)]++;

                    if ($newScore !== $oldScore) {
                        $stats['changed']++;
                    }

                    if ($stats['scored'] <= 10) {
                        $this->line("  {$lead->company_name}: {$oldScore} -> {$newScore} [" . LeadScoringService::tier($newScore) . "]");
                    }
                } else {
                    $newScore = $this->scorer->recalculate($lead);
                    $stats['scored']++;
                    $stats['score_sum'] += $newScore;
                    $stats['tier_counts'][LeadScoringService::tier($newScore)]++;

                    if ($newScore !== $oldScore) {
                        $stats['changed']++;
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $avg = $stats['scored'] > 0 ? round($stats['score_sum'] / $stats['scored'], 1) : 0;

        $this->info('=== Summary ===');
        $this->info("Scored: {$stats['scored']}");
        $this->info("Changed: {$stats['changed']}");
        $this->info("Average score: {$avg}");
        $this->info('Tier distribution:');
        foreach ($stats['tier_counts'] as $tier => $count) {
            $this->line("  {$tier}: {$count}");
        }

        // Log to activity feed (only on real run)
        if (!$dryRun && $stats['scored'] > 0) {
            $brandSlug = $this->option('brand') ?? 'all';
            app(ActivityLogger::class)->log([
                'brand_id' => null,
                'source' => 'lead_scoring',
                'event_type' => 'system',
                'title' => "Lead scoring batch complete",
                'body' => "Scored {$stats['scored']} leads ({$stats['changed']} changed). Average: {$avg}. Brand: {$brandSlug}.",
                'metadata' => [
                    'scored' => $stats['scored'],
                    'changed' => $stats['changed'],
                    'avg_score' => $avg,
                    'tier_counts' => $stats['tier_counts'],
                    'brand' => $brandSlug,
                    'segment' => $this->option('segment'),
                ],
                'severity' => 'info',
            ]);
        }

        return self::SUCCESS;
    }
}