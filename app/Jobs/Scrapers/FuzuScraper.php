<?php

namespace App\Jobs\Scrapers;

use App\Contracts\JobSourceScraper;
use DOMDocument;
use DOMXPath;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FuzuScraper implements JobSourceScraper, ShouldQueue
{
    use Queueable;

    private const BASE_URL = 'https://www.fuzu.com/kenya/jobs';

    private const TARGET_TITLES = [
        'sales rep', 'sales executive', 'business development officer', 'business development',
        'customer service', 'customer care', 'customer success', 'call centre agent',
        'contact centre agent', 'graduate trainee', 'management trainee', 'field officer',
        'relationship officer', 'branch officer', 'branch manager', 'operations officer',
        'loan officer', 'telesales agent', 'collections officer', 'account manager',
        'retail assistant', 'cashier', 'front office officer',
    ];

    private const EXCLUDE_PATTERNS = [
        'recruitment', 'recruiting', 'staffing', 'employment agency', 'hr consultancy',
        'individual recruiter', 'government', 'internship only', 'one person',
        'sole proprietor', 'freelance platform',
    ];

    private int $currentPage = 1;

    private bool $hasMorePages = true;

    /** @var array<string, array{job_titles: string[], count: int, posting_date: ?string, job_url: string}> */
    private array $companies = [];

    public function fetchListings(): array
    {
        $this->companies = [];

        while ($this->hasMorePages && count($this->companies) < 100) {
            $url = $this->buildPageUrl($this->currentPage);
            Log::info("FuzuScraper: Fetching {$url}");

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ])->timeout(30)->get($url);

                if (! $response->successful()) {
                    Log::warning("FuzuScraper: HTTP {$response->status()} on page {$this->currentPage}");
                    $this->hasMorePages = false;
                    break;
                }

                $html = $response->body();
                $listings = $this->parseListingsFromHtml($html);

                if (empty($listings)) {
                    $this->hasMorePages = false;
                    break;
                }

                foreach ($listings as $listing) {
                    $parsed = $this->parseListing($listing);
                    if ($parsed !== null) {
                        $this->addCompanyLead($parsed);
                    }
                }

                $this->currentPage++;
            } catch (\Exception $e) {
                Log::error("FuzuScraper: Error on page {$this->currentPage}: {$e->getMessage()}");
                $this->hasMorePages = false;
                break;
            }
        }

        return array_values($this->companies);
    }

    public function parseListing(array $rawListing): ?array
    {
        $companyName = $rawListing['company_name'] ?? '';
        $jobTitle = $rawListing['job_title'] ?? '';
        $postingDate = $rawListing['posting_date'] ?? null;
        $jobUrl = $rawListing['job_url'] ?? '';
        $companyDescription = $rawListing['company_description'] ?? '';

        // Normalise and check title
        $titleLower = mb_strtolower(trim($jobTitle));
        if (! $this->isTargetTitle($titleLower)) {
            return null;
        }

        // Filter out internship-only roles (allow grad/management trainee)
        if ($this->isInternshipOnly($titleLower, $jobTitle)) {
            return null;
        }

        // Check company for exclusion patterns
        $companyLower = mb_strtolower(trim($companyName));
        $descLower = mb_strtolower(trim($companyDescription));
        if ($this->isExcludedSource($companyLower, $descLower)) {
            return null;
        }

        // Check posting date is within 30 days
        if ($postingDate && ! $this->isWithinDateRange($postingDate)) {
            return null;
        }

        return [
            'company_name' => $companyName,
            'website' => null,
            'job_title' => $jobTitle,
            'posting_date' => $postingDate,
            'job_url' => $jobUrl,
            'source' => 'fuzu',
        ];
    }

    public function hasNextPage(): bool
    {
        return $this->hasMorePages;
    }

    public function sourceName(): string
    {
        return 'fuzu';
    }

    /**
     * Build the URL for a given page number.
     */
    private function buildPageUrl(int $page): string
    {
        if ($page === 1) {
            return self::BASE_URL;
        }

        return self::BASE_URL.'?page='.$page;
    }

    /**
     * Parse job listings from the HTML of a page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseListingsFromHtml(string $html): array
    {
        $listings = [];

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);

        // Fuzu uses job-card class structure
        $jobNodes = $xpath->query("//a[contains(@class, 'job-card')]");
        if ($jobNodes === false || $jobNodes->length === 0) {
            $jobNodes = $xpath->query("//div[contains(@class, 'job-card')]");
        }
        if ($jobNodes === false || $jobNodes->length === 0) {
            // Fallback: look for links containing job titles
            $jobNodes = $xpath->query("//a[contains(@href, '/job/')]");
        }

        if ($jobNodes === false || $jobNodes->length === 0) {
            return $listings;
        }

        foreach ($jobNodes as $node) {
            try {
                $listing = $this->extractJobFromNode($xpath, $node);
                if ($listing !== null) {
                    $listings[] = $listing;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $listings;
    }

    /**
     * Extract job data from a single DOM node.
     *
     * @return array<string, mixed>|null
     */
    private function extractJobFromNode(DOMXPath $xpath, \DOMNode $node): ?array
    {
        $jobTitle = '';
        $jobUrl = '';

        // If node is an <a> tag itself, use it directly
        if ($node->nodeName === 'a') {
            $href = $node->getAttribute('href');
            $jobUrl = str_starts_with($href, 'http') ? $href : 'https://www.fuzu.com'.$href;
            $jobTitle = trim($node->textContent);
        } else {
            // Look for title within the node
            $titleNode = $xpath->query(".//h2 | .//h3 | .//span[contains(@class, 'title')] | .//div[contains(@class, 'title')]", $node);
            if ($titleNode !== false && $titleNode->length > 0) {
                $jobTitle = trim($titleNode->item(0)->textContent);
            }

            $linkNode = $xpath->query(".//a[contains(@href, '/job/')]", $node);
            if ($linkNode !== false && $linkNode->length > 0) {
                $href = $linkNode->item(0)->getAttribute('href');
                $jobUrl = str_starts_with($href, 'http') ? $href : 'https://www.fuzu.com'.$href;
            }
        }

        if (empty($jobTitle)) {
            return null;
        }

        // Company name
        $companyNode = $xpath->query(".//span[contains(@class, 'company')] | .//div[contains(@class, 'company')] | .//p[contains(@class, 'company')] | .//span[contains(@class, 'employer')]", $node);
        $companyName = '';
        if ($companyNode !== false && $companyNode->length > 0) {
            $companyName = trim($companyNode->item(0)->textContent);
        }

        // Posting date - Fuzu often shows "Posted X days ago"
        $dateNode = $xpath->query(".//span[contains(@class, 'date')] | .//time | .//span[contains(text(), 'ago')] | .//div[contains(text(), 'ago')] | .//span[contains(@class, 'posted')] | .//div[contains(@class, 'posted')]", $node);
        $postingDate = null;
        if ($dateNode !== false && $dateNode->length > 0) {
            $postingDate = trim($dateNode->item(0)->textContent);
        }

        // Company description/snippet
        $descNode = $xpath->query(".//p[contains(@class, 'description')] | .//div[contains(@class, 'summary')]", $node);
        $companyDescription = '';
        if ($descNode !== false && $descNode->length > 0) {
            $companyDescription = trim($descNode->item(0)->textContent);
        }

        return [
            'company_name' => $companyName,
            'job_title' => $jobTitle,
            'posting_date' => $postingDate,
            'job_url' => $jobUrl,
            'company_description' => $companyDescription,
        ];
    }

    /**
     * Accumulate leads by company, rolling up vacancy count and titles.
     */
    private function addCompanyLead(array $parsed): void
    {
        $key = mb_strtolower(trim($parsed['company_name']));

        if (isset($this->companies[$key])) {
            $this->companies[$key]['count']++;
            if (! in_array($parsed['job_title'], $this->companies[$key]['job_titles'], true)) {
                $this->companies[$key]['job_titles'][] = $parsed['job_title'];
            }
        } else {
            $this->companies[$key] = [
                'company_name' => $parsed['company_name'],
                'website' => $parsed['website'],
                'job_titles' => [$parsed['job_title']],
                'count' => 1,
                'posting_date' => $parsed['posting_date'],
                'job_url' => $parsed['job_url'],
                'source' => $parsed['source'],
            ];
        }
    }

    /**
     * Check if the job title matches a target role.
     */
    private function isTargetTitle(string $titleLower): bool
    {
        foreach (self::TARGET_TITLES as $target) {
            if (str_contains($titleLower, $target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the role is internship-only (not grad/management trainee).
     */
    private function isInternshipOnly(string $titleLower, string $originalTitle): bool
    {
        $isInternship = str_contains($titleLower, 'intern');
        $isTrainee = str_contains($titleLower, 'graduate trainee') || str_contains($titleLower, 'management trainee') || str_contains($titleLower, 'trainee');

        return $isInternship && ! $isTrainee;
    }

    /**
     * Check if the company matches exclusion patterns (agencies, govt, etc.).
     */
    private function isExcludedSource(string $companyLower, string $descriptionLower): bool
    {
        $combined = $companyLower.' '.$descriptionLower;

        foreach (self::EXCLUDE_PATTERNS as $pattern) {
            if (str_contains($combined, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the posting date is within the last 30 days.
     */
    private function isWithinDateRange(string $dateString): bool
    {
        try {
            if (preg_match('/(\d+)\s*(hour|day|week|month)s?\s*ago/i', $dateString, $matches)) {
                $value = (int) $matches[1];
                $unit = strtolower($matches[2]);

                return match ($unit) {
                    'hour' => true,
                    'day' => $value <= 30,
                    'week' => $value <= 4,
                    'month' => $value <= 1,
                    default => false,
                };
            }

            $date = new \DateTime($dateString);
            $diff = $date->diff(new \DateTime);

            return $diff->days <= 30;
        } catch (\Exception $e) {
            return true;
        }
    }
}
