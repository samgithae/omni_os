<?php

namespace App\Console\Commands;

use App\Jobs\Scrapers\BrighterMondayScraper;
use App\Jobs\Scrapers\CompanyCareersScraper;
use App\Jobs\Scrapers\CorporateStaffingScraper;
use App\Jobs\Scrapers\FuzuScraper;
use App\Jobs\Scrapers\GlassdoorScraper;
use App\Jobs\Scrapers\GoogleJobsScraper;
use App\Jobs\Scrapers\LinkedInJobsScraper;
use App\Jobs\Scrapers\MyJobMagScraper;
use App\Models\Brand;
use App\Models\Lead;
use App\Models\MiningTarget;
use App\Services\ActivityLogger;
use App\Services\HiringSignalScoreCalculator;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;

class MineHiringSignals extends Command
{
    protected $signature = 'leads:mine-hiring-signals
        {--source= : Run only one source (e.g. brightermonday)}
        {--brand=ujuziplus : Brand slug to mine for}
        {--dry-run : Show what would be done without creating leads}';

    protected $description = 'Mine Kenyan job boards for hiring-signal leads (Hiring Deer pipeline, Phase 1)';

    private const SOURCE_MAP = [
        'brightermonday' => BrighterMondayScraper::class,
        'fuzu' => FuzuScraper::class,
        'myjobmag' => MyJobMagScraper::class,
        'corporatestaffing' => CorporateStaffingScraper::class,
        'glassdoor' => GlassdoorScraper::class,
        'company_careers' => CompanyCareersScraper::class,
        'google_jobs' => GoogleJobsScraper::class,
        'linkedin' => LinkedInJobsScraper::class,
    ];

    private const EXCLUDED_NAME_KEYWORDS = [
        'recruitment', 'staffing', 'employment agency', 'headhunter',
        'government', 'ministry of', 'county government',
        'internship', 'intern',
    ];

    public function handle(HiringSignalScoreCalculator $scoreCalculator, ActivityLogger $logger): int
    {
        $brandSlug = $this->option('brand');
        $brand = Brand::where('slug', $brandSlug)->firstOrFail();
        $sourceFilter = $this->option('source');
        $dryRun = $this->option('dry-run');

        // Get mining targets that are hiring-signal sources (category starts with 'hiring_signal_')
        $targets = MiningTarget::query()
            ->where('brand_id', $brand->id)
            ->where('is_active', true)
            ->where('category', 'like', 'hiring_signal_%')
            ->when($sourceFilter, fn ($q) => $q->where('category', 'hiring_signal_'.$sourceFilter))
            ->get();

        if ($targets->isEmpty()) {
            $this->warn('No hiring-signal mining targets found. Run mining:seed-targets first.');
            $this->line('Expected targets with categories like: hiring_signal_brightermonday, hiring_signal_fuzu, etc.');

            return self::SUCCESS;
        }

        $stats = [
            'companies_scanned' => 0,
            'companies_qualified' => 0,
            'companies_excluded' => 0,
            'high_priority' => 0,
            'hr_contacts_found' => 0,
            'industries' => [],
            'errors' => 0,
            'by_source' => [],
        ];

        $newLeads = [];

        foreach ($targets as $target) {
            $sourceSlug = str_replace('hiring_signal_', '', $target->category);

            if (! isset(self::SOURCE_MAP[$sourceSlug])) {
                $this->warn("No scraper registered for source: {$sourceSlug}");

                continue;
            }

            $scraperClass = self::SOURCE_MAP[$sourceSlug];
            $this->info("Mining: {$sourceSlug}");

            try {
                $scraper = new $scraperClass;
                $listings = $scraper->fetchListings();

                $this->line('  Fetched: '.count($listings).' raw listings');

                $stats['by_source'][$sourceSlug] = $stats['by_source'][$sourceSlug] ?? 0;

                $companyResults = [];

                foreach ($listings as $rawListing) {
                    try {
                        $parsed = $scraper->parseListing($rawListing);

                        // Skip listings that don't match target criteria
                        if ($parsed === null) {
                            continue;
                        }

                        // Check exclusion keywords on company name
                        $companyName = $parsed['company_name'];
                        $lowerName = strtolower($companyName);

                        $isExcluded = false;
                        foreach (self::EXCLUDED_NAME_KEYWORDS as $keyword) {
                            if (str_contains($lowerName, $keyword)) {
                                $isExcluded = true;
                                $stats['companies_excluded']++;
                                break;
                            }
                        }

                        if ($isExcluded) {
                            continue;
                        }

                        // Roll up by company (dedup within the run)
                        $domain = $parsed['website'] ?? '';
                        $dedupKey = $companyName.'|'.$domain;

                        if (! isset($companyResults[$dedupKey])) {
                            $companyResults[$dedupKey] = [
                                'company_name' => $companyName,
                                'website' => $parsed['website'] ?? null,
                                'source_url' => $parsed['job_url'],
                                'vacancy_count' => 0,
                                'vacancy_titles' => [],
                                'posting_dates' => [],
                                'source' => $parsed['source'],
                            ];
                        }

                        $companyResults[$dedupKey]['vacancy_count']++;
                        $companyResults[$dedupKey]['vacancy_titles'][] = $parsed['job_title'];
                        if ($parsed['posting_date']) {
                            $companyResults[$dedupKey]['posting_dates'][] = $parsed['posting_date'];
                        }

                        $stats['companies_scanned']++;
                    } catch (\Throwable $e) {
                        $this->error("  Parse error: {$e->getMessage()}");
                        $stats['errors']++;
                    }
                }

                // Convert to leads
                foreach ($companyResults as $data) {
                    $rawData = [
                        'hiring_signal' => [
                            'vacancy_count' => $data['vacancy_count'],
                            'vacancy_titles' => array_unique($data['vacancy_titles']),
                            'posting_dates' => array_unique($data['posting_dates']),
                            'source' => $data['source'],
                            'mined_at' => now()->toIso8601String(),
                        ],
                    ];

                    if ($dryRun) {
                        $this->line("  [DRY RUN] Would create: {$data['company_name']} ({$data['vacancy_count']} vacancies)");
                        $stats['companies_qualified']++;
                    } else {
                        $newLeads[] = [
                            'company_name' => $data['company_name'],
                            'website' => $data['website'],
                            'source_url' => $data['source_url'],
                            'source' => 'hiring_signal_'.$data['source'],
                            'segment' => 'deer',
                            'raw_data' => $rawData,
                        ];
                        $stats['companies_qualified']++;
                    }

                    $stats['by_source'][$sourceSlug] = ($stats['by_source'][$sourceSlug] ?? 0) + 1;
                }

                // Mark mining target as mined
                if (! $dryRun) {
                    $target->update(['last_mined_at' => now()]);
                }

            } catch (\Throwable $e) {
                $this->error("  Source error ({$sourceSlug}): {$e->getMessage()}");
                $stats['errors']++;
            }
        }

        // Bulk create leads
        if (! $dryRun && ! empty($newLeads)) {
            $this->info('Creating '.count($newLeads).' leads via API...');

            // Use the existing bulk path - call the API endpoint
            // For now, create directly to avoid HTTP loop
            $created = 0;
            $duplicates = 0;

            foreach ($newLeads as $leadData) {
                try {
                    Lead::create([
                        'brand_id' => $brand->id,
                        'company_name' => $leadData['company_name'],
                        'website' => $leadData['website'] ?? null,
                        'source' => $leadData['source'],
                        'source_url' => $leadData['source_url'],
                        'segment' => $leadData['segment'],
                        'status' => 'new',
                        'raw_data' => $leadData['raw_data'],
                    ]);
                    $created++;
                } catch (QueryException $e) {
                    // UNIQUE(brand_id, email) violation — email is null so this is company_name dedup
                    if (str_contains($e->getMessage(), 'leads_brand_email_unique')) {
                        $duplicates++;
                    } else {
                        throw $e;
                    }
                } catch (\Throwable $e) {
                    $this->error("  Create error for {$leadData['company_name']}: {$e->getMessage()}");
                    $stats['errors']++;
                }
            }

            // Calculate hiring_signal_score for new leads
            if ($created > 0) {
                $scored = 0;
                Lead::where('brand_id', $brand->id)
                    ->where('source', 'like', 'hiring_signal_%')
                    ->whereNull('hiring_signal_score')
                    ->chunk(100, function ($leads) use ($scoreCalculator, &$scored) {
                        foreach ($leads as $lead) {
                            $score = $scoreCalculator->recalculate($lead);
                            if ($score >= 80) {
                                $scored++;
                            }
                        }
                    });
                $stats['high_priority'] = $scored;
            }

            $this->info("  Created: {$created}, Duplicates: {$duplicates}");
        }

        // Report
        $this->newLine();
        $this->info('=== Hiring Signal Mining Complete ===');
        $this->line("Companies scanned: {$stats['companies_scanned']}");
        $this->line("Companies qualified: {$stats['companies_qualified']}");
        $this->line("Companies excluded: {$stats['companies_excluded']}");
        $this->line("High priority (≥80): {$stats['high_priority']}");
        $this->line("Errors: {$stats['errors']}");

        $sourceStats = '';
        foreach ($stats['by_source'] as $src => $count) {
            $sourceStats .= "{$src}: {$count}, ";
        }
        $this->line('By source: '.trim($sourceStats, ', '));

        // Log to activity feed
        if (! $dryRun) {
            $logger->log([
                'source' => 'laravel.cli.mine-hiring-signals',
                'event_type' => 'mining_run',
                'title' => "Hiring signal mining: {$stats['companies_qualified']} companies qualified",
                'body' => "Scanned {$stats['companies_scanned']} job listings across "
                    .count($stats['by_source']).' sources. '
                    ."{$stats['high_priority']} high-priority leads (score ≥80). "
                    .trim($sourceStats, ', '),
                'metadata' => $stats,
                'severity' => $stats['errors'] > 0 ? 'warning' : 'info',
            ]);
        }

        return self::SUCCESS;
    }
}
