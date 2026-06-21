<?php

namespace App\Enums;

use InvalidArgumentException;

enum ActivityEventType: string
{
    case MiningRun = 'mining_run';
    case EnrichmentBatch = 'enrichment_batch';
    case EmailSentBatch = 'email_sent_batch';
    case EmailApproved = 'email_approved';
    case EmailRejected = 'email_rejected';
    case ReplyClassified = 'reply_classified';
    case SuppressionAdded = 'suppression_added';
    case DailyBrief = 'daily_brief';
    case System = 'system';
    case Deployment = 'deployment';

    public function label(): string
    {
        return match ($this) {
            self::MiningRun => 'Mining Run',
            self::EnrichmentBatch => 'Enrichment Batch',
            self::EmailSentBatch => 'Email Sent',
            self::EmailApproved => 'Email Approved',
            self::EmailRejected => 'Email Rejected',
            self::ReplyClassified => 'Reply Classified',
            self::SuppressionAdded => 'Suppression Added',
            self::DailyBrief => 'Daily Brief',
            self::System => 'System',
            self::Deployment => 'Deployment',
        };
    }

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException("Invalid activity event type [{$value}].");
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
