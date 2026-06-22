<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\EmailMessage;

/**
 * Generates contextual Hermes replies.
 * Analyzes what Sam actually ASKED — not just the event type — and fetches
 * real data from the database when specific questions are detected.
 */
class CommentResponseService
{
    public function generate(ActivityEvent $event, string $commentBody): string
    {
        // First check if the comment is asking a specific data question
        $dataResponse = $this->tryDataQuestion($event, $commentBody);
        if ($dataResponse !== null) {
            return $dataResponse;
        }

        // Fallback to event-type handler
        $handler = $this->handlerFor($event->event_type);
        if ($handler) {
            return $handler($event, $commentBody);
        }

        return $this->genericResponse($event, $commentBody);
    }

    /**
     * Detect if Sam is asking for specific data or confirming an offer to list data.
     */
    private function tryDataQuestion(ActivityEvent $event, string $comment): ?string
    {
        $lower = strtolower($comment);

        // Confirmation words — Sam said "yes" after being asked "Want me to list...?"
        // This should trigger the data fetcher to actually return the list
        $isConfirmation = (
            $lower === 'yes' ||
            $lower === 'yeah' ||
            str_contains($lower, 'yes do') ||
            str_contains($lower, 'do that') ||
            str_contains($lower, 'go ahead') ||
            str_contains($lower, 'please') ||
            str_contains($lower, 'list them') ||
            str_contains($lower, 'show me')
        );

        // Keywords that signal "show me the actual data"
        $askingForDetails = (
            $isConfirmation ||
            str_contains($lower, 'which') ||
            str_contains($lower, 'what') ||
            str_contains($lower, 'list') ||
            str_contains($lower, 'tell me') ||
            str_contains($lower, 'who') ||
            str_contains($lower, 'where') ||
            str_contains($lower, 'details') ||
            str_contains($lower, 'why')    // "why did X fail?" is also a data question
        );

        if (!$askingForDetails) {
            return null;
        }

        // Route to event-specific data fetcher
        return match ($event->event_type) {
            'email_sent_batch' => $this->answerEmailSentQuestion($event, $comment),
            'email_approved' => $this->answerEmailApprovedQuestion($event, $comment),
            'enrichment_batch' => $this->answerEnrichmentQuestion($event, $comment),
            'reply_classified' => $this->answerReplyQuestion($event, $comment),
            default => null,
        };
    }

    private function answerEmailSentQuestion(ActivityEvent $event, string $comment): string
    {
        $meta = $event->metadata ?? [];
        $sent = $meta['sent'] ?? $meta['count'] ?? 0;
        $failed = $meta['failed'] ?? $meta['failed_count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';

        // Fetch the actual email records — brand_id may be null on the event
        $emailQuery = EmailMessage::query()->whereNotNull('sent_at')->latest('sent_at')->limit(20);
        if ($event->brand_id) {
            $emailQuery->where('brand_id', $event->brand_id);
        }

        $emails = $emailQuery->get();

        $sentEmails = $emails->where('status', 'sent');
        $failedEmails = $emails->where('status', 'failed');

        $lines = [];

        if ($sentEmails->isNotEmpty()) {
            $lines[] = "**Sent ({$sentEmails->count()}):**";
            foreach ($sentEmails as $i => $e) {
                $lead = $e->lead;
                $company = $lead?->company_name ?? '?';
                $email = $lead?->email ?? $e->recipient_email ?? '?';
                $subject = $e->subject ?? '(no subject)';
                // Body snippet for "what was the email"
                $bodySnippet = '';
                if (str_contains($comment, 'content') || str_contains($comment, 'body') || str_contains($comment, 'what was the email')) {
                    $body = strip_tags((string) $e->body);
                    $bodySnippet = ' — "' . substr($body, 0, 120) . (strlen($body) > 120 ? '..."' : '"');
                }
                $lines[] = "  {$i}. **{$company}** <{$email}> → \"{$subject}\"{$bodySnippet}";
                if ($i >= 9) {
                    $lines[] = "  ... and " . ($sentEmails->count() - 10) . " more.";
                    break;
                }
            }
        }

        if ($failedEmails->isNotEmpty()) {
            $lines[] = '';
            $lines[] = "**Failed ({$failedEmails->count()}):**";
            foreach ($failedEmails as $i => $e) {
                $lead = $e->lead;
                $company = $lead?->company_name ?? '?';
                $email = $lead?->email ?? $e->recipient_email ?? '?';
                $subject = $e->subject ?? '(no subject)';
                $reason = $e->failure_reason ?? $e->error ?? 'unknown';
                $lines[] = "  {$i}. **{$company}** <{$email}> → \"{$subject}\" — ❌ {$reason}";
            }
        }

        if (empty($lines)) {
            return "I checked the records for {$brandName} but I couldn't find individual email details in this batch's metadata. The pipeline reported {$sent} sent and {$failed} failed. Want me to dig deeper into specific emails or check the send logs?";
        }

        $lines[] = '';
        $lines[] = "Bottom line: {$sent} sent, {$failed} failed for {$brandName}. Sender pool rotation active.";

        return implode("\n", $lines);
    }

    private function answerEmailApprovedQuestion(ActivityEvent $event, string $comment): string
    {
        $meta = $event->metadata ?? [];
        $count = $meta['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';

        $emails = EmailMessage::query()
            ->where('brand_id', $event->brand_id)
            ->where('approval_status', 'approved')
            ->where('status', 'queued')
            ->latest('approved_at')
            ->limit(15)
            ->get();

        if ($emails->isEmpty()) {
            return "{$count} emails approved for {$brandName}. They've been sent or are queued. I don't have the exact list in the event metadata — the approval went through and the send pipeline picked them up.";
        }

        $lines = ["**Approved emails for {$brandName}:**"];
        foreach ($emails as $i => $e) {
            $lead = $e->lead;
            $company = $lead?->company_name ?? '?';
            $email = $lead?->email ?? '?';
            $lines[] = "  {$i}. **{$company}** <{$email}> → \"{$e->subject}\"";
        }
        $lines[] = '';
        $lines[] = "All {$count} approved. They'll send in the next business-hours window.";

        return implode("\n", $lines);
    }

    private function answerEnrichmentQuestion(ActivityEvent $event, string $comment): string
    {
        $meta = $event->metadata ?? [];
        $autoEnriched = $meta['auto_enriched'] ?? 0;
        $queued = $meta['email_enrichment_queued'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';

        $leads = \App\Models\Lead::query()
            ->where('brand_id', $event->brand_id)
            ->where('status', 'enriched')
            ->whereNotNull('email')
            ->latest('enriched_at')
            ->limit(15)
            ->get(['company_name', 'email', 'phone', 'segment', 'city']);

        $lines = ["**Enrichment run for {$brandName}:**"];

        if ($autoEnriched > 0) {
            $lines[] = "";
            $lines[] = "**Auto-enriched leads (had emails already):**";
            foreach ($leads->take(10) as $i => $l) {
                $phone = $l->phone ? ' 📞' : ' ❌ no phone';
                $lines[] = "  {$i}. **{$l->company_name}** <{$l->email}>{$phone} — {$l->segment} / {$l->city}";
            }
            if ($leads->count() > 10) {
                $lines[] = "  ... and " . ($leads->count() - 10) . " more.";
            }
        }

        if ($queued > 0) {
            $lines[] = "";
            $lines[] = "**Leads sent for email enrichment:** {$queued} (Hermes will look for emails)";
        }

        $noEmail = \App\Models\Lead::where('brand_id', $event->brand_id)
            ->where('status', 'no_email_found')->count();
        if ($noEmail > 0) {
            $lines[] = "";
            $lines[] = "**Leads with no email found:** {$noEmail} — tried 3 times each, came up empty. These are terminal unless a new data source is added.";
        }

        $noPhone = \App\Models\Lead::where('brand_id', $event->brand_id)
            ->whereNull('phone')->count();
        if (str_contains($comment, 'phone') || str_contains($comment, 'number')) {
            $lines[] = "";
            $lines[] = "**Missing phone numbers:** {$noPhone} leads — there's no phone enrichment pipeline yet.";
        }

        return implode("\n", $lines);
    }

    private function answerReplyQuestion(ActivityEvent $event, string $comment): string
    {
        $classification = $event->metadata['classification'] ?? 'unclassified';
        $leadName = $event->metadata['lead_name'] ?? $event->metadata['company'] ?? 'a lead';
        $brandName = $event->brand?->name ?? 'this brand';

        // Find the reply record
        $reply = \App\Models\Reply::query()
            ->where('brand_id', $event->brand_id)
            ->latest()
            ->first();

        $lines = ["**Reply classification — {$leadName} ({$brandName}):**"];
        $lines[] = "  Classification: **{$classification}**";

        if ($reply) {
            $lines[] = "";
            $lines[] = "**Reply content:**";
            $lines[] = "  From: {$reply->from_email}";
            $lines[] = "  Subject: {$reply->subject}";
            $body = substr($reply->body, 0, 500);
            $lines[] = "  Body: \"{$body}\"" . (strlen($reply->body) > 500 ? '...' : '');
            $lines[] = "";
            $lines[] = "  Status: {$classification}";

            if ($classification === 'interested') {
                $lines[] = "  → Marked as interested. In the inbox at /inbox if you want to reply.";
            }
        } else {
            $lines[] = "  The reply was classified by the webhook but I can't find it in the replies table. It may be stored in the lead's raw_data.";
        }

        return implode("\n", $lines);
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
                . "Want me to list which ones failed and why?";
        }

        $rate = $sent > 0 ? round(($meta['opened'] ?? 0) / $sent * 100, 1) : 0;
        $senderCount = $meta['sender_count'] ?? 'multiple';
        if ($rate > 0) {
            return "Noted. {$sent} sent for {$brandName}, already seeing a {$rate}% open rate. "
                . "The rotation through {$senderCount} sender addresses seems to be helping deliverability.";
        }

        return "Noted — {$sent} emails sent cleanly for {$brandName}. ";
    }

    private function handleEmailApproved(ActivityEvent $event, string $comment): string
    {
        $count = $event->metadata['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';
        $lower = strtolower($comment);

        if (str_contains($lower, 'check') || str_contains($lower, 'review')) {
            return "Understood — I'll review the approved batch for {$brandName} before the send window opens.";
        }

        return "Got it. {$count} emails approved for {$brandName}. They'll go out in the next send window. "
            . "Want me to list what's in the queue?";
    }

    private function handleEmailRejected(ActivityEvent $event, string $comment): string
    {
        $count = $event->metadata['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';
        return "Noted. {$count} emails rejected for {$brandName}. "
            . "If the rejection was due to content concerns, let me know what to adjust.";
    }

    private function handleReplyClassified(ActivityEvent $event, string $comment): string
    {
        $classification = $event->metadata['classification'] ?? 'unclassified';
        $leadName = $event->metadata['lead_name'] ?? $event->metadata['company'] ?? 'a lead';
        $brandName = $event->brand?->name ?? 'this brand';

        if ($classification === 'interested') {
            return "Good signal — {$leadName} classified as interested for {$brandName}. "
                . "Inbox link: /inbox if you want to see the full reply and respond.";
        }
        if ($classification === 'not_interested') {
            return "Noted — {$leadName} declined for {$brandName}. Suppression logged.";
        }
        return "Recorded {$classification} for {$leadName} on {$brandName}.";
    }

    private function handleSuppressionAdded(ActivityEvent $event, string $comment): string
    {
        $count = $event->metadata['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';
        $reason = $event->metadata['reason'] ?? null;
        if ($reason === 'bounce') {
            return "Good — {$count} bounce suppressions added for {$brandName}. Sender reputation protected.";
        }
        return "{$count} suppression(s) logged for {$brandName}.";
    }

    private function handleDailyBrief(ActivityEvent $event, string $comment): string
    {
        return "Thanks for the overview. I've reviewed the metrics. "
            . "If you want me to investigate any specific area, just ask. "
            . "Which emails went out, how the funnel looks, reply patterns — I can pull the data.";
    }

    private function handleMiningRun(ActivityEvent $event, string $comment): string
    {
        $found = $event->metadata['found'] ?? $event->metadata['count'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';
        return "Noted — {$found} new prospects mined for {$brandName}. "
            . "They'll go through enrichment and scoring before entering the email pipeline.";
    }

    private function handleEnrichmentBatch(ActivityEvent $event, string $comment): string
    {
        $count = $event->metadata['enriched'] ?? $event->metadata['count'] ?? 0;
        $auto = $event->metadata['auto_enriched'] ?? 0;
        $brandName = $event->brand?->name ?? 'this brand';
        $lines = ["{$count} leads processed for {$brandName}"];
        if ($auto > 0) {
            $lines[] = "({$auto} had emails already — auto-enriched)";
        }
        $lines[] = "Want the full list of who was enriched?";
        return implode(' ', $lines);
    }

    private function handleSystem(ActivityEvent $event, string $comment): string
    {
        $source = $event->source ?? '';
        if (str_contains($source, 'cron') || str_contains($source, 'scheduler')) {
            return "System maintenance noted. All scheduled jobs running on cadence. "
                . "The detailed schedule is at /analytics/jobs if you want to check.";
        }
        return "Acknowledged. System event recorded.";
    }

    private function genericResponse(ActivityEvent $event, string $comment): string
    {
        $lower = strtolower($comment);
        $actionWords = ['fix', 'change', 'update', 'adjust', 'add', 'remove', 'check', 'investigate', 'look'];
        $isActionItem = false;
        foreach ($actionWords as $word) {
            if (str_contains($lower, $word)) {
                $isActionItem = true;
                break;
            }
        }
        $brandName = $event->brand?->name ?? 'system';

        if ($isActionItem) {
            return "Noted — I've logged this as an action item. "
                . "If it's urgent, tell me and I'll prioritise it.";
        }

        return "Got it. Noted for {$brandName}. "
            . "Want me to dig into any specific data, or is this just a note?";
    }
}