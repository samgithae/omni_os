<?php

namespace App\Jobs\Scrapers;

use App\Contracts\JobSourceScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrighterMondayScraper implements JobSourceScraper, ShouldQueue
{
    use Queueable;

    private const JINA_READER = 'https://r.jina.ai';

    private const BASE_URL = 'https://www.brightermonday.co.ke/jobs';

    private const MAX_PAGES = 5;

    private const MAX_LISTINGS = 80;

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

    /** @var array<string, true> */
    private array $seenSlugs = [];

    private int $currentPage = 1;

    private bool $hasMorePages = true;

    public function fetchListings(): array
    {
        $this->seenSlugs = [];
        $listings = [];

        for ($page = 1; $page <= self::MAX_PAGES && count($listings) < self::MAX_LISTINGS; $page++) {
            $url = self::BASE_URL.($page > 1 ? '?page='.$page : '');
            Log::info("BrighterMondayScraper: Fetching {$url} via Jina Reader");

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'X-Return-Format' => 'markdown',
                    'X-Engine' => 'reader',
                ])->timeout(60)->get(self::JINA_READER.'/'.urlencode($url));

                if (! $response->successful()) {
                    Log::warning("BrighterMondayScraper: Jina Reader HTTP {$response->status()} on page {$page}");
                    break;
                }

                $markdown = $response->body();
                $pageListings = $this->parseListingsFromMarkdown($markdown);

                if (empty($pageListings)) {
                    break;
                }

                // Dedup by listing slug
                $newCount = 0;
                foreach ($pageListings as $listing) {
                    $slug = $this->listingSlug($listing);
                    if (! isset($this->seenSlugs[$slug])) {
                        $this->seenSlugs[$slug] = true;
                        $listings[] = $listing;
                        $newCount++;
                    }
                }

                if ($newCount === 0) {
                    // No new listings — likely a pagination loop
                    break;
                }
            } catch (\Exception $e) {
                Log::error("BrighterMondayScraper: Error on page {$page}: {$e->getMessage()}");
                break;
            }
        }

        return $listings;
    }

    public function parseListing(array $rawListing): ?array
    {
        $companyName = $rawListing['company_name'] ?? '';
        $jobTitle = $rawListing['job_title'] ?? '';
        $postingDate = $rawListing['posting_date'] ?? date('Y-m-d');
        $jobUrl = $rawListing['job_url'] ?? '';

        $titleLower = mb_strtolower(trim($jobTitle));
        if (! $this->isTargetTitle($titleLower)) {
            return null;
        }

        if ($this->isInternshipOnly($titleLower, $jobTitle)) {
            return null;
        }

        $companyLower = mb_strtolower(trim($companyName));
        if ($this->isExcludedSource($companyLower)) {
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

    /**
     * Parse job listings from Jina Reader markdown output.
     */
    private function parseListingsFromMarkdown(string $markdown): array
    {
        $listings = [];

        // Split into sections by company name pattern: "**COMPANY_NAME**" followed by job info
        // BrighterMonday via Jina returns entries like:
        // "**Company Name**\n\nTitle | Location | Type | Salary\n\nDescription..."
        $lines = explode("\n", $markdown);
        $currentJob = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Detect company name: bold text at start of line
            if (preg_match('/^\*\*([^*]+)\*\*$/', $trimmed, $m)) {
                // Save previous job if exists
                if ($currentJob !== null && ! empty($currentJob['company_name'])) {
                    $listings[] = $currentJob;
                }
                $currentJob = [
                    'company_name' => trim($m[1]),
                    'job_title' => '',
                    'posting_date' => date('Y-m-d'),
                    'job_url' => '',
                    'company_description' => '',
                ];
                continue;
            }

            if ($currentJob === null) {
                continue;
            }

            // Detect job title: a line followed by location/type info
            // Pattern: "Job Title" on its own line, or "    - Job Title"
            if (empty($currentJob['job_title']) && preg_match('/^###\s*(.+)/', $trimmed, $m)) {
                $currentJob['job_title'] = trim($m[1]);
                continue;
            }

            // Detect location/type line: "Location | Type | Salary"
            if (! empty($currentJob['job_title']) && preg_match('/^(.+?)\s*\|/', $trimmed, $m)) {
                $currentJob['company_description'] = trim($m[1]);
                continue;
            }

            // Detect relative date: "X days ago", "Today", "Yesterday"
            if (preg_match('/\b(Today|Yesterday|\d+\s+days?\s+ago)\b/i', $trimmed, $dm)) {
                $dateStr = strtolower($dm[1]);
                if ($dateStr === 'today') {
                    $currentJob['posting_date'] = date('Y-m-d');
                } elseif ($dateStr === 'yesterday') {
                    $currentJob['posting_date'] = date('Y-m-d', strtotime('-1 day'));
                } elseif (preg_match('/(\d+)\s+day/', $dateStr, $dd)) {
                    $currentJob['posting_date'] = date('Y-m-d', strtotime('-'.(int)$dd[1].' days'));
                }
                continue;
            }
        }

        // Don't forget the last job
        if ($currentJob !== null && ! empty($currentJob['company_name'])) {
            $listings[] = $currentJob;
        }

        return $listings;
    }

    /**
     * Generate a unique slug for a listing for dedup.
     */
    private function listingSlug(array $listing): string
    {
        return mb_strtolower(trim(($listing['company_name'] ?? '').'|'.($listing['job_title'] ?? '')));
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

    private function isExcludedSource(string $companyLower): bool
    {
        foreach (self::EXCLUDE_PATTERNS as $pattern) {
            if (str_contains($companyLower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
