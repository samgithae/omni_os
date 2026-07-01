<?php

namespace App\Jobs\Scrapers;

use App\Contracts\JobSourceScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleJobsScraper implements JobSourceScraper, ShouldQueue
{
    use Queueable;

    /**
     * Target job titles we are interested in.
     */
    protected const TARGET_TITLES = [
        'Sales Rep', 'Sales Executive', 'Business Development Officer',
        'Customer Service', 'Customer Care', 'Customer Success',
        'Call Centre Agent', 'Contact Centre Agent',
        'Graduate Trainee', 'Management Trainee',
        'Field Officer', 'Relationship Officer', 'Branch Officer', 'Branch Manager',
        'Operations Officer', 'Loan Officer', 'Telesales Agent',
        'Collections Officer', 'Account Manager',
        'Retail Assistant', 'Cashier', 'Front Office Officer',
    ];

    /**
     * Exclusion keywords.
     */
    protected const EXCLUDE_KEYWORDS = [
        'recruitment agency', 'staffing firm', 'staffing agency',
        'individual recruiter', 'government', 'internship only',
        'one-person business', 'sole proprietor',
    ];

    /**
     * Known company website URLs to crawl for JobPosting schema data.
     *
     * Unlike CompanyCareersScraper which hits career-pages subdirectories,
     * this scraper targets the main company websites and discovers careers
     * pages from them, then extracts schema.org/JobPosting data.
     *
     * @var array<int, array{name: string, website: string}>
     */
    protected array $companySites = [];

    /**
     * Current site index being processed.
     */
    protected int $currentIndex = 0;

    /**
     * @var array<int, array<string, mixed>> Raw listings accumulated during fetch.
     */
    protected array $listings = [];

    /**
     * Constructor.
     *
     * @param array<int, array{name: string, website: string}>|null $companySites
     */
    public function __construct(?array $companySites = null)
    {
        $this->companySites = $companySites ?? $this->defaultCompanySites();
    }

    /**
     * Default list of known Kenyan company websites.
     *
     * @return array<int, array{name: string, website: string}>
     */
    protected function defaultCompanySites(): array
    {
        return [
            ['name' => 'Safaricom', 'website' => 'https://www.safaricom.co.ke'],
            ['name' => 'Equity Bank', 'website' => 'https://equitybankgroup.com'],
            ['name' => 'KCB Bank', 'website' => 'https://ke.kcbgroup.com'],
            ['name' => 'Cooperative Bank', 'website' => 'https://www.co-opbank.co.ke'],
            ['name' => 'Absa Kenya', 'website' => 'https://www.absa.co.ke'],
            ['name' => 'Stanbic Bank', 'website' => 'https://www.stanbicbank.co.ke'],
            ['name' => 'NCBA Bank', 'website' => 'https://www.ncbagroup.com'],
            ['name' => 'I&M Bank', 'website' => 'https://www.imbank.com'],
            ['name' => 'Standard Chartered Kenya', 'website' => 'https://www.sc.com/ke'],
            ['name' => 'Diamond Trust Bank', 'website' => 'https://www.dtbafrica.com'],
            ['name' => 'Family Bank', 'website' => 'https://www.familybank.co.ke'],
            ['name' => 'Airtel Kenya', 'website' => 'https://www.airtel.co.ke'],
            ['name' => 'Jumo', 'website' => 'https://jumo.world'],
            ['name' => 'Cellulant', 'website' => 'https://cellulant.com'],
            ['name' => 'Twiga Foods', 'website' => 'https://twiga.com'],
            ['name' => 'M-Kopa', 'website' => 'https://m-kopa.com'],
            ['name' => 'Liquid Telecom', 'website' => 'https://www.liquidtelecom.com'],
            ['name' => 'Jubilee Insurance', 'website' => 'https://www.jubileeinsurance.com'],
            ['name' => 'Sanlam Kenya', 'website' => 'https://www.sanlam.co.ke'],
            ['name' => 'Britam', 'website' => 'https://www.britam.com'],
        ];
    }

    /**
     * Common careers-page path patterns to try on each company website.
     */
    protected const CAREERS_PATHS = [
        '/careers', '/jobs', '/career', '/join-us', '/work-with-us',
        '/about/careers', '/about/jobs', '/careers/jobs',
        '/careers/current-openings', '/opportunities',
    ];

    /**
     * Fetch job listings by discovering and scraping company careers pages
     * for schema.org/JobPosting structured data.
     *
     * For each company website:
     *   1. Attempt to discover the careers page by trying common URL paths.
     *   2. Fetch the discovered careers page HTML.
     *   3. Parse JSON-LD (<script type="application/ld+json">) for JobPosting schema.
     *   4. If no JSON-LD, try to find job listing links on the page and
     *      follow them to find schema data on individual job pages.
     *
     * Note: This scraper does NOT scrape Google's job-search UI directly.
     * It uses the same JobPosting-schema approach as CompanyCareersScraper
     * but focuses on discovering pages from company websites.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchListings(): array
    {
        $this->listings = [];
        $this->currentIndex = 0;

        foreach ($this->companySites as $index => $company) {
            try {
                $careersUrl = $this->discoverCareersPage($company['website']);
                if ($careersUrl === null) {
                    Log::info('GoogleJobsScraper: No careers page found for '.$company['name']);
                    continue;
                }

                $listings = $this->scrapeCareersPage($careersUrl, $company);
                $this->listings = array_merge($this->listings, $listings);
                $this->currentIndex = $index;

                // Polite delay
                usleep(500_000); // 0.5s
            } catch (\Exception $e) {
                Log::warning('GoogleJobsScraper: error processing '.$company['name'].': '.$e->getMessage());
                continue;
            }
        }

        return $this->listings;
    }

    /**
     * Try to discover the careers page URL for a given company website.
     *
     * @param string $website
     * @return string|null
     */
    protected function discoverCareersPage(string $website): ?string
    {
        // Try common careers paths
        foreach (self::CAREERS_PATHS as $path) {
            $url = rtrim($website, '/').$path;

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ])->timeout(10)->get($url);

                if ($response->successful() && ! empty($response->body())) {
                    // Quick validation: check the page mentions jobs/careers
                    $lowerBody = strtolower($response->body());
                    if (str_contains($lowerBody, 'job') || str_contains($lowerBody, 'career') || str_contains($lowerBody, 'vacanc')) {
                        return $url;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Scrape a careers page for JobPosting schema data.
     *
     * @param string $careersUrl
     * @param array{name: string, website: string} $company
     * @return array<int, array<string, mixed>>
     */
    protected function scrapeCareersPage(string $careersUrl, array $company): array
    {
        $listings = [];

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])->timeout(15)->get($careersUrl);

            if (! $response->successful() || empty($response->body())) {
                return $listings;
            }

            $html = $response->body();

            // First: try JSON-LD on the careers page itself
            $jsonLdListings = $this->parseJsonLd($html, $company, $careersUrl);
            if (! empty($jsonLdListings)) {
                return $jsonLdListings;
            }

            // Second: try to find individual job listing links and scrape those
            $jobPageUrls = $this->findJobPageLinks($html, $careersUrl);
            foreach ($jobPageUrls as $jobUrl) {
                try {
                    $jobListings = $this->scrapeIndividualJobPage($jobUrl, $company);
                    $listings = array_merge($listings, $jobListings);

                    // Rate-limit between individual job pages
                    usleep(300_000);
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Third: if still nothing, try keyword scan fallback
            if (empty($listings)) {
                $listings = $this->keywordScanFallback($html, $company, $careersUrl);
            }
        } catch (\Exception $e) {
            Log::warning('GoogleJobsScraper: error scraping '.$careersUrl.': '.$e->getMessage());
        }

        return $listings;
    }

    /**
     * Parse JSON-LD script tags for schema.org/JobPosting entries.
     *
     * @param string $html
     * @param array{name: string, website: string} $company
     * @param string $pageUrl
     * @return array<int, array<string, mixed>>
     */
    protected function parseJsonLd(string $html, array $company, string $pageUrl): array
    {
        $listings = [];

        preg_match_all(
            '/<script\s+type=["\']application\/ld\+json["\']>(.*?)<\/script>/s',
            $html,
            $matches
        );

        foreach ($matches[1] as $json) {
            $json = trim($json);
            if (empty($json)) {
                continue;
            }

            try {
                $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
                $items = [];

                if (isset($data['@graph']) && is_array($data['@graph'])) {
                    $items = $data['@graph'];
                } elseif (isset($data['@type'])) {
                    $items = [$data];
                } elseif (is_array($data) && isset($data[0])) {
                    $items = $data;
                }

                foreach ($items as $item) {
                    if (! isset($item['@type'])) {
                        continue;
                    }

                    $types = (array) $item['@type'];
                    if (! in_array('JobPosting', $types, true)) {
                        continue;
                    }

                    $listings[] = [
                        'title' => $item['title'] ?? $item['name'] ?? 'Unknown Position',
                        'company' => $company['name'],
                        'url' => $item['url'] ?? $pageUrl,
                        'date' => $item['datePosted'] ?? null,
                        'location' => $item['jobLocation'] ?? null,
                        'description' => $item['description'] ?? '',
                        'source' => $this->sourceName(),
                        '_company_website' => $company['website'],
                    ];
                }
            } catch (\JsonException $e) {
                continue;
            }
        }

        return $listings;
    }

    /**
     * Find links to individual job pages from a careers listing page.
     *
     * @param string $html
     * @param string $baseUrl
     * @return array<int, string>
     */
    protected function findJobPageLinks(string $html, string $baseUrl): array
    {
        $urls = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_PARSEHUGE);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Find links that look like they point to individual job pages
        $links = $xpath->query('//a[contains(@href, "job") or contains(@href, "career") or contains(@href, "position") or contains(@href, "opening") or contains(@href, "vacanc")]');

        if ($links === false || $links->length === 0) {
            return $urls;
        }

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (empty($href)) {
                continue;
            }

            // Resolve relative URLs
            if (! str_starts_with($href, 'http://') && ! str_starts_with($href, 'https://')) {
                $parsed = parse_url($baseUrl);
                $scheme = $parsed['scheme'] ?? 'https';
                $host = $parsed['host'] ?? '';

                if (str_starts_with($href, '/')) {
                    $href = "{$scheme}://{$host}{$href}";
                } else {
                    $basePath = dirname($parsed['path'] ?? '/');
                    $href = "{$scheme}://{$host}{$basePath}/{$href}";
                }
            }

            // Avoid duplicates and navigation links
            $seenUrls = array_map(fn ($u) => rtrim($u, '/'), $urls);
            $normalized = rtrim($href, '/');
            if ($normalized !== rtrim($baseUrl, '/') && ! in_array($normalized, $seenUrls, true)) {
                $urls[] = $href;
            }
        }

        // Limit to a reasonable number
        return array_slice($urls, 0, 20);
    }

    /**
     * Scrape an individual job listing page for JSON-LD schema data.
     *
     * @param string $jobUrl
     * @param array{name: string, website: string} $company
     * @return array<int, array<string, mixed>>
     */
    protected function scrapeIndividualJobPage(string $jobUrl, array $company): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])->timeout(10)->get($jobUrl);

            if (! $response->successful() || empty($response->body())) {
                return [];
            }

            return $this->parseJsonLd($response->body(), $company, $jobUrl);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Fallback keyword scan when no structured data or job links found.
     *
     * @param string $html
     * @param array{name: string, website: string} $company
     * @param string $pageUrl
     * @return array<int, array<string, mixed>>
     */
    protected function keywordScanFallback(string $html, array $company, string $pageUrl): array
    {
        $listings = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_PARSEHUGE);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Look for heading-based listing patterns
        $nodes = $xpath->query('//h2/a | //h3/a | //h4/a');
        if ($nodes === false || $nodes->length === 0) {
            // Try list items with links
            $nodes = $xpath->query('//li/a');
        }

        if ($nodes !== false && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $title = trim($node->textContent);
                if (empty($title) || strlen($title) > 200) {
                    continue;
                }

                $href = $node->getAttribute('href');
                $url = empty($href) ? $pageUrl : $this->resolveUrl($href, $pageUrl);

                $listings[] = [
                    'title' => $title,
                    'company' => $company['name'],
                    'url' => $url,
                    'date' => null,
                    'location' => null,
                    'description' => '',
                    'source' => $this->sourceName(),
                    '_company_website' => $company['website'],
                ];
            }
        }

        return $listings;
    }

    /**
     * Resolve a potentially relative URL to absolute.
     */
    protected function resolveUrl(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$href}";
        }

        $basePath = dirname($parts['path'] ?? '/');
        return "{$scheme}://{$host}{$basePath}/{$href}";
    }

    /**
     * Parse a raw listing into the standard structured format.
     *
     * @param array<string, mixed> $rawListing
     * @return array{company_name: string, website: ?string, job_title: string, posting_date: ?string, job_url: string, source: string}
     */
    public function parseListing(array $rawListing): array
    {
        $postingDate = $rawListing['date'] ?? null;
        if ($postingDate !== null && $postingDate !== '') {
            try {
                $postingDate = \Carbon\Carbon::parse($postingDate)->format('Y-m-d');
            } catch (\Exception $e) {
                $postingDate = null;
            }
        }

        return [
            'company_name' => $rawListing['company'] ?? 'Unknown',
            'website' => $rawListing['_company_website'] ?? null,
            'job_title' => trim(preg_replace('/\s+/', ' ', $rawListing['title'] ?? '')),
            'posting_date' => $postingDate,
            'job_url' => $rawListing['url'] ?? '',
            'source' => $this->sourceName(),
        ];
    }

    /**
     * Check if more company sites remain to be processed.
     */
    public function hasNextPage(): bool
    {
        return $this->currentIndex < count($this->companySites) - 1;
    }

    /**
     * Source identifier.
     */
    public function sourceName(): string
    {
        return 'google_jobs';
    }

    /**
     * Filter the fetched listings by target job titles and 30-day window,
     * exclude unwanted sources, and roll up to one lead per company.
     *
     * @param array<int, array<string, mixed>> $listings
     * @return array<int, array{company_name: string, website: ?string, job_title: string, posting_date: ?string, job_url: string, source: string}>
     */
    public function filterAndRollUp(array $listings): array
    {
        $parsed = [];
        foreach ($listings as $raw) {
            $entry = $this->parseListing($raw);

            // Check exclusion keywords
            $combined = strtolower($entry['company_name'].' '.$entry['job_title']);
            $excluded = false;
            foreach (self::EXCLUDE_KEYWORDS as $keyword) {
                if (str_contains($combined, $keyword)) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            // Check target title match
            if (! $this->matchesTargetTitle($entry['job_title'])) {
                continue;
            }

            // Check 30-day window
            if ($entry['posting_date'] !== null) {
                try {
                    $postingDate = \Carbon\Carbon::parse($entry['posting_date']);
                    if ($postingDate->lt(now()->subDays(30))) {
                        continue;
                    }
                } catch (\Exception $e) {
                    // Keep listing if date unparseable
                }
            }

            $parsed[] = $entry;
        }

        // Roll up: one lead per company
        $byCompany = [];
        foreach ($parsed as $entry) {
            $key = strtolower($entry['company_name']);
            if (! isset($byCompany[$key])) {
                $byCompany[$key] = [
                    'company_name' => $entry['company_name'],
                    'website' => $entry['website'],
                    'job_title' => $entry['job_title'],
                    'posting_date' => $entry['posting_date'],
                    'job_url' => $entry['job_url'],
                    'source' => $entry['source'],
                    '_vacancy_count' => 1,
                    '_titles' => [$entry['job_title']],
                ];
            } else {
                $byCompany[$key]['_vacancy_count']++;
                if (! in_array($entry['job_title'], $byCompany[$key]['_titles'], true)) {
                    $byCompany[$key]['_titles'][] = $entry['job_title'];
                }
                if ($entry['posting_date'] !== null) {
                    if ($byCompany[$key]['posting_date'] === null ||
                        $entry['posting_date'] > $byCompany[$key]['posting_date']) {
                        $byCompany[$key]['posting_date'] = $entry['posting_date'];
                        $byCompany[$key]['job_url'] = $entry['job_url'];
                    }
                }
            }
        }

        $result = [];
        foreach ($byCompany as $entry) {
            $count = $entry['_vacancy_count'];
            $titles = $entry['_titles'];
            $label = $count > 1
                ? sprintf('%s (+%d more)', $entry['job_title'], $count - 1)
                : $entry['job_title'];

            $result[] = [
                'company_name' => $entry['company_name'],
                'website' => $entry['website'],
                'job_title' => $label,
                'posting_date' => $entry['posting_date'],
                'job_url' => $entry['job_url'],
                'source' => $entry['source'],
            ];
        }

        return $result;
    }

    /**
     * Check whether a job title matches any of the target titles (case-insensitive).
     */
    protected function matchesTargetTitle(string $title): bool
    {
        $lower = strtolower($title);
        foreach (self::TARGET_TITLES as $target) {
            if (str_contains($lower, strtolower($target))) {
                return true;
            }
        }
        return false;
    }
}
