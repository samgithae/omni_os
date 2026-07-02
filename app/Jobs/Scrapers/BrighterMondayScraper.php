<?php

namespace App\Jobs\Scrapers;

use App\Contracts\JobSourceScraper;
use DOMDocument;
use DOMXPath;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrighterMondayScraper implements JobSourceScraper, ShouldQueue
{
    use Queueable;

    private const BASE_URL = 'https://www.brightermonday.co.ke/jobs';

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

    private const EXCLUDE_PATTERNS = [
        'recruitment', 'recruiting', 'staffing', 'employment agency', 'hr consultancy',
        'individual recruiter', 'government', 'internship only', 'one person',
        'sole proprietor', 'freelance platform',
    ];

    private const int MAX_PAGES = 5;

    private int $currentPage = 1;

    private bool $hasMorePages = true;

    /** @var array<string, array{job_titles: string[], count: int, posting_date: ?string, job_url: string}> */
    private array $companies = [];

    /** @var array<string, true> Tracks listing URLs already seen to detect pagination loops */
    private array $seenListingUrls = [];

    public function fetchListings(): array
    {
        $this->companies = [];
        $this->seenListingUrls = [];

        $rawListings = [];

        while ($this->hasMorePages && $this->currentPage <= self::MAX_PAGES && count($rawListings) < 100) {
            $url = $this->buildPageUrl($this->currentPage);
            Log::info("BrighterMondayScraper: Fetching {$url}");

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ])->timeout(30)->get($url);

                if (! $response->successful()) {
                    Log::warning("BrighterMondayScraper: HTTP {$response->status()} on page {$this->currentPage}");
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
                    $rawListings[] = $listing;
                }

                // Check for pagination loop: if all listing URLs on this page
                // were already seen on a previous page, stop paginating.
                $allSeen = true;
                $newCount = 0;
                foreach ($listings as $listing) {
                    $url = $listing['job_url'] ?? '';
                    if (! $url) {
                        continue;
                    }
                    if (! isset($this->seenListingUrls[$url])) {
                        $this->seenListingUrls[$url] = true;
                        $allSeen = false;
                        $newCount++;
                    }
                }
                if ($allSeen && $newCount === 0) {
                    $this->hasMorePages = false;
                    break;
                }

                $this->currentPage++;
            } catch (\Exception $e) {
                Log::error("BrighterMondayScraper: Error on page {$this->currentPage}: {$e->getMessage()}");
                $this->hasMorePages = false;
                break;
            }
        }

        return $rawListings;
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
            'source' => 'brightermonday',
        ];
    }

    public function hasNextPage(): bool
    {
        return $this->hasMorePages;
    }

    public function sourceName(): string
    {
        return 'brightermonday';
    }

    private function buildPageUrl(int $page): string
    {
        if ($page === 1) {
            return self::BASE_URL;
        }

        return self::BASE_URL.'?page='.$page;
    }

    private function parseListingsFromHtml(string $html): array
    {
        $listings = [];

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);

        // Current BM site: cards with data-cy="listing-cards-components"
        $jobNodes = $xpath->query("//div[@data-cy='listing-cards-components']");

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

    private function extractJobFromNode(DOMXPath $xpath, \DOMNode $node): ?array
    {
        // Job title: find the listing-title-link anchor, then the p inside it
        $titleNode = $xpath->query(".//a[@data-cy='listing-title-link']//p", $node);
        $jobTitle = '';
        $jobUrl = '';

        if ($titleNode !== false && $titleNode->length > 0) {
            $jobTitle = trim($titleNode->item(0)->textContent);
            // Get URL from the parent anchor
            $linkNode = $xpath->query(".//a[@data-cy='listing-title-link']", $node);
            if ($linkNode !== false && $linkNode->length > 0) {
                $href = $linkNode->item(0)->getAttribute('href');
                if (! str_starts_with($href, 'http')) {
                    $href = 'https://www.brightermonday.co.ke'.$href;
                }
                $jobUrl = $href;
            }
        }

        if (empty($jobTitle)) {
            return null;
        }

        // Company name: p.text-blue-700.text-loading-animate
        $companyNode = $xpath->query(".//p[contains(@class, 'text-blue-700')]", $node);
        $companyName = '';
        if ($companyNode !== false && $companyNode->length > 0) {
            $companyName = trim($companyNode->item(0)->textContent);
        }

        // Location: first span with bg-brand-secondary-100 class
        $location = '';
        $locationNode = $xpath->query(".//span[contains(@class, 'bg-brand-secondary-100')]", $node);
        if ($locationNode !== false && $locationNode->length > 0) {
            $location = trim($locationNode->item(0)->textContent);
        }

        // Category: second p.text-gray-500 or last one
        $category = '';
        $catNode = $xpath->query(".//p[contains(@class, 'text-gray-500')]", $node);
        if ($catNode !== false && $catNode->length > 0) {
            $category = trim($catNode->item($catNode->length - 1)->textContent);
        }

        // No posting date visible on the listing cards — mark as today
        $postingDate = date('Y-m-d');

        return [
            'company_name' => $companyName,
            'job_title' => $jobTitle,
            'posting_date' => $postingDate,
            'job_url' => $jobUrl,
            'company_description' => $category.' '.$location,
        ];
    }

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

    private function isTargetTitle(string $titleLower): bool
    {
        foreach (self::TARGET_TITLES as $target) {
            if (str_contains($titleLower, $target)) {
                return true;
            }
        }

        return false;
    }

    private function isInternshipOnly(string $titleLower, string $originalTitle): bool
    {
        $isInternship = str_contains($titleLower, 'intern');
        $isTrainee = str_contains($titleLower, 'graduate trainee') || str_contains($titleLower, 'management trainee') || str_contains($titleLower, 'trainee');

        return $isInternship && ! $isTrainee;
    }

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
