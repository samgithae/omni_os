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

        // ── Phase 1: Transition new leads that already have emails to enriched ──
        $alreadyHaveEmail = Lead::query()
            ->where('status', 'new')
            ->whereNotNull('email');

        if ($brandSlug) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if (! $brand) {
                $this->error("Brand '{$brandSlug}' not found.");

                return self::FAILURE;
            }
            $alreadyHaveEmail->where('brand_id', $brand->id);
        }

        $alreadyCount = $alreadyHaveEmail->count();
        $alreadyTransitioned = 0;
        $samples = [];
        if ($alreadyCount > 0 && ! $dryRun) {
            $alreadyHaveEmail->limit($limit)->each(function (Lead $lead) use (&$alreadyTransitioned, &$samples) {
                try {
                    // Must go through enriching first (state machine: new -> enriching -> enriched)
                    $lead->startEnrichment('cli.enrich-batch.auto');
                    $lead->refresh();
                    // Now transition enriching -> enriched with the existing email
                    $lead->enrichFound(
                        $lead->email,
                        $lead->email_confidence ?? 'imported',
                        (bool) $lead->email_verified,
                        'cli.enrich-batch.auto',
                        'Email already present at import — auto-enriched'
                    );
                    $alreadyTransitioned++;
                    if (count($samples) < 3) {
                        $samples[] = $lead->company_name.' ('.$lead->email.')';
                    }
                } catch (\Throwable $e) {
                    $this->warn("Lead #{$lead->id}: could not auto-enrich — {$e->getMessage()}");
                }
            });
            if ($alreadyTransitioned > 0) {
                $this->info("Auto-enriched {$alreadyTransitioned} leads with existing emails.");
                foreach ($samples as $s) {
                    $this->line("  ✓ {$s}");
                }
            }
        }

        // ── Phase 2: Queue leads that need email enrichment ──
        $query = Lead::query()
            ->where('status', 'new')
            ->whereNull('email')
            ->where(function ($q) {
                $q->whereNull('enrichment_attempts')
                    ->orWhere('enrichment_attempts', '<', 3);
            });

        if ($brandSlug) {
            $query->where('brand_id', $brand->id);
        } else {
            $query->with('brand');
        }

        if ($segment) {
            $query->where('segment', $segment);
            $this->line("Segment: {$segment}");
        }

        $total = $query->count();
        $this->line("Leads needing email enrichment: {$total}");

        $processed = 0;
        $errors = 0;
        $leads = [];

        if ($total > 0) {
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

            foreach ($leads as $lead) {
                try {
                    $lead->startEnrichment('cli.enrich-batch');
                    $processed++;
                } catch (\Throwable $e) {
                    $this->error("Lead #{$lead->id}: {$e->getMessage()}");
                    $errors++;
                }
            }
        }

        // ── Phase 3: Report ──
        if ($alreadyTransitioned === 0 && $processed === 0 && $total === 0) {
            $this->info('No leads need enrichment right now.');
        } else {
            if ($alreadyTransitioned > 0) {
                $this->info("Transitioned {$alreadyTransitioned} leads with existing emails to enriched.");
            }
            if ($processed > 0) {
                $this->info("Queued {$processed} leads for email enrichment.");
            }
            if ($errors) {
                $this->warn("{$errors} leads had errors.");
            }
        }

        // Log to activity feed
        $logger = app(ActivityLogger::class);
        $brandNames = $brandSlug
            ? [Brand::where('slug', $brandSlug)->value('name')]
            : ($leads ? $leads->pluck('brand.name')->unique()->values()->toArray() : []);

        $segmentLabel = $segment ? " ({$segment})" : '';
        $totalProcessed = $processed + $alreadyTransitioned;

        $logger->log([
            'source' => 'laravel.cli.enrich-batch',
            'event_type' => 'enrichment_batch',
            'title' => "Enrichment batch: {$totalProcessed} processed{$segmentLabel}"
                .($alreadyTransitioned > 0 ? " ({$alreadyTransitioned} auto-enriched)" : '')
                .($errors ? " — {$errors} failed" : ''),
            'metadata' => [
                'auto_enriched' => $alreadyTransitioned,
                'email_enrichment_queued' => $processed,
                'errors' => $errors,
                'brands' => $brandNames,
                'segment' => $segment,
            ],
            'severity' => $errors > 0 ? 'warning' : 'info',
        ]);

        return self::SUCCESS;
    }
}
