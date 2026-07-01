<?php

namespace App\Jobs\Scrapers;

use App\Contracts\JobSourceScraper;
use DOMDocument;
use DOMXPath;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GlassdoorScraper implements JobSourceScraper, ShouldQueue
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
     * Exclusion keywords — listings mentioning these are skipped.
     */
    protected const EXCLUDE_KEYWORDS = [
        'recruitment agency', 'staffing firm', 'staffing agency',
        'individual recruiter', 'government', 'internship only',
        'one-person business', 'sole proprietor',
    ];

    /**
     * Base URL for Glassdoor Kenya searches.
     */
    protected string $baseUrl = 'https://www.glassdoor.com/Job/kenya-jobs-SRCH_IL.0,5_IN180.htm';

    /**
     * Current page number being fetched.
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
     * Fetch job listings from Glassdoor.
     *
     * Uses DOMDocument/DOMXPath for HTML parsing.  Resilient to anti-bot
     * measures — if the HTTP request is blocked (non-200 or empty body)
     * we simply return an empty array instead of crashing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchListings(): array
    {
        $this->listings = [];
        $this->currentPage = 1;
        $this->hasNext = true;

        while ($this->hasNext && $this->currentPage <= 5) { // limit to 5 pages
            $url = $this->buildPageUrl($this->currentPage);

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ])->timeout(30)->get($url);

                if (! $response->successful() || empty($response->body())) {
                    Log::warning('GlassdoorScraper: blocked or empty response on page '.$this->currentPage);
                    $this->hasNext = false;
                    break;
                }

                $this->parsePageHtml($response->body());
                $this->currentPage++;
                $this->hasNext = $this->detectNextPage($response->body());

                // Brief delay to be polite
                if ($this->hasNext) {
                    usleep(500_000); // 0.5s
                }
            } catch (\Exception $e) {
                Log::warning('GlassdoorScraper: HTTP/parse error on page '.$this->currentPage.': '.$e->getMessage());
                $this->hasNext = false;
                break;
            }
        }

        return $this->listings;
    }

    /**
     * Build the Glassdoor URL for a given page number.
     */
    protected function buildPageUrl(int $page): string
    {
        if ($page <= 1) {
            return $this->baseUrl;
        }

        return "https://www.glassdoor.com/Job/kenya-jobs-SRCH_IL.0,5_IN180_IP{$page}.htm";
    }

    /**
     * Parse Glassdoor HTML to extract job listing cards.
     */
    protected function parsePageHtml(string $html): void
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Glassdoor listing cards — common selectors
        $cards = $xpath->query('//article[contains(@class, "jobListing")]');
        if ($cards === false || $cards->length === 0) {
            $cards = $xpath->query('//li[contains(@class, "jl")]');
        }
        if ($cards === false || $cards->length === 0) {
            $cards = $xpath->query('//div[contains(@data-test, "jobListing")]');
        }
        if ($cards === false || $cards->length === 0) {
            return; // No listings found on this page
        }

        foreach ($cards as $card) {
            try {
                $raw = $this->extractCardData($xpath, $card);
                if ($raw !== null) {
                    $this->listings[] = $raw;
                }
            } catch (\Exception $e) {
                // Skip malformed cards
                continue;
            }
        }
    }

    /**
     * Extract raw data from a single listing card.
     *
     * @return array<string, mixed>|null
     */
    protected function extractCardData(DOMXPath $xpath, $card): ?array
    {
        // Extract job title
        $titleNode = $xpath->query('.//a[contains(@class, "jobTitle")]', $card)->item(0)
            ?? $xpath->query('.//a[@data-test="job-title"]', $card)->item(0)
            ?? $xpath->query('.//h2/a | .//h3/a', $card)->item(0);

        if ($titleNode === null) {
            return null;
        }

        $jobTitle = trim($titleNode->textContent);
        $jobUrl = $titleNode->getAttribute('href');
        if (! empty($jobUrl) && ! str_starts_with($jobUrl, 'http')) {
            $jobUrl = 'https://www.glassdoor.com' . $jobUrl;
        }

        // Extract company name
        $companyNode = $xpath->query('.//div[contains(@class, "employerName")]', $card)->item(0)
            ?? $xpath->query('.//span[@data-test="employer-name"]', $card)->item(0)
            ?? $xpath->query('.//div[contains(@class, "company")]', $card)->item(0);

        $companyName = $companyNode ? trim($companyNode->textContent) : 'Unknown Company';

        // Extract posting date (relative — "30d+", "7d", etc.)
        $dateNode = $xpath->query('.//span[contains(@class, "date")]', $card)->item(0)
            ?? $xpath->query('.//div[contains(@class, "age")]', $card)->item(0)
            ?? $xpath->query('.//time', $card)->item(0);

        $postingDate = $dateNode ? trim($dateNode->textContent) : null;

        // Extract location
        $locationNode = $xpath->query('.//span[contains(@class, "location")]', $card)->item(0)
            ?? $xpath->query('.//div[@data-test="location"]', $card)->item(0);

        $location = $locationNode ? trim($locationNode->textContent) : '';

        return [
            'title' => $jobTitle,
            'company' => $companyName,
            'url' => $jobUrl,
            'date' => $postingDate,
            'location' => $location,
            'source' => $this->sourceName(),
        ];
    }

    /**
     * Detect if a "next page" link exists in the HTML.
     */
    protected function detectNextPage(string $html): bool
    {
        return str_contains($html, 'class="next"') ||
               str_contains($html, 'data-test="pagination-next"') ||
               str_contains($html, 'IP_'.($this->currentPage + 1));
    }

    /**
     * Parse a raw Glassdoor listing into the standard structured format.
     *
     * @param array<string, mixed> $rawListing
     * @return array{company_name: string, website: ?string, job_title: string, posting_date: ?string, job_url: string, source: string}
     */
    public function parseListing(array $rawListing): array
    {
        $title = $rawListing['title'] ?? '';

        return [
            'company_name' => $rawListing['company'] ?? 'Unknown',
            'website' => null, // Glassdoor doesn't expose company website easily
            'job_title' => $this->normalizeTitle($title),
            'posting_date' => $this->parseRelativeDate($rawListing['date'] ?? null),
            'job_url' => $rawListing['url'] ?? '',
            'source' => $this->sourceName(),
        ];
    }

    /**
     * Normalise a job title to a canonical form for duplicate detection.
     */
    protected function normalizeTitle(string $title): string
    {
        return trim(preg_replace('/\s+/', ' ', $title));
    }

    /**
     * Attempt to convert a relative date string ("30d+", "7d", "Today")
     * into a Y-m-d string, or return null if unparseable.
     */
    protected function parseRelativeDate(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }

        $relative = strtolower(trim($relative));

        if (in_array($relative, ['today', 'now', 'just posted'], true)) {
            return now()->format('Y-m-d');
        }

        if (in_array($relative, ['yesterday'], true)) {
            return now()->subDay()->format('Y-m-d');
        }

        if (preg_match('/^(\d+)\s*d(?:ay)?s?\+?$/', $relative, $m)) {
            $days = (int) $m[1];
            return now()->subDays($days)->format('Y-m-d');
        }

        if (preg_match('/^(\d+)\s*h(?:our)?s?\+?$/', $relative, $m)) {
            return now()->format('Y-m-d');
        }

        return $relative; // Return as-is if we can't parse
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
        return 'glassdoor';
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
                $postingDate = \Carbon\Carbon::parse($entry['posting_date']);
                if ($postingDate->lt(now()->subDays(30))) {
                    continue;
                }
            }

            $parsed[] = $entry;
        }

        // Roll up: one lead per company, aggregate titles
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
                // Use most recent posting date
                if ($entry['posting_date'] !== null) {
                    if ($byCompany[$key]['posting_date'] === null ||
                        $entry['posting_date'] > $byCompany[$key]['posting_date']) {
                        $byCompany[$key]['posting_date'] = $entry['posting_date'];
                        $byCompany[$key]['job_url'] = $entry['job_url'];
                    }
                }
            }
        }

        // Format final output
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
