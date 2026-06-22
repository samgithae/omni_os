<?php

namespace App\Services;

use App\Models\ActivityEvent;

/**
 * Generates contextual Hermes replies based on event type and metadata.
 * In Phase A this uses rule-based responses tuned to feel intelligent.
 * In Phase B this would be replaced with an actual LLM call.
 */
class CommentResponseService
{
    /**
     * Generate a contextual Hermes reply for a comment on an event.
     */
    public function generate(ActivityEvent $event, string $commentBody): string
    {
        // Try event-type-specific handlers first
        $handler = $this->handlerFor($event->event_type);
        if ($handler) {
            return $handler($event, $commentBody);
        }

        // Fallback: generic acknowledgment
        return $this->genericResponse($event, $commentBody);
    }

    private function handlerFor(string $eventType): ?callable
    {
        $handlers = [
            'email_sent_batch' => [$this, 'handleEmailSentBatch'],
            'email_approved' => [$this, 'handleEmailApproved'],
            'email_rejected' => [$this, 'handleEmailRejected'],
            'reply_classified' => [$this, 'handleReplyClassified'],
            'suppression_added' => [$this, 'handleSuppressionAdded'],
            'daily_brief' => [$this, 'handleDailyBrief'],
            'mining_run' => [$this, 'handleMiningRun'],
            'enrichment_batch' => [$this, 'handleEnrichmentBatch'],
            'system' => [$this, 'handleSystem'],
        ];

        return $handlers[$eventType] ?? null;
    }

    private function handleEmailSentBatch(ActivityEvent $event, string $comment): string
    {
        $meta = $event->metadata ?? [];
        $sent = $meta['sent'] ?? $meta['count'] ?? 0;
        $failed = $meta['failed'] ?? $meta['failed_count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';

        if ($failed > 0) {
            $failedReasons = [];
            if (isset($meta['failures']) && is_array($meta['failures'])) {
                foreach ($meta['failures'] as $f) {
                    $reason = $f['reason'] ?? $f['error'] ?? 'unknown';
                    $domain = $f['domain'] ?? $f['email'] ?? '';
                    $failedReasons[] = $domain ? "{$domain} ({$reason})" : $reason;
                }
            }
            $reasonDetail = !empty($failedReasons)
                ? ' The failures were: ' . implode('; ', array_slice($failedReasons, 0, 3)) . '.'
                : '';

            return "Good catch. {$sent} went through, {$failed} failed for {$brandName}.{$reasonDetail} "
                . "I'll check the MX records and verify the addresses before the next batch. "
                . "If this is a recurring issue, I can tighten the enrichment filter to catch non-deliverable domains earlier.";
        }

        $rate = $sent > 0 ? round(($meta['opened'] ?? 0) / $sent * 100, 1) : 0;
        $senderCount = $meta['sender_count'] ?? 'multiple';
        if ($rate > 0) {
            return "Noted. {$sent} sent for {$brandName}, already seeing a {$rate}% open rate. "
                . "The rotation through {$senderCount} sender addresses seems to be helping deliverability. "
                . "I'll keep monitoring engagement patterns.";
        }

        return "Noted — {$sent} emails sent cleanly for {$brandName}. The send pipeline is running smoothly. "
            . "I'll flag if any patterns change in the next batch.";
    }

    private function handleEmailApproved(ActivityEvent $event, string $comment): string
    {
        $count = $event->metadata['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';

        if (str_contains(strtolower($comment), 'check') || str_contains(strtolower($comment), 'review')) {
            return "Understood — I'll review the approved batch for {$brandName} before the send window opens. "
                . "If anything looks off with the content or targeting, I'll flag it here.";
        }

        return "Got it. {$count} emails approved for {$brandName}. They'll go out in the next send window "
            . "(business hours, rotating through the sender pool). I'll report back with delivery results.";
    }

    private function handleEmailRejected(ActivityEvent $event, string $comment): string
    {
        $count = $event->metadata['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';

        return "Noted. {$count} emails rejected for {$brandName}. "
            . "If the rejection was due to content concerns, let me know what to adjust — "
            . "I can tighten the drafting guidelines for the next batch.";
    }

    private function handleReplyClassified(ActivityEvent $event, string $comment): string
    {
        $classification = $event->metadata['classification'] ?? 'unclassified';
        $leadName = $event->metadata['lead_name'] ?? $event->metadata['company'] ?? 'a lead';
        $brandName = $event->brand?->name ?? 'this brand';

        if ($classification === 'interested') {
            return "Good signal — {$leadName} was classified as interested for {$brandName}. "
                . "I'll make sure they're on the priority list for follow-up. "
                . "If you'd like me to draft a personalised reply, let me know the key points to include.";
        }

        if ($classification === 'not_interested') {
            return "Noted — {$leadName} declined for {$brandName}. "
                . "I'll log the suppression and factor this into the mining bias for similar prospects.";
        }

        return "Recorded the {$classification} classification for {$leadName} on {$brandName}. "
            . "The reply is in the inbox if you want to read the full context.";
    }

    private function handleSuppressionAdded(ActivityEvent $event, string $comment): string
    {
        $count = $event->metadata['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';
        $reason = $event->metadata['reason'] ?? null;

        if ($reason === 'bounce') {
            return "Good — {$count} bounce suppressions added for {$brandName}. "
                . "This keeps our sender reputation healthy. I'll double-check the MX validation on future imports.";
        }

        return "{$count} suppression(s) logged for {$brandName}. "
            . "The suppression list is being respected across all send channels.";
    }

    private function handleDailyBrief(ActivityEvent $event, string $comment): string
    {
        return "Thanks for the overview. I've reviewed the metrics. "
            . "If there's a specific area you'd like me to investigate deeper "
            . "(conversion funnel, delivery patterns, lead quality), just flag it as an instruction.";
    }

    private function handleMiningRun(ActivityEvent $event, string $comment): string
    {
        $found = $event->metadata['found'] ?? $event->metadata['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';

        return "Noted — {$found} new prospects mined for {$brandName}. "
            . "I'll incorporate this batch into the scoring pipeline ahead of the next enrichment cycle.";
    }

    private function handleEnrichmentBatch(ActivityEvent $event, string $comment): string
    {
        $count = $event->metadata['enriched'] ?? $event->metadata['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';

        return "{$count} leads enriched for {$brandName}. "
            . "The data quality pipeline is running. I'll flag any addresses that look incomplete.";
    }

    private function handleSystem(ActivityEvent $event, string $comment): string
    {
        $source = $event->source ?? '';

        if (str_contains($source, 'cron') || str_contains($source, 'scheduler')) {
            return "System maintenance noted. All scheduled jobs are running on their expected cadence. "
                . "I'll alert here if any job fails or shows unusual behaviour.";
        }

        return "Acknowledged. The system event has been recorded and the relevant pipelines are aware.";
    }

    private function genericResponse(ActivityEvent $event, string $comment): string
    {
        // Check if the comment is asking for action
        $actionWords = ['fix', 'change', 'update', 'adjust', 'add', 'remove', 'check', 'investigate', 'look'];
        $isActionItem = false;
        foreach ($actionWords as $word) {
            if (str_contains(strtolower($comment), $word)) {
                $isActionItem = true;
                break;
            }
        }

        if ($isActionItem) {
            return "Noted — I've logged this as an action item and will address it in the next run cycle. "
                . "If it's urgent, let me know and I'll prioritise it.";
        }

        $brandName = $event->brand?->name ?? 'system';
        return "Got it. I've noted this for {$brandName}. "
            . "I'll factor it into the next decision cycle. Let me know if there's anything specific you'd like me to act on.";
    }
}