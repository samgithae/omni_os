<?php

namespace App\Console\Commands;

use App\Contracts\JobSourceScraper;
use App\Jobs\Scrapers\BrighterMondayScraper;
use App\Jobs\Scrapers\GenericJobBoardScraper;
use App\Jobs\Scrapers\LinkedInJobsScraper;
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
        'fuzu' => GenericJobBoardScraper::class,
        'myjobmag' => GenericJobBoardScraper::class,
        'corporatestaffing' => GenericJobBoardScraper::class,
        'glassdoor' => GenericJobBoardScraper::class,
        'company_careers' => GenericJobBoardScraper::class,
        'google_jobs' => GenericJobBoardScraper::class,
        'linkedin' => LinkedInJobsScraper::class,
    ];

    private const EXCLUDED_NAME_KEYWORDS = [
        'recruitment', 'staffing', 'employment agency', 'headhunter',
        'government', 'ministry of', 'county government',
        'internship', 'intern',
    ];

    private const TARGET_TITLES = [
        'sales rep', 'sales executive', 'business development officer', 'business development',
        'customer service', 'customer care', 'customer success', 'call centre agent',
        'contact centre agent', 'graduate trainee', 'management trainee', 'field officer',
        'relationship officer', 'branch officer', 'branch manager', 'operations officer',
        'loan officer', 'telesales agent', 'collections officer', 'account manager',
        'retail assistant', 'cashier', 'front office officer',
        'sales and marketing', 'marketing executive', 'marketing officer',
        'medical representative', 'medical rep', 'pharmaceutical sales',
        'hr officer', 'hr executive', 'hr assistant', 'human resource',
        'brand manager', 'brand officer', 'digital marketing',
        'travel consultant', 'travel advisor',
        'finance officer', 'finance manager', 'accountant',
        'administrative officer', 'admin officer', 'office administrator',
        'programme coordinator', 'program coordinator', 'project officer',
        'procurement officer', 'supply chain', 'logistics officer',
        'credit officer', 'risk officer', 'compliance officer',
        'training officer', 'learning and development', 'l&d officer',
        'quality assurance', 'quality control',
        'technical training', 'vocational training',
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
                $scraper = $this->createScraper($sourceSlug, $scraperClass);
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
                            'subcategory' => 'hiring',
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
                        'subcategory' => 'hiring',
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

    /**
     * Factory: create the right scraper instance with per-source config.
     */
    private function createScraper(string $sourceSlug, string $scraperClass): JobSourceScraper
    {
        // GenericJobBoardScraper needs source config
        if ($scraperClass === GenericJobBoardScraper::class) {
            $sourceConfigs = [
                'fuzu' => [
                    'url' => 'https://www.fuzu.com/jobs',
                    'targets' => self::TARGET_TITLES,
                    'exclude' => [],
                ],
                'myjobmag' => [
                    'url' => 'https://www.myjobmag.co.ke/jobs',
                    'targets' => self::TARGET_TITLES,
                    'exclude' => ['recruitment', 'staffing'],
                ],
                'corporatestaffing' => [
                    'url' => 'https://www.corporatestaffing.co.ke/jobs',
                    'targets' => self::TARGET_TITLES,
                    'exclude' => [],
                ],
                'glassdoor' => [
                    'url' => 'https://www.glassdoor.com/Job/kenya-jobs-SRCH_IL.0,5_IN179.htm',
                    'targets' => self::TARGET_TITLES,
                    'exclude' => [],
                ],
                'company_careers' => [
                    'url' => 'https://www.google.com/search?q=kenya+company+careers+page+hiring&tbm=nws',
                    'targets' => self::TARGET_TITLES,
                    'exclude' => ['recruitment'],
                ],
                'google_jobs' => [
                    'url' => 'https://www.google.com/search?q=jobs+in+kenya&ibp=htl;jobs',
                    'targets' => self::TARGET_TITLES,
                    'exclude' => [],
                ],
            ];

            $cfg = $sourceConfigs[$sourceSlug] ?? $sourceConfigs['fuzu'];

            return new GenericJobBoardScraper(
                $sourceSlug,
                $cfg['url'],
                $cfg['targets'],
                $cfg['exclude'],
                3
            );
        }

        // Other scrapers (BrighterMonday, LinkedIn) need no constructor args
        return new $scraperClass;
    }
}
