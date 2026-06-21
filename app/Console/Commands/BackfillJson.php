<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Models\Brand;
use App\Models\Lead;
use App\Models\Suppression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackfillJson extends Command
{
    protected $signature = 'leads:backfill-json
                            {dir : Directory containing lead JSON files}
                            {--dry-run : Preview only, do not insert}
                            {--brand=ujuziplus : Brand slug to assign leads to}';

    protected $description = 'Backfill leads from old agent JSON files into Postgres';

    public function handle(): int
    {
        $dir = $this->argument('dir');
        $dryRun = $this->option('dry-run');
        $brandSlug = $this->option('brand');

        if (! is_dir($dir)) {
            $this->error("Directory not found: {$dir}");

            return 1;
        }

        $brand = Brand::where('slug', $brandSlug)->first();
        if (! $brand) {
            $this->error("Brand not found: {$brandSlug}");

            return 1;
        }

        // Find JSON files
        $files = File::glob(rtrim($dir, '/').'/*.json');

        // Also check icp_lead_generator subdirectory
        $icpFiles = File::glob(rtrim($dir, '/').'/icp_lead_generator/*.json');
        $files = array_merge($files, $icpFiles);

        // Also check checkpoints
        $checkpointFiles = File::glob(rtrim($dir, '/').'/checkpoints/*.json');
        $files = array_merge($files, $checkpointFiles);

        // Filter out non-lead files
        $skipPatterns = [
            '/config\.json$/i', '/_report\.json$/i', '/audit_/i',
            '/checkpoint/i', '/\.pid$/i', '/config\.py$/i',
            '/requirements\.txt$/i',
        ];

        $leadFiles = [];
        foreach ($files as $file) {
            $baseName = basename($file);
            $skip = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $baseName)) {
                    $skip = true;
                    break;
                }
            }
            if (! $skip) {
                $leadFiles[] = $file;
            }
        }

        if (empty($leadFiles)) {
            $this->warn('No lead JSON files found in '.$dir);

            return 0;
        }

        $this->info('Found '.count($leadFiles).' potential lead files to scan.');

        $totalParsed = 0;
        $totalCreated = 0;
        $totalDuplicates = 0;
        $totalSuppressed = 0;
        $totalSkipped = 0;

        foreach ($leadFiles as $file) {
            $this->line("Scanning: {$file}");

            try {
                $content = file_get_contents($file);
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $this->warn("  Cannot parse JSON: {$e->getMessage()}");
                $totalSkipped++;
                continue;
            }

            // Handle different JSON structures
            $leads = $this->normalizeLeadData($data);

            foreach ($leads as $leadData) {
                $totalParsed++;

                $companyName = $leadData['company_name'] ?? null;
                $email = $leadData['email'] ?? null;

                if (! $companyName) {
                    continue;
                }

                // Check suppression
                if ($email) {
                    $isSuppressed = Suppression::query()
                        ->where('brand_id', $brand->id)
                        ->where('email', $email)
                        ->exists();

                    if ($isSuppressed) {
                        if (! $dryRun) {
                            $this->line("  ⛔ {$companyName} — suppressed");
                        }
                        $totalSuppressed++;
                        continue;
                    }
                }

                // Check duplicate
                $dedupQuery = Lead::query()->where('brand_id', $brand->id);
                if ($email) {
                    $dedupQuery->where('email', $email);
                } else {
                    $dedupQuery->where('company_name', $companyName);
                }

                if ($dedupQuery->exists()) {
                    if (! $dryRun) {
                        $this->line("  🔁 {$companyName} — duplicate");
                    }
                    $totalDuplicates++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  ✅ {$companyName} — would import".($email ? " ({$email})" : ''));
                    $totalCreated++; // count in dry-run too
                    continue;
                }

                try {
                    Lead::create([
                        'brand_id' => $brand->id,
                        'company_name' => $companyName,
                        'email' => $email,
                        'phone' => $leadData['phone'] ?? null,
                        'website' => $leadData['website'] ?? null,
                        'segment' => $leadData['segment'] ?? 'rabbit',
                        'category' => $leadData['category'] ?? null,
                        'country' => $leadData['country'] ?? 'Kenya',
                        'city' => $leadData['city'] ?? null,
                        'address' => $leadData['address'] ?? null,
                        'source' => $leadData['source'] ?? 'json_backfill',
                        'status' => $email ? LeadStatus::New->value : LeadStatus::New->value,
                        'raw_data' => [
                            'backfilled_from' => $file,
                            'backfilled_at' => now()->toIso8601String(),
                            'original_data' => $leadData,
                        ],
                    ]);

                    $totalCreated++;
                } catch (\Throwable $e) {
                    $this->error("  ❌ {$companyName}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Files scanned', count($leadFiles)],
                ['Leads parsed', $totalParsed],
                ['Created', $totalCreated],
                ['Duplicates', $totalDuplicates],
                ['Suppressed', $totalSuppressed],
                ['Skipped (parse errors)', $totalSkipped],
            ]
        );

        if ($dryRun) {
            $this->info('DRY RUN — no data was inserted. Run without --dry-run to import.');
        }

        return 0;
    }

    /**
     * Normalize various JSON structures into a uniform array of leads.
     */
    private function normalizeLeadData(array $data): array
    {
        // Case 1: Array of leads directly
        if (isset($data[0]) && is_array($data[0]) && isset($data[0]['company_name'])) {
            return $data;
        }

        // Case 2: { "leads": [...] } or { "data": [...] }
        if (isset($data['leads']) && is_array($data['leads'])) {
            return $data['leads'];
        }
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        // Case 3: { "results": [...] } or { "companies": [...] }
        if (isset($data['results']) && is_array($data['results'])) {
            return $data['results'];
        }
        if (isset($data['companies']) && is_array($data['companies'])) {
            return $data['companies'];
        }

        // Case 4: Single lead object
        if (isset($data['company_name'])) {
            return [$data];
        }

        // Case 5: Object with numeric keys
        $values = array_values($data);
        if (isset($values[0]) && is_array($values[0]) && (($values[0]['company_name'] ?? null) !== null || ($values[0]['name'] ?? null) !== null)) {
            return $values;
        }

        return [];
    }
}
