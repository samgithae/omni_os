<?php

namespace App\Jobs\Scrapers;

use App\Contracts\JobSourceScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompanyCareersScraper implements JobSourceScraper, ShouldQueue
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
     * Known company careers page URLs to crawl.
     *
     * In production, this would be loaded from a database or config file.
     *
     * @var array<int, array{name: string, careers_url: string}>
     */
    protected array $companyPages = [];

    /**
     * Current company index being processed.
     */
    protected int $currentIndex = 0;

    /**
     * @var array<int, array<string, mixed>> Raw listings accumulated during fetch.
     */
    protected array $listings = [];

    /**
     * Constructor.
     *
     * @param array<int, array{name: string, careers_url: string}>|null $companyPages
     */
    public function __construct(?array $companyPages = null)
    {
        $this->companyPages = $companyPages ?? $this->defaultCompanyPages();
    }

    /**
     * Default list of known Kenyan company careers pages.
     *
     * @return array<int, array{name: string, careers_url: string}>
     */
    protected function defaultCompanyPages(): array
    {
        return [
            ['name' => 'Safaricom', 'careers_url' => 'https://www.safaricom.co.ke/careers'],
            ['name' => 'Equity Bank', 'careers_url' => 'https://equitybankgroup.com/careers'],
            ['name' => 'KCB Bank', 'careers_url' => 'https://ke.kcbgroup.com/careers'],
            ['name' => 'Cooperative Bank', 'careers_url' => 'https://www.co-opbank.co.ke/careers'],
            ['name' => 'Absa Kenya', 'careers_url' => 'https://www.absa.co.ke/careers'],
            ['name' => 'Stanbic Bank', 'careers_url' => 'https://www.stanbicbank.co.ke/careers'],
            ['name' => 'NCBA Bank', 'careers_url' => 'https://www.ncbagroup.com/careers'],
            ['name' => 'I&M Bank', 'careers_url' => 'https://www.imbank.com/careers'],
            ['name' => 'Standard Chartered', 'careers_url' => 'https://www.sc.com/ke/careers'],
            ['name' => 'Diamond Trust Bank', 'careers_url' => 'https://www.dtbafrica.com/careers'],
            ['name' => 'Family Bank', 'careers_url' => 'https://www.familybank.co.ke/careers'],
            ['name' => 'Airtel Kenya', 'careers_url' => 'https://www.airtel.co.ke/careers'],
            ['name' => 'Jumo', 'careers_url' => 'https://jumo.world/careers'],
            ['name' => 'Cellulant', 'careers_url' => 'https://cellulant.com/careers'],
            ['name' => 'Twiga Foods', 'careers_url' => 'https://twiga.com/careers'],
            ['name' => 'M-Kopa', 'careers_url' => 'https://m-kopa.com/careers'],
            ['name' => 'Liquid Telecom', 'careers_url' => 'https://www.liquidtelecom.com/careers'],
            ['name' => 'Jubilee Insurance', 'careers_url' => 'https://www.jubileeinsurance.com/careers'],
            ['name' => 'Sanlam Kenya', 'careers_url' => 'https://www.sanlam.co.ke/careers'],
            ['name' => 'Britam', 'careers_url' => 'https://www.britam.com/careers'],
        ];
    }

    /**
     * Fetch job listings from known company careers pages.
     *
     * For each company page:
     *   1. Fetch the HTML.
     *   2. Parse JSON-LD (schema.org/JobPosting) from <script type="application/ld+json"> tags.
     *   3. If no JSON-LD found, fall back to keyword-based scraping of job title/description elements.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchListings(): array
    {
        $this->listings = [];
        $this->currentIndex = 0;

        foreach ($this->companyPages as $index => $company) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ])->timeout(15)->get($company['careers_url']);

                if (! $response->successful()) {
                    Log::info('CompanyCareersScraper: non-200 for '.$company['name'].' — '.$response->status());
                    continue;
                }

                $html = $response->body();
                if (empty($html)) {
                    continue;
                }

                $jobListings = $this->extractJobListings($html, $company);
                $this->listings = array_merge($this->listings, $jobListings);
                $this->currentIndex = $index;

                // Polite delay between requests
                usleep(300_000); // 0.3s
            } catch (\Exception $e) {
                Log::warning('CompanyCareersScraper: error fetching '.$company['name'].': '.$e->getMessage());
                continue;
            }
        }

        return $this->listings;
    }

    /**
     * Extract job listings from HTML for a given company.
     *
     * Strategy:
     *   1. Parse JSON-LD structured data (<script type="application/ld+json">)
     *      looking for @type === "JobPosting".
     *   2. If no structured data found, fall back to keyword scanning for
     *      common job listing patterns in the HTML.
     *
     * @param string $html
     * @param array{name: string, careers_url: string} $company
     * @return array<int, array<string, mixed>>
     */
    protected function extractJobListings(string $html, array $company): array
    {
        // Strategy 1: JSON-LD structured data
        $jsonLdListings = $this->parseJsonLd($html, $company);
        if (! empty($jsonLdListings)) {
            return $jsonLdListings;
        }

        // Strategy 2: Fall back to keyword scanning
        Log::info('CompanyCareersScraper: No JSON-LD found for '.$company['name'].', using keyword scan');
        return $this->keywordScan($html, $company);
    }

    /**
     * Parse JSON-LD script tags for schema.org/JobPosting entries.
     *
     * @param string $html
     * @param array{name: string, careers_url: string} $company
     * @return array<int, array<string, mixed>>
     */
    protected function parseJsonLd(string $html, array $company): array
    {
        $listings = [];

        // Match all <script type="application/ld+json"> blocks
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

                // Handle @graph containers and single items
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

                    // Normalize type — could be a string or array of types
                    $types = (array) $item['@type'];
                    if (! in_array('JobPosting', $types, true)) {
                        continue;
                    }

                    $listings[] = [
                        'title' => $item['title'] ?? $item['name'] ?? 'Unknown Position',
                        'company' => $company['name'],
                        'url' => $item['url'] ?? $company['careers_url'],
                        'date' => $item['datePosted'] ?? null,
                        'location' => $item['jobLocation'] ?? null,
                        'description' => $item['description'] ?? '',
                        'source' => $this->sourceName(),
                        '_company_website' => $this->resolveCompanyWebsite($company['name']),
                    ];
                }
            } catch (\JsonException $e) {
                // Skip malformed JSON-LD blocks
                continue;
            }
        }

        return $listings;
    }

    /**
     * Fallback keyword-based scan for job listings when no schema.org data is found.
     *
     * Looks for common HTML patterns: <a> tags with job-related class names,
     * heading elements near "careers" or "jobs" text, etc.
     *
     * @param string $html
     * @param array{name: string, careers_url: string} $company
     * @return array<int, array<string, mixed>>
     */
    protected function keywordScan(string $html, array $company): array
    {
        $listings = [];
        $lowerHtml = strtolower($html);

        // Use DOMDocument to find links/headings that look like job listings
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_PARSEHUGE);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Look for links with job-related classes or IDs
        $jobLinks = $xpath->query('//a[contains(@class, "job") or contains(@class, "career") or contains(@id, "job") or contains(@id, "career")]');
        if ($jobLinks === false || $jobLinks->length === 0) {
            // Broader: find any heading (h2/h3/h4) near a link on the page
            $jobLinks = $xpath->query('//h2/a | //h3/a | //h4/a');
        }

        if ($jobLinks === false || $jobLinks->length === 0) {
            return $listings;
        }

        foreach ($jobLinks as $link) {
            $title = trim($link->textContent);
            if (empty($title)) {
                continue;
            }

            // Skip links that don't look like job titles
            if ($this->isNavigationLink($title)) {
                continue;
            }

            $href = $link->getAttribute('href');
            $url = $company['careers_url'];
            if (! empty($href)) {
                $url = $this->resolveUrl($href, $company['careers_url']);
            }

            $listings[] = [
                'title' => $title,
                'company' => $company['name'],
                'url' => $url,
                'date' => null,
                'location' => null,
                'description' => '',
                'source' => $this->sourceName(),
                '_company_website' => $this->resolveCompanyWebsite($company['name']),
            ];
        }

        return $listings;
    }

    /**
     * Check if link text looks like a navigation link rather than a job title.
     */
    protected function isNavigationLink(string $text): bool
    {
        $navTerms = ['home', 'about', 'contact', 'privacy', 'terms', 'faq',
                     'sitemap', 'login', 'register', 'sign in', 'careers',
                     'all jobs', 'search', 'apply', 'submit',
        ];
        $lower = strtolower(trim($text));
        foreach ($navTerms as $term) {
            if ($lower === $term) {
                return true;
            }
        }
        return false;
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

        // Relative path
        $basePath = dirname($parts['path'] ?? '/');
        return "{$scheme}://{$host}{$basePath}/{$href}";
    }

    /**
     * Resolve a company website URL from name.
     */
    protected function resolveCompanyWebsite(string $companyName): ?string
    {
        foreach ($this->companyPages as $page) {
            if ($page['name'] === $companyName) {
                $parsed = parse_url($page['careers_url']);
                return ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');
            }
        }
        return null;
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
     * Check if more companies remain to be processed.
     */
    public function hasNextPage(): bool
    {
        return $this->currentIndex < count($this->companyPages) - 1;
    }

    /**
     * Source identifier.
     */
    public function sourceName(): string
    {
        return 'company_careers';
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
                    // If we can't parse the date, keep it
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
