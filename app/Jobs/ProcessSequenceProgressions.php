<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\SequenceSchedule;
use App\Models\Suppression;
use App\Services\ActivityLogger;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessSequenceProgressions implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        if (now()->isWeekend()) {
            Log::info('SequenceProgression: Skipping — weekend');
            return;
        }

        $brands = Brand::whereHas('sequenceSchedules', fn($q) => $q->where('is_active', true))->get();

        $stats = [
            'drafts_created' => 0,
            'by_segment' => [],
            'by_step' => [],
            'sequences_completed' => 0,
            'sequences_stopped_suppressed' => 0,
            'sequences_stopped_replied' => 0,
        ];

        foreach ($brands as $brand) {
            $result = $this->processBrand($brand);
            foreach ($result as $key => $val) {
                if (is_numeric($val)) {
                    $stats[$key] = ($stats[$key] ?? 0) + $val;
                }
            }
        }

        $this->sendTelegramSummary($stats);
        $this->logActivity($stats);
    }

    private function processBrand(Brand $brand): array
    {
        $result = ['drafts_created' => 0, 'sequences_completed' => 0, 'sequences_stopped_suppressed' => 0, 'sequences_stopped_replied' => 0];

        $leads = Lead::where('brand_id', $brand->id)
            ->whereNotIn('status', ['suppressed', 'closed', 'interested', 'not_interested', 'no_email_found'])
            ->whereHas('emailMessages', fn($q) => $q->where('status', 'sent'))
            ->get();

        foreach ($leads as $lead) {
            $leadResult = $this->processLead($lead, $brand);
            foreach ($leadResult as $key => $val) {
                $result[$key] = ($result[$key] ?? 0) + $val;
            }
        }

        return $result;
    }

    private function processLead(Lead $lead, Brand $brand): array
    {
        $result = ['drafts_created' => 0, 'sequences_completed' => 0, 'sequences_stopped_suppressed' => 0, 'sequences_stopped_replied' => 0];

        // Find the last SENT email for this lead
        $lastSent = $lead->emailMessages()
            ->where('status', 'sent')
            ->orderByDesc('sequence_step')
            ->first();

        if (! $lastSent) {
            return $result;
        }

        $nextStep = $lastSent->sequence_step + 1;

        // Check if we've reached the end of the sequence
        if ($nextStep > 5) {
            $result['sequences_completed'] = 1;
            return $result;
        }

        // Check if next step already exists
        $existingNext = $lead->emailMessages()
            ->where('sequence_step', $nextStep)
            ->first();

        if ($existingNext) {
            return $result; // Already drafted or sent
        }

        // Check suppression
        if ($lead->email && Suppression::where('brand_id', $brand->id)->where('email', $lead->email)->exists()) {
            Log::info("SequenceProgression: Lead {$lead->id} ({$lead->company_name}) is suppressed — stopping sequence");
            $result['sequences_stopped_suppressed'] = 1;
            return $result;
        }

        // Get the schedule for this step + segment
        $schedule = SequenceSchedule::where('brand_id', $brand->id)
            ->where('segment', $lead->segment)
            ->where('step', $nextStep)
            ->where('is_active', true)
            ->first();

        if (! $schedule) {
            return $result;
        }

        // Check if enough days have passed
        $daysSinceLastSent = $lastSent->sent_at->diffInDays(now());
        if ($daysSinceLastSent < $schedule->days_after_previous) {
            return $result;
        }

        // Check: no other email sent to this lead today
        $sentToday = $lead->emailMessages()
            ->whereDate('sent_at', today())
            ->exists();

        if ($sentToday) {
            return $result;
        }

        // Ready to create the draft
        $this->createDraftForNextStep($lead, $brand, $nextStep, $schedule);
        $result['drafts_created'] = 1;

        return $result;
    }

    private function createDraftForNextStep(Lead $lead, Brand $brand, int $step, SequenceSchedule $schedule): void
    {
        EmailMessage::create([
            'lead_id' => $lead->id,
            'brand_id' => $brand->id,
            'sequence_step' => $step,
            'subject' => null,
            'body' => null,
            'status' => 'draft',
            'approval_status' => 'needs_content',
            'scheduled_for' => now(),
        ]);

        LeadEvent::create([
            'lead_id' => $lead->id,
            'event_type' => 'sequence_step_queued',
            'source' => 'sequence_scheduler',
            'payload' => [
                'step' => $step,
                'days_since_last' => $lead->emailMessages()
                    ->where('status', 'sent')
                    ->latest('sent_at')
                    ->first()
                    ?->sent_at
                    ->diffInDays(now()),
                'segment' => $lead->segment,
                'purpose' => $schedule->purpose,
            ],
        ]);

        Log::info("SequenceProgression: Queued step {$step} for lead {$lead->id} ({$lead->company_name})");
    }

    private function sendTelegramSummary(array $stats): void
    {
        if ($stats['drafts_created'] === 0 && $stats['sequences_completed'] === 0 && $stats['sequences_stopped_suppressed'] === 0 && $stats['sequences_stopped_replied'] === 0) {
            return;
        }

        $telegram = app(TelegramService::class);
        if (! $telegram->isConfigured()) {
            return;
        }

        $text = "📋 <b>Sequence Progression Report — " . now()->format('M j') . "</b>\n\n";

        if ($stats['drafts_created'] > 0) {
            $text .= "New drafts created: <b>{$stats['drafts_created']}</b>\n";
            foreach ($stats['by_segment'] ?? [] as $seg => $count) {
                $text .= "├── {$seg}: {$count}\n";
            }
        } else {
            $text .= "No new drafts created.\n";
        }

        $text .= "\n";
        if ($stats['sequences_completed'] > 0) {
            $text .= "✅ Sequences completed: {$stats['sequences_completed']}\n";
        }
        if ($stats['sequences_stopped_suppressed'] > 0) {
            $text .= "⛔ Stopped (suppressed): {$stats['sequences_stopped_suppressed']}\n";
        }
        if ($stats['sequences_stopped_replied'] > 0) {
            $text .= "💬 Stopped (replied): {$stats['sequences_stopped_replied']}\n";
        }

        $text .= "\nNext run: Tomorrow 5:00 AM";

        $telegram->sendMessage($text);
    }

    private function logActivity(array $stats): void
    {
        $logger = app(ActivityLogger::class);

        if ($stats['drafts_created'] > 0) {
            $logger->log([
                'source' => 'laravel.scheduler.sequence-progression',
                'event_type' => 'system',
                'title' => "Sequence progression: {$stats['drafts_created']} new drafts queued",
                'metadata' => $stats,
                'severity' => 'info',
            ]);
        }

        if ($stats['sequences_completed'] > 0) {
            $logger->log([
                'source' => 'laravel.scheduler.sequence-progression',
                'event_type' => 'system',
                'title' => "{$stats['sequences_completed']} leads completed all 5 sequence steps",
                'metadata' => $stats,
                'severity' => 'success',
            ]);
        }
    }
}
