<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\BrandSequenceConfig;
use App\Models\Lead;
use Illuminate\Console\Command;

class IdentifyIncompleteSequences extends Command
{
    protected $signature = 'emails:identify-incomplete-sequences
        {--brand= : Filter by brand slug}
        {--segment= : Filter by segment (rabbit, deer)}
        {--fix : Reset partial sequences for re-generation (sets to needs_content, clears approved partials)}
        {--dry-run : Preview without making changes}';

    protected $description = 'Find enriched leads with incomplete email sequences (missing one or more steps).';

    public function handle(): int
    {
        $query = Lead::query()
            ->where('status', 'enriched')
            ->whereNotNull('email')
            ->with(['brand', 'emailMessages']);

        if ($brandSlug = $this->option('brand')) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if (! $brand) {
                $this->error("Brand '{$brandSlug}' not found.");

                return 1;
            }
            $query->where('brand_id', $brand->id);
        }

        if ($segment = $this->option('segment')) {
            $query->where('segment', $segment);
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            $this->warn('No enriched leads with email addresses found.');

            return 0;
        }

        $incompleteLeads = [];
        $totalMissingSteps = 0;
        $totalExistingSteps = 0;
        $totalRequiredSteps = 0;

        foreach ($leads as $lead) {
            $config = BrandSequenceConfig::resolveFor($lead->brand_id, $lead->segment, $lead->subcategory);

            if (! $config) {
                $this->line("  [SKIP] Lead {$lead->id} ({$lead->company_name}) — no sequence config for brand {$lead->brand->name}/{$lead->segment}");

                continue;
            }

            $requiredSteps = range(1, $config->sequence_steps);
            $totalRequiredSteps += count($requiredSteps);

            $existingSteps = $lead->emailMessages
                ->filter(fn ($m) => ! empty(trim($m->subject ?? '')) && ! empty(trim($m->body ?? '')))
                ->pluck('sequence_step')
                ->toArray();

            $missingSteps = array_values(array_diff($requiredSteps, $existingSteps));
            $totalExistingSteps += count($existingSteps);
            $totalMissingSteps += count($missingSteps);

            if (empty($missingSteps)) {
                continue; // Complete — skip
            }

            $incompleteLeads[] = [
                'lead' => $lead,
                'missing_steps' => $missingSteps,
                'existing_steps' => $existingSteps,
                'config' => $config,
            ];
        }

        // Print report
        $this->line('');
        $this->info('=== Incomplete Sequence Report ===');
        $this->line("Total leads scanned: {$leads->count()}");
        $this->line('Incomplete leads: '.count($incompleteLeads));
        $this->line("Total steps missing: {$totalMissingSteps}");
        $this->line('');

        if (empty($incompleteLeads)) {
            $this->info('All leads have complete email sequences. Nothing to fix.');

            return 0;
        }

        $this->table(
            ['Lead ID', 'Company', 'Brand', 'Segment', 'Required Steps', 'Existing', 'Missing', 'Has Approved'],
            collect($incompleteLeads)->map(function ($item) {
                $lead = $item['lead'];
                $hasApproved = $lead->emailMessages->contains(fn ($m) => $m->approval_status === 'approved');

                return [
                    $lead->id,
                    $lead->company_name,
                    $lead->brand->name,
                    $lead->segment.'/'.$lead->subcategory,
                    $item['config']->sequence_steps,
                    '['.implode(',', $item['existing_steps']).']',
                    '['.implode(',', $item['missing_steps']).']',
                    $hasApproved ? 'YES ⚠️' : 'No',
                ];
            })->toArray()
        );

        $this->line('');
        $this->info('Summary: Found '.count($incompleteLeads)." leads with incomplete sequences. {$totalMissingSteps} steps missing across ".count($incompleteLeads).' leads.');

        // --fix action
        if ($this->option('fix')) {
            $this->line('');
            $this->warn('Running --fix: resetting partial sequences for re-generation...');

            $fixed = 0;
            $unapproved = 0;

            foreach ($incompleteLeads as $item) {
                $lead = $item['lead'];

                foreach ($lead->emailMessages as $email) {
                    // If a partial email has approval_status=approved, un-approve it
                    // so the batch submission endpoint can override it
                    if ($email->approval_status === 'approved') {
                        if (! $this->option('dry-run')) {
                            $email->update([
                                'approval_status' => 'needs_content',
                                'approved_at' => null,
                                'status' => 'draft',
                            ]);
                        }
                        $unapproved++;
                    }

                    // If a partial email has no content, mark as needs_content
                    if (empty(trim($email->subject ?? '')) || empty(trim($email->body ?? ''))) {
                        if (! $this->option('dry-run') && $email->approval_status !== 'needs_content') {
                            $email->update(['approval_status' => 'needs_content']);
                        }
                    }
                }

                $fixed++;
            }

            $this->info("  Fixed: {$fixed} leads");
            $this->info("  Un-approved: {$unapproved} partial-approved emails");

            if ($this->option('dry-run')) {
                $this->warn('  Dry-run mode — no changes were made. Run without --dry-run to apply.');
            }
        } elseif ($this->option('dry-run')) {
            $this->line('');
            $this->line('Dry-run mode — no changes. Use --fix to reset partial sequences.');
        } else {
            $this->line('');
            $this->warn('Use --fix to reset partial email_messages to needs_content for re-generation.');
            $this->warn('  php artisan emails:identify-incomplete-sequences --fix');
            $this->warn('  php artisan emails:identify-incomplete-sequences --fix --dry-run');
        }

        return 0;
    }
}
