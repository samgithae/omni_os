<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\BrandSequenceConfig;
use App\Models\Lead;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class GenerateEmailContent extends Command
{
    protected $signature = 'emails:generate-content
        {--limit=10 : Max leads to generate for per run}
        {--dry-run : Preview without making changes}';

    protected $description = 'Check for leads needing email sequence generation and log readiness. Actual LLM generation is done by the Hermes cron (generate-email-sequences skill, every 60 min). This command tracks the pipeline on the jobs dashboard.';

    public function handle(ActivityLogger $logger): int
    {
        $limit = (int) $this->option('limit');

        $this->info('Checking for leads needing email sequence generation...');

        $query = Lead::query()
            ->where('status', 'enriched')
            ->whereNotNull('email')
            ->with(['brand', 'emailMessages']);

        $leads = $query->get();

        $needsGeneration = 0;
        $totalMissingSteps = 0;
        $withConfig = 0;
        $noConfig = 0;
        $noMessages = 0;

        foreach ($leads as $lead) {
            $config = BrandSequenceConfig::resolveFor($lead->brand_id, $lead->segment);

            if (! $config) {
                $noConfig++;
                continue;
            }

            $withConfig++;

            $requiredSteps = range(1, $config->sequence_steps);
            $existingSteps = $lead->emailMessages
                ->filter(fn ($m) => ! empty(trim($m->subject ?? '')) && ! empty(trim($m->body ?? '')))
                ->pluck('sequence_step')
                ->toArray();

            $missingSteps = array_values(array_diff($requiredSteps, $existingSteps));

            if (! empty($missingSteps)) {
                $needsGeneration++;
                $totalMissingSteps += count($missingSteps);
            }

            if ($lead->emailMessages->count() === 0) {
                $noMessages++;
            }
        }

        $this->line('');
        $this->info('=== Email Generation Pipeline Status ===');
        $this->line("Enriched leads with email: {$leads->count()}");
        $this->line("Has sequence config:       {$withConfig}");
        $this->line("No sequence config:        {$noConfig}");
        $this->line("Has no email messages:     {$noMessages}");
        $this->line("Needs generation:          {$needsGeneration}");
        $this->line("Total steps missing:       {$totalMissingSteps}");
        $this->line('');

        // Log to Activity Feed so it appears there too
        if (! $this->option('dry-run')) {
            $logger->log([
                'brand_id' => null,
                'source' => 'hermes:generate_email_sequences',
                'event_type' => 'system',
                'title' => 'Email generation pipeline check',
                'body' => "Checked {$leads->count()} enriched leads. {$needsGeneration} need generation ({$totalMissingSteps} steps missing). {$noConfig} have no sequence config. Hermes cron (generate-email-sequences) will process the next batch within 60 minutes.",
                'severity' => $needsGeneration > 0 ? 'info' : 'success',
            ]);
        }

        if ($needsGeneration > 0) {
            $this->warn("{$needsGeneration} leads need generation. The Hermes cron (generate-email-sequences, every 60 min) will process them.");
            if (! $this->option('dry-run')) {
                $this->line('Activity Feed event logged.');
            }
        } else {
            $this->info('All leads have complete sequences. No generation needed.');
        }

        return 0;
    }
}
