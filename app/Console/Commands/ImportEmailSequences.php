<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use Illuminate\Console\Command;
use League\Csv\Reader;

class ImportEmailSequences extends Command
{
    protected $signature = 'emails:import-sequences
                            {--dry-run : Show what would be imported without writing}
                            {--file= : Specific CSV file path (default: auto-detect Rabbits + Deer)}';

    protected $description = 'Import email sequences (email_1 through email_5) from Google Sheets CSV into email_messages table';

    private int $ujuziplusBrandId = 0;

    public function handle(): int
    {
        $this->info('=== Email Sequence Import ===');

        $brand = Brand::where('slug', 'ujuziplus')->first();
        if (!$brand) {
            $this->error('UjuziPlus brand not found. Run the seeder first.');
            return self::FAILURE;
        }
        $this->ujuziplusBrandId = $brand->id;

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
            'total_emails' => 0,
            'imported' => 0,
            'skipped_no_lead' => 0,
            'skipped_not_real_email' => 0,
            'skipped_duplicate' => 0,
        ];

        foreach ($files as $csvFile) {
            if (!file_exists($csvFile)) {
                $this->warn("File not found: {$csvFile}");
                continue;
            }

            $segment = str_contains($csvFile, 'rabbit') ? 'rabbit' : 'deer';
            $this->info("\n--- Importing {$segment} sequences from " . basename($csvFile) . " ---");

            $csv = Reader::createFromPath($csvFile, 'r');
            $csv->setHeaderOffset(0);
            $records = $csv->getRecords();
            $headers = $csv->getHeader();

            $fileStats = ['emails_found' => 0, 'imported' => 0, 'skipped' => 0];

            foreach ($records as $rowIdx => $record) {
                // Find the lead by company name
                $companyName = $this->getValue($record, $headers, 'org_name');
                if (!$companyName) {
                    continue;
                }

                $lead = Lead::where('brand_id', $this->ujuziplusBrandId)
                    ->where('company_name', $companyName)
                    ->first();

                if (!$lead) {
                    // Try a fuzzy match — sometimes names have slight differences
                    $lead = Lead::where('brand_id', $this->ujuziplusBrandId)
                        ->where('company_name', 'LIKE', '%' . substr($companyName, 0, 20) . '%')
                        ->first();
                }

                if (!$lead) {
                    $stats['skipped_no_lead']++;
                    continue;
                }

                // Determine max email columns (rabbits: 5, deer: 3)
                $maxEmails = $segment === 'rabbit' ? 5 : 3;

                for ($step = 1; $step <= $maxEmails; $step++) {
                    $emailKey = "email_{$step}";
                    $rawEmail = $this->getValue($record, $headers, $emailKey);
                    $rawEmail = trim($rawEmail);

                    if (!$rawEmail) {
                        continue;
                    }

                    $stats['total_emails']++;
                    $fileStats['emails_found']++;

                    // Skip non-email entries (skip notes, enrichment markers, etc.)
                    if (!$this->isRealEmailContent($rawEmail)) {
                        $stats['skipped_not_real_email']++;
                        $fileStats['skipped']++;
                        continue;
                    }

                    // Parse subject and body
                    [$subject, $body] = $this->parseEmailContent($rawEmail);

                    if (!$subject && !$body) {
                        $stats['skipped_not_real_email']++;
                        $fileStats['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $stats['imported']++;
                        $fileStats['imported']++;
                        continue;
                    }

                    // Check for existing email message (idempotency)
                    $existing = EmailMessage::where('lead_id', $lead->id)
                        ->where('sequence_step', $step)
                        ->first();

                    if ($existing) {
                        $stats['skipped_duplicate']++;
                        $fileStats['skipped']++;
                        continue;
                    }

                    // Create the email message
                    EmailMessage::create([
                        'brand_id' => $this->ujuziplusBrandId,
                        'lead_id' => $lead->id,
                        'sequence_step' => $step,
                        'subject' => $subject,
                        'body' => $body,
                        'status' => 'draft',
                        'approval_status' => 'pending',
                    ]);

                    $stats['imported']++;
                    $fileStats['imported']++;
                }
            }

            $this->info("  Emails found: {$fileStats['emails_found']}");
            $this->info("  Imported: {$fileStats['imported']}");
            $this->info("  Skipped: {$fileStats['skipped']}");
        }

        $this->info("\n=== Summary ===");
        $this->info("Total emails found: {$stats['total_emails']}");
        $this->info("Imported: {$stats['imported']}");
        $this->info("Skipped (no matching lead): {$stats['skipped_no_lead']}");
        $this->info("Skipped (not real email content): {$stats['skipped_not_real_email']}");
        $this->info("Skipped (duplicate): {$stats['skipped_duplicate']}");

        return self::SUCCESS;
    }

    private function getValue(array $record, array $headers, string $key): string
    {
        foreach ($headers as $h) {
            if (strtolower($h) === strtolower($key)) {
                return trim($record[$h] ?? '');
            }
        }
        return '';
    }

    private function isRealEmailContent(string $content): bool
    {
        // Skip enrichment markers and notes
        $skipPatterns = [
            'skipped: no website',
            'no emails found',
            'enriched:',
            'not available',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_starts_with(strtolower($content), strtolower($pattern))) {
                return false;
            }
        }

        // Real email content should be reasonably long (at least 30 chars)
        // and either has "Subject:" or is a substantial body
        if (strlen($content) < 30) {
            return false;
        }

        return true;
    }

    private function parseEmailContent(string $raw): array
    {
        $subject = null;
        $body = $raw;

        // Try to extract subject from "Subject: ..." at the start
        if (preg_match('/^Subject:\s*(.+?)\n(.*)/s', $raw, $matches)) {
            $subject = trim($matches[1]);
            $body = trim($matches[2]);
        }

        // If subject is too long (> 255 chars), it's probably not a real subject
        if ($subject && strlen($subject) > 255) {
            $subject = substr($subject, 0, 252) . '...';
        }

        return [$subject, $body];
    }
}