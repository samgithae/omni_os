<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Lead;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class EnrichLeadsBatch extends Command
{
    protected $signature = 'leads:enrich-batch
        {--brand= : Brand slug to process (omit for all brands)}
        {--segment= : Segment to process (rabbit, deer, or omit for all)}
        {--limit=50 : Max leads to process in this batch}
        {--dry-run : Show what would be processed without making changes}';

    protected $description = 'Queue leads that need enrichment — transitions new leads to enriching status';

    public function handle(): int
    {
        $brandSlug = $this->option('brand');
        $segment = $this->option('segment');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        // Build query for leads that need enrichment
        $query = Lead::query()
            ->where('status', 'new')
            ->whereNull('email')
            ->where(function ($q) {
                $q->whereNull('enrichment_attempts')
                    ->orWhere('enrichment_attempts', '<', 3);
            });

        if ($brandSlug) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if (! $brand) {
                $this->error("Brand '{$brandSlug}' not found.");
                return self::FAILURE;
            }
            $query->where('brand_id', $brand->id);
            $this->line("Brand: {$brand->name}");
        } else {
            $query->with('brand');
        }

        if ($segment) {
            $query->where('segment', $segment);
            $this->line("Segment: {$segment}");
        }

        $total = $query->count();
        $this->line("Leads needing enrichment: {$total}");

        if ($total === 0) {
            $this->info('No leads need enrichment right now.');
            return self::SUCCESS;
        }

        $leads = $query->limit($limit)->get();

        if ($dryRun) {
            $this->warn('DRY RUN — no changes made.');
            $this->table(
                ['ID', 'Company', 'Segment', 'City', 'Attempts', 'Brand'],
                $leads->map(fn ($l) => [
                    $l->id,
                    $l->company_name,
                    $l->segment,
                    $l->city ?? '—',
                    $l->enrichment_attempts ?? 0,
                    $l->brand?->name ?? '—',
                ])->toArray()
            );
            return self::SUCCESS;
        }

        $processed = 0;
        $errors = 0;

        foreach ($leads as $lead) {
            try {
                $lead->startEnrichment('cli.enrich-batch');
                $processed++;
            } catch (\Throwable $e) {
                $this->error("Lead #{$lead->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        // Log to activity feed
        $logger = app(ActivityLogger::class);
        $brandNames = $brandSlug
            ? [Brand::where('slug', $brandSlug)->value('name')]
            : $leads->pluck('brand.name')->unique()->values()->toArray();

        $segmentLabel = $segment ? " ({$segment})" : '';

        $logger->log([
            'source' => 'laravel.cli.enrich-batch',
            'event_type' => 'enrichment_batch',
            'title' => "Enrichment batch: {$processed} leads queued{$segmentLabel}" . ($errors ? " — {$errors} failed" : ''),
            'metadata' => [
                'total' => $leads->count(),
                'processed' => $processed,
                'errors' => $errors,
                'brands' => $brandNames,
                'segment' => $segment,
            ],
            'severity' => $errors > 0 ? 'warning' : 'info',
        ]);

        $this->info("Queued {$processed} leads for enrichment.");
        if ($errors) {
            $this->warn("{$errors} leads had errors.");
        }

        return self::SUCCESS;
    }
}
