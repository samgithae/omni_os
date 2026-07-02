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

    /** Known noise lines to skip */
    private const NOISE_LINES = [
        'FEATURED', 'New', 'Easy apply', 'Apply', 'Save', 'Share',
        'Only on Fuzu',
        'Activate Notifications', 'Deactivate Notifications',
        'Stay productive', 'Stop receiving', 'This action will',
        'Cancel', 'Proceed', 'Activate', 'No Thanks', 'Try It Now',
        'Search results',
    ];

    /**
     * @param string $sourceName Internal source identifier
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
     * Handles multiple job board markdown structures:
     *   - BrighterMonday: FEATURED → [Title] → Company → Location → Date
     *   - Fuzu: Company → ## [Title] → Posted: Date
     *   - Others: various combinations
     */
    private function parseListingsFromMarkdown(string $markdown): array
    {
        $results = [];
        $lines = explode("\n", $markdown);

        // Track the most recent non-noise text line as a potential company name
        $lastCandidate = '';
        $currentTitle = '';
        $currentUrl = '';
        $currentDate = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            // Skip image lines and known noise
            if (str_starts_with($trimmed, '![') || $this->isNoise($trimmed)) {
                continue;
            }

            // Skip lines that are clearly descriptions (too long, contain common description words)
            if (strlen($trimmed) > 100 || preg_match('/\b(looking for|seeking|will be responsible|the successful candidate|we are\b)/i', $trimmed)) {
                continue;
            }

            // Skip navigation/footer links
            if (preg_match('/^## (Top cities|Finance Manager|Job details)/', $trimmed)) {
                continue;
            }
            if (preg_match('/^© /', $trimmed)) {
                continue;
            }
            if (preg_match('/^\d+ days? left to apply/', $trimmed)) {
                continue;
            }
            if (preg_match('/^(Join Fuzu|About the job|Company|Contract|Apply by)/', $trimmed)) {
                continue;
            }

            // Detect date patterns
            $date = $this->extractDate($trimmed);
            if ($date !== null) {
                $currentDate = $date;
                continue;
            }

            // Detect job title as markdown link: [Title](url) or ## [Title](url)
            if (preg_match('/^#*\s*\[([^\]]+)\]\(([^)]+)\)/i', $trimmed, $m)) {
                $title = trim($m[1]);
                $url = $m[2];

                // If we have a previous listing pending, save it
                if (! empty($currentTitle) && ! empty($lastCandidate)) {
                    $results[] = $this->makeListing($lastCandidate, $currentTitle, $currentUrl, $currentDate);
                }

                // Start new listing: the lastCandidate before this title is the company
                $currentTitle = $title;
                $currentUrl = $url;
                $currentDate = '';
                // Don't reset lastCandidate — it stays as the company for this title
                continue;
            }

            // This line is a candidate — company name or other metadata
            // If we have an active title, the first candidate is the company name
            if (! empty($currentTitle) && empty($lastCandidate)) {
                $lastCandidate = $trimmed;
                continue;
            }

            // If no active title and this looks like a company name, save as candidate
            if (empty($currentTitle) && strlen($trimmed) < 80 && ! str_contains($trimmed, '|')) {
                $lastCandidate = $trimmed;
            }
        }

        // Don't forget the last listing
        if (! empty($currentTitle) && ! empty($lastCandidate)) {
            $results[] = $this->makeListing($lastCandidate, $currentTitle, $currentUrl, $currentDate);
        }

        return $results;
    }

    /**
     * Build a listing array.
     */
    private function makeListing(string $company, string $title, string $url, string $date): array
    {
        return [
            'company_name' => $company,
            'job_title' => $title,
            'posting_date' => $this->parseRelativeDate($date),
            'job_url' => $url,
        ];
    }

    /**
     * Check if a line is noise.
     */
    private function isNoise(string $text): bool
    {
        foreach (self::NOISE_LINES as $n) {
            if (str_contains($text, $n)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract date from a line. Returns date string or null.
     */
    private function extractDate(string $text): ?string
    {
        // "Today", "Yesterday", "X days ago", "X weeks ago", "X months ago"
        if (preg_match('/^(Today|Yesterday|\d+\s+(day|week|month)s?\s+ago)$/i', $text, $m)) {
            return $m[1];
        }

        // "Posted: Date" format
        if (preg_match('/Posted:\s*(.+)/i', $text, $m)) {
            return trim($m[1]);
        }

        return null;
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
