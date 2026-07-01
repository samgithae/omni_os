<?php

namespace App\Jobs\Scrapers;

use App\Contracts\JobSourceScraper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn Jobs Scraper
 *
 * Fetches job listings from LinkedIn using a session-based approach.
 *
 * IMPORTANT — PRODUCTION USAGE:
 * LinkedIn aggressively blocks raw HTTP requests from non-browser clients.
 * This implementation uses configurable session cookies (LINKEDIN_SESSION_TOKEN
 * and LINKEDIN_JSESSIONID environment variables) and browser-like headers to
 * attempt direct API/HTML access.
 *
 * For reliable production usage, consider using an automation layer such as
 * AdsPower (fingerprint browser) or a residential proxy service to obtain
 * and maintain valid LinkedIn sessions.  The cookie values will need to be
 * rotated periodically as LinkedIn invalidates sessions.
 *
 * Required environment variables:
 *   LINKEDIN_SESSION_TOKEN — li_at (session cookie) value
 *   LINKEDIN_JSESSIONID   — JSESSIONID value
 */
class LinkedInJobsScraper implements JobSourceScraper, ShouldQueue
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
     * LinkedIn API base for job search.
     */
    protected string $apiBase = 'https://www.linkedin.com';

    /**
     * Search keywords — broad Kenyan job search.
     */
    protected string $keywords = 'job Kenya';

    /**
     * Current page (offset) being fetched.
     */
    protected int $currentPage = 1;

    /**
     * Whether a next page is available.
     */
    protected bool $hasNext = true;

    /**
     * @var array<int, array<string, mixed>> Raw listings accumulated during fetch.
     */
    protected array $listings = [];

    /**
     * @var string|null LinkedIn session token (li_at cookie)
     */
    protected ?string $sessionToken = null;

    /**
     * @var string|null LinkedIn JSESSIONID
     */
    protected ?string $jsessionId = null;

    /**
     * Constructor.
     *
     * Reads session credentials from environment variables.
     */
    public function __construct()
    {
        $this->sessionToken = env('LINKEDIN_SESSION_TOKEN');
        $this->jsessionId = env('LINKEDIN_JSESSIONID');
    }

    /**
     * Build the common browser-like headers used for all LinkedIn requests.
     *
     * @return array<string, string>
     */
    protected function browserHeaders(): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"macOS"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
            'Dnt' => '1',
            'Connection' => 'keep-alive',
        ];

        if ($this->jsessionId !== null) {
            $headers['Csrf-Token'] = $this->jsessionId;
        }

        return $headers;
    }

    /**
     * Build the cookie string from session credentials.
     */
    protected function cookieString(): string
    {
        $cookies = [];
        if ($this->sessionToken !== null) {
            $cookies[] = 'li_at='.$this->sessionToken;
        }
        if ($this->jsessionId !== null) {
            $cookies[] = 'JSESSIONID="'.$this->jsessionId.'"';
        }
        // Always set a base user session cookie
        $cookies[] = 'lang=v=2&lang=en-us';
        $cookies[] = 'bcookie="v=2&'.uniqid('', true).'"';

        return implode('; ', $cookies);
    }

    /**
     * Fetch job listings from LinkedIn via HTML search.
     *
     * Uses the LinkedIn job search page with session cookies for authentication.
     * If no session credentials are configured, logs a warning and returns empty.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchListings(): array
    {
        $this->listings = [];
        $this->currentPage = 1;
        $this->hasNext = true;

        if (empty($this->sessionToken) || empty($this->jsessionId)) {
            Log::warning('LinkedInJobsScraper: LINKEDIN_SESSION_TOKEN or LINKEDIN_JSESSIONID not configured. Returning empty results.');
            $this->hasNext = false;

            return [];
        }

        while ($this->hasNext && $this->currentPage <= 5) {
            $url = $this->buildSearchUrl();

            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->withOptions([
                        'cookies' => true, // Enable cookie handling
                    ])
                    ->withCookies([
                        'li_at' => $this->sessionToken,
                        'JSESSIONID' => '"'.$this->jsessionId.'"',
                        'lang' => 'v=2&lang=en-us',
                    ], 'www.linkedin.com')
                    ->timeout(30)
                    ->get($url);

                if (! $response->successful()) {
                    $status = $response->status();
                    Log::warning("LinkedInJobsScraper: HTTP {$status} on page {$this->currentPage}");

                    if (in_array($status, [401, 403, 429], true)) {
                        // Session expired or blocked — stop
                        $this->hasNext = false;
                        break;
                    }

                    $this->currentPage++;

                    continue;
                }

                $html = $response->body();
                if (empty($html)) {
                    $this->hasNext = false;
                    break;
                }

                $this->parseSearchResults($html, $url);
                $this->currentPage++;
                $this->hasNext = $this->detectNextPage($html);

                // Polite delay between pages
                if ($this->hasNext) {
                    usleep(1_000_000); // 1s — LinkedIn rate-limits aggressively
                }
            } catch (\Exception $e) {
                Log::warning('LinkedInJobsScraper: error on page '.$this->currentPage.': '.$e->getMessage());
                $this->hasNext = false;
                break;
            }
        }

        return $this->listings;
    }

    /**
     * Build the LinkedIn job search URL for the current page.
     */
    protected function buildSearchUrl(): string
    {
        $params = http_build_query([
            'keywords' => $this->keywords,
            'location' => 'Kenya',
            'trk' => 'public_jobs_jobs-search-bar_search-submit',
            'start' => ($this->currentPage - 1) * 25,
        ]);

        return "{$this->apiBase}/jobs/search?{$params}";
    }

    /**
     * Parse LinkedIn search results HTML for job listing cards.
     *
     * LinkedIn's HTML structure changes frequently, so this uses multiple
     * fallback selectors to stay resilient.
     */
    protected function parseSearchResults(string $html, string $pageUrl): void
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_PARSEHUGE);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Multiple selector strategies for LinkedIn's varying markup
        $jobCards = $xpath->query('//li[contains(@class, "jobs-search-results__list-item")]');
        if ($jobCards === false || $jobCards->length === 0) {
            $jobCards = $xpath->query('//div[contains(@data-job-id, "")]');
        }
        if ($jobCards === false || $jobCards->length === 0) {
            $jobCards = $xpath->query('//a[contains(@href, "/jobs/view/")]/..');
        }
        if ($jobCards === false || $jobCards->length === 0) {
            // Try JSON embedded in the page (LinkedIn often embeds data in script tags)
            $this->parseEmbeddedJson($html, $pageUrl);

            return;
        }

        foreach ($jobCards as $card) {
            try {
                $raw = $this->extractCardData($xpath, $card, $pageUrl);
                if ($raw !== null) {
                    $this->listings[] = $raw;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    /**
     * Extract data from a single LinkedIn job card DOM element.
     *
     * @param  \DOMElement  $card
     * @return array<string, mixed>|null
     */
    protected function extractCardData(\DOMXPath $xpath, $card, string $pageUrl): ?array
    {
        // Job title
        $titleNode = $xpath->query('.//a[contains(@class, "job-card-list__title")]', $card)->item(0)
            ?? $xpath->query('.//h3/a | .//h3/span', $card)->item(0)
            ?? $xpath->query('.//span[contains(@class, "job-title")]', $card)->item(0)
            ?? $xpath->query('.//a[contains(@href, "/jobs/view")]', $card)->item(0);

        if ($titleNode === null) {
            return null;
        }

        $jobTitle = trim($titleNode->textContent);
        if (empty($jobTitle)) {
            return null;
        }

        // Job URL
        $jobUrl = '';
        if ($titleNode->nodeName === 'a') {
            $href = $titleNode->getAttribute('href');
            if (! empty($href)) {
                $jobUrl = str_starts_with($href, 'http') ? $href : $this->apiBase.$href;
            }
        }

        // Company name
        $companyNode = $xpath->query('.//a[contains(@class, "job-card-container__company-name")]', $card)->item(0)
            ?? $xpath->query('.//span[contains(@class, "company-name")]', $card)->item(0)
            ?? $xpath->query('.//h4/a | .//h4/span', $card)->item(0)
            ?? $xpath->query('.//span[contains(@class, "job-company")]', $card)->item(0);

        $companyName = $companyNode ? trim($companyNode->textContent) : 'Unknown Company';

        // Location
        $locationNode = $xpath->query('.//li[contains(@class, "job-card-container__metadata-item")]', $card)->item(0)
            ?? $xpath->query('.//span[contains(@class, "job-location")]', $card)->item(0)
            ?? $xpath->query('.//span[contains(@class, "location")]', $card)->item(0);

        $location = $locationNode ? trim($locationNode->textContent) : '';

        // Posted date — LinkedIn often shows "1 week ago", "30+ days ago", etc.
        $dateNode = $xpath->query('.//time', $card)->item(0)
            ?? $xpath->query('.//span[contains(@class, "job-card-container__listed-state")]', $card)->item(0)
            ?? $xpath->query('.//span[contains(@class, "posted-date")]', $card)->item(0);

        $postedDate = $dateNode ? trim($dateNode->textContent) : null;

        return [
            'title' => $jobTitle,
            'company' => $companyName,
            'url' => $jobUrl,
            'date' => $postedDate,
            'location' => $location,
            'source' => $this->sourceName(),
        ];
    }

    /**
     * Parse embedded JSON data that LinkedIn often includes in the page.
     *
     * LinkedIn sometimes stores job data in <script> tags with
     * data-based state or server-declosed data structures.
     */
    protected function parseEmbeddedJson(string $html, string $pageUrl): void
    {
        // Try to find job data in window.__INITIAL_STATE__ or similar
        preg_match('/window\.__INITIAL_STATE__\s*=\s*({.*?});\s*<\/script>/s', $html, $matches);
        if (empty($matches)) {
            preg_match('/window\.__UNDERSCORE_INITIAL_DATA__\s*=\s*({.*?});\s*<\/script>/s', $html, $matches);
        }
        if (empty($matches)) {
            return;
        }

        try {
            $state = json_decode($matches[1], true, 64, JSON_THROW_ON_ERROR);
            $results = $state['jobSearch']['searchResults'] ?? $state['results'] ?? [];
            if (empty($results)) {
                return;
            }

            foreach ($results as $result) {
                $listing = $result['jobPosting'] ?? $result;
                $title = $listing['title'] ?? $listing['jobTitle'] ?? null;
                if ($title === null) {
                    continue;
                }

                $this->listings[] = [
                    'title' => $title,
                    'company' => $listing['companyName'] ?? $listing['company']['name'] ?? 'Unknown Company',
                    'url' => $listing['url'] ?? $listing['jobUrl'] ?? $pageUrl,
                    'date' => $listing['postedDate'] ?? $listing['datePosted'] ?? null,
                    'location' => $listing['location'] ?? $listing['formattedLocation'] ?? '',
                    'source' => $this->sourceName(),
                ];
            }
        } catch (\JsonException $e) {
            // Not parseable
        }
    }

    /**
     * Detect if a "next page" exists in the LinkedIn HTML.
     */
    protected function detectNextPage(string $html): bool
    {
        return str_contains($html, 'aria-label="Next"') ||
               str_contains($html, 'jobs-search-pagination__next-button') ||
               str_contains($html, '"start":'.(($this->currentPage) * 25));
    }

    /**
     * Parse a raw LinkedIn listing into the standard structured format.
     *
     * @param  array<string, mixed>  $rawListing
     * @return array{company_name: string, website: ?string, job_title: string, posting_date: ?string, job_url: string, source: string}
     */
    public function parseListing(array $rawListing): array
    {
        $title = $rawListing['title'] ?? '';
        $relativeDate = $rawListing['date'] ?? null;

        return [
            'company_name' => $rawListing['company'] ?? 'Unknown',
            'website' => null, // LinkedIn doesn't expose company website in search results
            'job_title' => trim(preg_replace('/\s+/', ' ', $title)),
            'posting_date' => $this->parseRelativeDate($relativeDate),
            'job_url' => $rawListing['url'] ?? '',
            'source' => $this->sourceName(),
        ];
    }

    /**
     * Convert a LinkedIn relative date string into a Y-m-d format.
     *
     * Handles patterns like:
     *   - "1 week ago"
     *   - "2 weeks ago"
     *   - "3 days ago"
     *   - "30+ days ago"
     *   - "1 month ago"
     *   - "Today"
     *   - "Yesterday"
     */
    protected function parseRelativeDate(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }

        $relative = strtolower(trim($relative));

        if (in_array($relative, ['today', 'just now', 'now'], true)) {
            return now()->format('Y-m-d');
        }

        if ($relative === 'yesterday') {
            return now()->subDay()->format('Y-m-d');
        }

        // "X days ago" / "X+ days ago"
        if (preg_match('/^(\d+)\+?\s*day(?:s)?\s*ago$/', $relative, $m)) {
            return now()->subDays((int) $m[1])->format('Y-m-d');
        }

        // "X week(s) ago" / "X+ weeks ago"
        if (preg_match('/^(\d+)\+?\s*week(?:s)?\s*ago$/', $relative, $m)) {
            return now()->subWeeks((int) $m[1])->format('Y-m-d');
        }

        // "X month(s) ago" / "X+ months ago"
        if (preg_match('/^(\d+)\+?\s*month(?:s)?\s*ago$/', $relative, $m)) {
            return now()->subMonths((int) $m[1])->format('Y-m-d');
        }

        // "X year(s) ago"
        if (preg_match('/^(\d+)\+?\s*year(?:s)?\s*ago$/', $relative, $m)) {
            return now()->subYears((int) $m[1])->format('Y-m-d');
        }

        // Try Carbon parsing as a last resort
        try {
            return Carbon::parse($relative)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if more pages are available.
     */
    public function hasNextPage(): bool
    {
        return $this->hasNext;
    }

    /**
     * Source identifier.
     */
    public function sourceName(): string
    {
        return 'linkedin';
    }

    /**
     * Filter the fetched listings by target job titles and 30-day window,
     * exclude unwanted sources, and roll up to one lead per company.
     *
     * @param  array<int, array<string, mixed>>  $listings
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
                    $postingDate = Carbon::parse($entry['posting_date']);
                    if ($postingDate->lt(now()->subDays(30))) {
                        continue;
                    }
                } catch (\Exception $e) {
                    // Keep if date unparseable
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
