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
                    'X-Return-Format' => 'markdown',
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

        // Find the start of actual listings (after "Jobs Found")
        $start = strpos($markdown, 'Jobs Found');
        if ($start === false) {
            return $listings;
        }
        $body = substr($markdown, $start);

        // Split by "Easy apply" to get individual listing blocks
        $blocks = explode('Easy apply', $body);

        foreach ($blocks as $block) {
            $lines = explode("\n", trim($block));
            $companyName = '';
            $jobTitle = '';
            $jobUrl = '';
            $locationType = '';
            $category = '';
            $postingDate = date('Y-m-d');

            $phase = 'before'; // before → title → company → location → category → date → done

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed)) {
                    continue;
                }

                // Skip known noise lines
                if (in_array($trimmed, ['FEATURED', 'New', 'Activate Notifications', 'Deactivate Notifications',
                    'Stay productive - get the latest updates on Jobs & News',
                    'Stop receiving the latest updates on Jobs & News',
                    'This action will pause all job alerts. Are you sure?',
                    'Cancel', 'Proceed', 'Activate',
                    'Job Hunting? Use AI to Boost Your Career',
                    'No Thanks',
                ]) || str_starts_with($trimmed, '![Image') || str_starts_with($trimmed, '1,')
                    || str_starts_with($trimmed, 'Filters') || str_starts_with($trimmed, 'Any ')
                    || str_starts_with($trimmed, 'http') || str_starts_with($trimmed, 'No Thanks')) {
                    continue;
                }

                // Job title: [Title](url "Title") pattern
                if ($phase === 'before' && preg_match('/^\[([^\]]+)\]\(([^)]+)\)/', $trimmed, $m)) {
                    $jobTitle = trim($m[1]);
                    $jobUrl = $m[2];
                    $phase = 'company';
                    continue;
                }

                // Company name: line with link or plain text
                if ($phase === 'company' && preg_match('/^\[([^\]]+)\]\(([^)]+)\)/', $trimmed, $m)) {
                    // This is actually a company link
                    $companyName = trim($m[1]);
                    $phase = 'location';
                    continue;
                }
                if ($phase === 'company' && empty($companyName) && ! str_contains($trimmed, '|')) {
                    $companyName = $trimmed;
                    $phase = 'location';
                    continue;
                }

                // Location/Type: contains |
                if ($phase === 'location' && str_contains($trimmed, '|')) {
                    $locationType = $trimmed;
                    $phase = 'date';
                    continue;
                }

                // Date: Today, Yesterday, X days ago
                if ($phase === 'date' && preg_match('/^(Today|Yesterday|\d+\s+days?\s+ago)$/i', $trimmed, $dm)) {
                    $dateStr = strtolower($dm[1]);
                    if ($dateStr === 'today') {
                        $postingDate = date('Y-m-d');
                    } elseif ($dateStr === 'yesterday') {
                        $postingDate = date('Y-m-d', strtotime('-1 day'));
                    } elseif (preg_match('/(\d+)\s+day/', $dateStr, $dd)) {
                        $postingDate = date('Y-m-d', strtotime('-'.(int)$dd[1].' days'));
                    }
                    $phase = 'done';
                    continue;
                }
            }

            if (! empty($companyName) && ! empty($jobTitle)) {
                $listings[] = [
                    'company_name' => $companyName,
                    'job_title' => $jobTitle,
                    'posting_date' => $postingDate,
                    'job_url' => $jobUrl,
                    'company_description' => $category.' '.$locationType,
                ];
            }
        }

        return $listings;
    }

    /**
     * Extract job title from description text.
     */
    private function extractJobTitle(string $description, string $category): string
    {
        // Common patterns: "We are looking for a [TITLE]", "seeking a [TITLE]", "The [TITLE] will"
        $patterns = [
            '/\b(looking for|seeking|hiring|recruit(?:ing|))\s+(?:a|an|experienced|skilled|proactive|detail-oriented|qualified|professional|results-driven|mature|organized|responsible)?\s*([A-Z][A-Za-z\s\/&-]+?)(?:\s+to\s|\s+with\s|\s+for\s|\.|,)/',
            '/\b(The|Our)\s+([A-Z][A-Za-z\s\/&-]+?)\s+(will be responsible|will|is responsible|reports|manages)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $m)) {
                $title = trim($m[count($m) === 3 ? 2 : 2]);
                // Clean up
                $title = preg_replace('/\s+/', ' ', $title);
                if (strlen($title) > 5 && strlen($title) < 100) {
                    return $title;
                }
            }
        }

        // Fallback: use the category as a hint
        // Return a meaningful prefix of the description
        $words = explode(' ', trim($description));
        $titleWords = [];
        foreach ($words as $w) {
            if (ctype_upper($w[0] ?? '') && strlen($w) > 2) {
                $titleWords[] = $w;
                if (count($titleWords) >= 5) break;
            } elseif (! empty($titleWords)) {
                break;
            }
        }

        return ! empty($titleWords) ? implode(' ', $titleWords) : $category;
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
