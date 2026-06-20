<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Suppression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class ImportUjuziPlusLeads extends Command
{
    protected $signature = 'leads:import-ujuziplus
                            {--dry-run : Show what would be imported without writing}
                            {--file= : Specific CSV file path (default: auto-detect Rabbits + Deer)}';

    protected $description = 'Import UjuziPlus leads from Google Sheets CSV exports into Postgres';

    private int $ujuziplusBrandId = 0;

    public function handle(): int
    {
        $this->info('=== UjuziPlus Lead Import ===');

        // Find UjuziPlus brand
        $brand = Brand::where('slug', 'ujuziplus')->first();
        if (!$brand) {
            $this->error('UjuziPlus brand not found. Run the seeder first.');
            return self::FAILURE;
        }
        $this->ujuziplusBrandId = $brand->id;
        $this->info("Brand: {$brand->name} (ID: {$brand->id})");

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written.');
        }

        $file = $this->option('file');
        if ($file) {
            $files = [$file];
        } else {
            $storagePath = storage_path('app/private');
            $files = [
                $storagePath . '/ujuziplus_rabbits.csv',
                $storagePath . '/ujuziplus_deer.csv',
            ];
        }

        $stats = [
            'total_rows' => 0,
            'imported' => 0,
            'skipped_no_name' => 0,
            'skipped_duplicate' => 0,
            'suppressed' => 0,
            'emails_found' => 0,
        ];

        foreach ($files as $csvFile) {
            if (!file_exists($csvFile)) {
                $this->warn("File not found: {$csvFile}");
                continue;
            }

            $segment = str_contains($csvFile, 'rabbit') ? 'rabbit' : 'deer';
            $this->info("\n--- Importing {$segment} from " . basename($csvFile) . " ---");

            $csv = Reader::createFromPath($csvFile, 'r');
            $csv->setHeaderOffset(0);
            $records = $csv->getRecords();
            $headers = $csv->getHeader();

            $fileStats = ['rows' => 0, 'imported' => 0, 'skipped' => 0, 'emails' => 0];

            foreach ($records as $record) {
                $fileStats['rows']++;
                $stats['total_rows']++;

                $lead = $this->mapRecord($record, $headers, $segment);

                if (!$lead['company_name']) {
                    $fileStats['skipped']++;
                    $stats['skipped_no_name']++;
                    continue;
                }

                if ($lead['email']) {
                    $stats['emails_found']++;
                    $fileStats['emails']++;
                }

                if ($dryRun) {
                    $fileStats['imported']++;
                    $stats['imported']++;
                    continue;
                }

                // Check for existing lead (dedup invariant — only on non-null emails)
                if ($lead['email']) {
                    $existing = Lead::where('brand_id', $this->ujuziplusBrandId)
                        ->where('email', $lead['email'])
                        ->first();

                    if ($existing) {
                        $stats['skipped_duplicate']++;
                        $fileStats['skipped']++;
                        continue;
                    }
                }

                // Create the lead
                $created = Lead::create([
                    'brand_id' => $this->ujuziplusBrandId,
                    'company_name' => $lead['company_name'],
                    'contact_name' => $lead['contact_name'],
                    'email' => $lead['email'],
                    'phone' => $lead['phone'],
                    'website' => $lead['website'],
                    'segment' => $lead['segment'],
                    'category' => $lead['category'],
                    'country' => 'Kenya',
                    'city' => $lead['city'],
                    'status' => $lead['email'] ? 'enriched' : 'new',
                    'email_verified' => (bool) $lead['email'],
                    'score' => $lead['score'],
                    'source' => 'google_sheets',
                    'raw_data' => $lead['raw_data'],
                ]);

                // Log import event
                LeadEvent::create([
                    'lead_id' => $created->id,
                    'brand_id' => $this->ujuziplusBrandId,
                    'event_type' => 'imported',
                    'payload' => ['source' => 'google_sheets', 'segment' => $segment, 'original_row' => $fileStats['rows']],
                    'source' => 'import_command',
                ]);

                // Handle suppression (only if email exists)
                if ($lead['is_suppressed'] && $lead['email']) {
                    Suppression::create([
                        'brand_id' => $this->ujuziplusBrandId,
                        'email' => $lead['email'],
                        'reason' => 'manual',
                        'notes' => $lead['suppress_reason'] ?? 'Imported from sheet',
                    ]);
                    $stats['suppressed']++;
                }

                $fileStats['imported']++;
                $stats['imported']++;
            }

            $this->info("  Rows: {$fileStats['rows']}");
            $this->info("  Imported: {$fileStats['imported']}");
            $this->info("  Skipped: {$fileStats['skipped']}");
            $this->info("  Emails: {$fileStats['emails']}");
        }

        $this->info("\n=== Summary ===");
        $this->info("Total rows processed: {$stats['total_rows']}");
        $this->info("Imported: {$stats['imported']}");
        $this->info("Skipped (no name): {$stats['skipped_no_name']}");
        $this->info("Skipped (duplicate): {$stats['skipped_duplicate']}");
        $this->info("Emails found: {$stats['emails_found']}");
        $this->info("Suppressed: {$stats['suppressed']}");

        return self::SUCCESS;
    }

    private function mapRecord(array $record, array $headers, string $segment): array
    {
        // Helper to get value by header name (case-insensitive)
        $get = function (string $key) use ($record, $headers): string {
            foreach ($headers as $h) {
                if (strtolower($h) === strtolower($key)) {
                    return trim($record[$h] ?? '');
                }
            }
            return '';
        };

        $companyName = $get('org_name') ?: $get('company_name');
        $email = $get('email') ?: $get('direct_email') ?: $get('company_email');
        $phone = $get('phone') ?: $get('phone_wa');
        $website = $get('website');
        $category = $get('category');
        $contactName = $get('first_name') ?: $get('contact_name');

        // Deer sheet has role column
        $role = $get('role');

        // Truncate contact_name if it's clearly a misaligned long text (Deer sheet issue)
        if (strlen($contactName) > 100) {
            $contactName = null;
        }

        // Truncate company_name if too long
        if (strlen($companyName) > 255) {
            $companyName = substr($companyName, 0, 252) . '...';
        }

        // Truncate email if too long (shouldn't be, but Deer has misaligned data)
        if (strlen($email) > 255) {
            $email = null;
        }

        // Truncate phone if too long
        if (strlen($phone) > 50) {
            $phone = substr($phone, 0, 50);
        }

        // Clean website (sometimes email is in website field)
        if ($website && !str_starts_with($website, 'http') && !str_contains($website, '.')) {
            $website = null;
        }
        // Sometimes email ends up in website field
        if ($website && str_contains($website, '@') && !str_starts_with($website, 'http')) {
            if (!$email) {
                $email = $website;
            }
            $website = null;
        }

        // Check suppression
        $suppressValue = strtolower($get('suppress'));
        $isSuppressed = in_array($suppressValue, ['1', 'yes', 'true'], true) ||
            str_starts_with($suppressValue, 'yes|') ||
            str_starts_with($suppressValue, 'yes ');
        $suppressReason = $get('suppress_reason');

        // Build raw_data from all columns
        $rawData = [];
        foreach ($headers as $h) {
            if (!empty($record[$h] ?? '')) {
                $rawData[$h] = $record[$h];
            }
        }

        // Calculate score
        $score = 0;
        if ($email) $score += 30;
        if ($phone) $score += 15;
        if ($website) $score += 15;
        if (!empty($rawData['business_insight'])) $score += 20;
        if (!empty($rawData['concrete_fact'])) $score += 10;
        if ($segment === 'deer') $score += 10;

        return [
            'company_name' => $companyName,
            'contact_name' => $contactName,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'website' => $website ?: null,
            'segment' => $segment,
            'category' => $category ?: null,
            'city' => $this->extractCity($companyName, $rawData),
            'score' => $score,
            'raw_data' => $rawData,
            'is_suppressed' => $isSuppressed,
            'suppress_reason' => $suppressReason,
        ];
    }

    private function extractCity(string $companyName, array $rawData): ?string
    {
        // Check if city is in the raw data
        foreach (['city', 'location', 'address'] as $key) {
            foreach ($rawData as $k => $v) {
                if (strtolower($k) === $key && !empty($v)) {
                    return $v;
                }
            }
        }

        // Try to infer from company name or other fields
        $text = strtolower($companyName . ' ' . implode(' ', $rawData));
        $cities = ['nairobi', 'mombasa', 'kisumu', 'nakuru', 'eldoret', 'thika', 'nyeri', 'machakos', 'kakamega', 'naivasha', 'meru', 'kisii', 'malindi', 'kitale'];

        foreach ($cities as $city) {
            if (str_contains($text, $city)) {
                return ucfirst($city);
            }
        }

        return null; // Unknown city
    }
}