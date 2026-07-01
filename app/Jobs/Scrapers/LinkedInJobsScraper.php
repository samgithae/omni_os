<?php

namespace App\Jobs\Scrapers;

use App\Contracts\JobSourceScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn Jobs Scraper
 *
 * Uses mcporter to call the linkedin-scraper-mcp MCP server (installed via
 * Agent-Reach) for Playwright-based LinkedIn job search with anti-detection.
 *
 * REQUIREMENT (one-time on server with Xvfb):
 *   cd /srv/omni_os && source .env && mcporter call linkedin.close_session
 *   # Then run login: Xvfb :99 & DISPLAY=:99 mcp-server-linkedin --login
 *   # Complete LinkedIn login in the virtual display
 *   # Session persists in ~/.mcp-server-linkedin/
 *
 * When LinkedIn session isn't set up, returns empty results (pipeline continues).
 */
class LinkedInJobsScraper implements JobSourceScraper, ShouldQueue
{
    use Queueable;

    private const TARGET_TITLES = [
        'sales rep', 'sales executive', 'business development',
        'customer service', 'customer care', 'customer success',
        'call centre agent', 'call center agent', 'contact centre',
        'graduate trainee', 'management trainee',
        'field officer', 'relationship officer',
        'branch officer', 'branch manager', 'operations officer',
        'loan officer', 'telesales', 'collections officer',
        'account manager', 'retail assistant', 'cashier',
        'front office officer',
    ];

    private const EXCLUDE_PATTERNS = [
        'recruitment agency', 'staffing firm', 'employment agency',
        'individual recruiter', 'government',
        'internship only', 'one person',
    ];

    private array $listings = [];

    private bool $configured = false;

    public function __construct()
    {
        $this->configured = env('LINKEDIN_ENABLED', false)
            && file_exists(base_path('config/mcporter.json'));
    }

    public function fetchListings(): array
    {
        $this->listings = [];

        if (! $this->configured) {
            Log::info('LinkedInJobsScraper: LINKEDIN_ENABLED not set or mcporter.json missing.');

            return [];
        }

        // Check MCP server is responding
        $status = $this->mcporter(['list', 'linkedin']);
        if ($status === null || str_contains($status, 'unavailable')) {
            Log::info('LinkedInJobsScraper: LinkedIn MCP server not available.');

            return [];
        }

        $keywords = ['sales', 'business development', 'customer service',
            'graduate trainee', 'field officer', 'relationship officer',
            'branch manager', 'operations', 'loan officer', 'telesales',
            'collections', 'account manager', 'retail', 'front office',
        ];

        foreach ($keywords as $keyword) {
            $results = $this->searchJobs($keyword);
            $this->listings = array_merge($this->listings, $results);

            if (count($this->listings) >= 100) {
                break;
            }

            sleep(2);
        }

        Log::info('LinkedInJobsScraper: Fetched '.count($this->listings).' listings');

        return $this->listings;
    }

    public function parseListing(array $rawListing): ?array
    {
        $title = $rawListing['job_title'] ?? '';
        $titleLower = mb_strtolower(trim($title));

        if (! $this->isTargetTitle($titleLower)) {
            return null;
        }

        $company = $rawListing['company_name'] ?? '';
        if ($this->isExcluded(mb_strtolower(trim($company)))) {
            return null;
        }

        return [
            'company_name' => $company,
            'website' => null,
            'job_title' => $title,
            'posting_date' => $rawListing['posting_date'] ?? null,
            'job_url' => $rawListing['job_url'] ?? '',
            'source' => 'linkedin',
        ];
    }

    public function hasNextPage(): bool
    {
        return false;
    }

    public function sourceName(): string
    {
        return 'linkedin';
    }

    private function searchJobs(string $keyword): array
    {
        $raw = $this->mcporter([
            'call', 'linkedin.search_jobs',
            "keywords={$keyword}",
            'location=Kenya',
            'max_pages=1',
            'date_posted=past month',
        ]);

        if ($raw === null) {
            return [];
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['jobs'])) {
            return [];
        }

        $results = [];
        foreach ($data['jobs'] as $job) {
            $company = $job['company'] ?? [];
            $companyName = is_array($company) ? ($company['name'] ?? '') : (string) $company;

            $details = $this->getJobDetails($job['job_id'] ?? '');

            $results[] = [
                'company_name' => $companyName,
                'job_title' => $job['title'] ?? '',
                'posting_date' => $details['posted_date'] ?? $job['posted_date'] ?? null,
                'job_url' => $job['url'] ?? 'https://www.linkedin.com/jobs/view/'.($job['job_id'] ?? '').'/',
            ];
        }

        return $results;
    }

    private function getJobDetails(string $jobId): array
    {
        if (empty($jobId)) {
            return [];
        }

        $raw = $this->mcporter(['call', 'linkedin.get_job_details', "job_id={$jobId}"]);
        if ($raw === null) {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function mcporter(array $args): ?string
    {
        $workdir = base_path();
        $argsStr = implode(' ', array_map('escapeshellarg', $args));
        $cmd = 'cd '.escapeshellarg($workdir)
            .' && mcporter '.$argsStr.' 2>&1';

        $exitCode = -1;
        $output = [];
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::warning('LinkedInJobsScraper: mcporter exit '.$exitCode);

            return null;
        }

        return implode("\n", $output);
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

    private function isExcluded(string $companyLower): bool
    {
        foreach (self::EXCLUDE_PATTERNS as $pattern) {
            if (str_contains($companyLower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
