<?php

namespace App\Jobs\Scrapers;

use App\Contracts\JobSourceScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generic web scraper using Agent Reach's Jina Reader.
 * Replaces all individual HTML/DOM-based scrapers.
 * Parses markdown output from r.jina.ai for any job board URL.
 */
class GenericJobBoardScraper implements JobSourceScraper, ShouldQueue
{
    use Queueable, UsesJinaReader;

    private string $sourceName;
    private string $baseUrl;
    private array $targetTitles;
    private array $excludePatterns;
    private int $pageLimit;

    /** @var array<int, array<string, mixed>> */
    private array $listings = [];

    /**
     * @param string $sourceName Internal source identifier (e.g. 'fuzu', 'myjobmag')
     * @param string $baseUrl URL to scrape
     * @param array $targetTitles List of target job title keywords
     * @param array $excludePatterns Company name exclusion patterns
     * @param int $maxPages Max pages to fetch
     */
    public function __construct(
        string $sourceName,
        string $baseUrl,
        array $targetTitles = [],
        array $excludePatterns = [],
        int $maxPages = 3,
    ) {
        $this->sourceName = $sourceName;
        $this->baseUrl = $baseUrl;
        $this->targetTitles = $targetTitles;
        $this->excludePatterns = $excludePatterns;
        $this->pageLimit = $maxPages;
    }

    public function fetchListings(): array
    {
        $this->listings = [];

        for ($page = 1; $page <= $this->pageLimit; $page++) {
            $url = $this->baseUrl.($page > 1 ? "?page={$page}" : '');
            $markdown = $this->fetchViaJinaReader($url);

            if ($markdown === null) {
                break;
            }

            $pageListings = $this->parseListingsFromMarkdown($markdown);
            $newCount = 0;

            foreach ($pageListings as $listing) {
                $key = ($listing['company_name'] ?? '').'|'.($listing['job_title'] ?? '');
                if (! $this->isDuplicate($key)) {
                    $this->listings[] = $listing;
                    $newCount++;
                }
            }

            if ($newCount === 0) {
                break;
            }
        }

        return $this->listings;
    }

    public function parseListing(array $rawListing): ?array
    {
        $companyName = $rawListing['company_name'] ?? '';
        $jobTitle = $rawListing['job_title'] ?? '';
        $postingDate = $rawListing['posting_date'] ?? date('Y-m-d');
        $jobUrl = $rawListing['job_url'] ?? '';

        $titleLower = mb_strtolower(trim($jobTitle));
        if (! $this->matchesTargetTitle($titleLower)) {
            return null;
        }

        if ($this->isInternshipOnly($titleLower, $jobTitle)) {
            return null;
        }

        $companyLower = mb_strtolower(trim($companyName));
        if ($this->matchesExcludePattern($companyLower)) {
            return null;
        }

        return [
            'company_name' => $companyName,
            'website' => null,
            'job_title' => $jobTitle,
            'posting_date' => $postingDate,
            'job_url' => $jobUrl,
            'source' => 'hiring_signal_'.$this->sourceName,
        ];
    }

    public function hasNextPage(): bool
    {
        return false;
    }

    public function sourceName(): string
    {
        return $this->sourceName;
    }

    /**
     * Parse job listings from Jina Reader markdown output.
     * Handles common job board markdown structures.
     */
    private function parseListingsFromMarkdown(string $markdown): array
    {
        $results = [];

        // Split by common listing separators
        // Most job boards via Jina render listings as:
        // [Job Title](url) or **Job Title**
        // followed by Company Name, Location, Date

        $lines = explode("\n", $markdown);
        $currentCompany = '';
        $currentTitle = '';
        $currentUrl = '';
        $currentDate = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            // Skip common noise
            if (str_starts_with($trimmed, '![Image') || str_starts_with($trimmed, 'http')
                || in_array($trimmed, ['FEATURED', 'New', 'Easy apply', 'Apply', 'Save', 'Share'])
                || preg_match('/^\d+,\d+\s+(Jobs|Found)/', $trimmed)) {
                continue;
            }

            // Job title as markdown link: [Title](url) or ## [Title](url)
            // Skip image links ![Image](url)
            if (! str_starts_with($trimmed, '!') && preg_match('/^#*\s*\[([^\]]+)\]\(([^)]+)\)/', $trimmed, $m)) {
                $title = trim($m[1]);
                $url = $m[2];

                // If we have a previous entry, save it
                if (! empty($currentCompany) && ! empty($currentTitle)) {
                    $results[] = [
                        'company_name' => $currentCompany,
                        'job_title' => $currentTitle,
                        'posting_date' => $this->parseRelativeDate($currentDate),
                        'job_url' => $currentUrl,
                    ];
                }

                $currentTitle = $title;
                $currentUrl = $url;
                $currentCompany = '';
                $currentDate = '';
                continue;
            }

            // If we have a title but no company yet, this line might be the company
            if (! empty($currentTitle) && empty($currentCompany) && strlen($trimmed) < 80) {
                $currentCompany = $trimmed;
                continue;
            }

            // Detect date patterns
            if (preg_match('/^(Today|Yesterday|\d+\s+(day|week|month)s?\s+ago)$/i', $trimmed, $dm)) {
                $currentDate = $dm[1];
                continue;
            }

            // Also try to extract date if it contains date keywords
            if (preg_match('/(Today|Yesterday|\d+\s+(day|week|month)s?\s+ago)/i', $trimmed, $dm)) {
                $currentDate = $dm[1];
                continue;
            }

            // Posted: Date format (e.g. "Posted: Jul 1, 2026")
            if (preg_match('/Posted:\s*(.+)/i', $trimmed, $dm)) {
                $currentDate = trim($dm[1]);
                continue;
            }
        }

        // Don't forget the last listing
        if (! empty($currentCompany) && ! empty($currentTitle)) {
            $results[] = [
                'company_name' => $currentCompany,
                'job_title' => $currentTitle,
                'posting_date' => $this->parseRelativeDate($currentDate),
                'job_url' => $currentUrl,
            ];
        }

        return $results;
    }

    private function matchesTargetTitle(string $titleLower): bool
    {
        foreach ($this->targetTitles as $target) {
            if (str_contains($titleLower, $target)) {
                return true;
            }
        }

        return false;
    }

    private function isInternshipOnly(string $titleLower, string $originalTitle): bool
    {
        $isInternship = str_contains($titleLower, 'intern');
        $isTrainee = str_contains($titleLower, 'graduate trainee')
            || str_contains($titleLower, 'management trainee')
            || str_contains($titleLower, 'trainee');

        return $isInternship && ! $isTrainee;
    }

    private function matchesExcludePattern(string $companyLower): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (str_contains($companyLower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
