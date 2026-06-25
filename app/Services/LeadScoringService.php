<?php

namespace App\Services;

use App\Models\Lead;

/**
 * Calculates a 0-100 lead score based on:
 *   - Segment (deal-size potential)           max 25
 *   - Data completeness (email/phone/etc)     max 40
 *   - Email confidence (enrichment quality)   max 15
 *   - Engagement (opens/clicks/replies)        max 15
 *   - Status bonus (pipeline position)        max 5
 *   -----------------------------------------
 *   Total raw max                              100
 */
class LeadScoringService
{
    /** Segment weight — bigger deals score higher */
    private const SEGMENT_WEIGHTS = [
        'elephant' => 25,
        'deer' => 20,
        'rabbit' => 15,
        'mouse' => 5,
    ];

    /** Normalized 0-100 ceiling */
    private const MAX_SCORE = 100;

    /**
     * Calculate the score for a single lead.
     *
     * @param  Lead  $lead  The lead (with emailMessages relationship loaded)
     * @return array{score: int, breakdown: array<string, int>}
     */
    public function calculate(Lead $lead): array
    {
        $breakdown = [];

        // 1. Segment (max 25)
        $breakdown['segment'] = self::SEGMENT_WEIGHTS[$lead->segment] ?? 5;

        // 2. Data completeness (max 40)
        $breakdown['data_completeness'] = 0;
        if (! empty($lead->email)) {
            $breakdown['data_completeness'] += 20;
        }
        if (! empty($lead->phone)) {
            $breakdown['data_completeness'] += 10;
        }
        if (! empty($lead->website)) {
            $breakdown['data_completeness'] += 7;
        }
        if (! empty($lead->contact_name)) {
            $breakdown['data_completeness'] += 3;
        }

        // 3. Email confidence (max 15)
        // If email exists but confidence is null (legacy imports), treat as inferred
        $confidence = $lead->email_confidence;
        if (! $confidence && ! empty($lead->email)) {
            $confidence = 'inferred';
        }
        $breakdown['email_confidence'] = match ($confidence) {
            'verified' => 15,
            'inferred' => 10,
            'estimated' => 5,
            default => 0,
        };

        // 4. Engagement (max 15) — from email messages
        $breakdown['engagement'] = $this->calculateEngagement($lead);

        // 5. Status bonus (max 5)
        $breakdown['status_bonus'] = match ($lead->status) {
            'interested' => 5,
            'replied' => 4,
            'enriched' => 3,
            'emailed' => 2,
            'enriching' => 1,
            default => 0,
        };

        $raw = array_sum($breakdown);
        $score = min(self::MAX_SCORE, $raw);

        return [
            'score' => $score,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate engagement sub-score from email messages.
     * Rules: opened = +5, clicked = +5 (on top of opened), replied = +5 (on top of clicked).
     * Cap at 15.
     */
    private function calculateEngagement(Lead $lead): int
    {
        $messages = $lead->emailMessages;

        // If relationship isn't loaded, query it
        if (! $messages) {
            $messages = $lead->emailMessages()->get();
        }

        if ($messages->isEmpty()) {
            return 0;
        }

        $hasOpened = $messages->whereNotNull('opened_at')->isNotEmpty();
        $hasClicked = $messages->whereNotNull('clicked_at')->isNotEmpty();

        // Check for replies via lead events
        $hasReplied = $lead->events()
            ->where('event_type', 'replied')
            ->exists();

        $score = 0;
        if ($hasOpened) {
            $score += 5;
        }
        if ($hasClicked) {
            $score += 5;
        }
        if ($hasReplied) {
            $score += 5;
        }

        return min(15, $score);
    }

    /**
     * Recalculate and persist the score for a single lead.
     *
     * @return int The new score
     */
    public function recalculate(Lead $lead): int
    {
        // Eager load what we need
        $lead->load(['emailMessages', 'events']);

        $result = $this->calculate($lead);

        $lead->score = $result['score'];
        $lead->saveQuietly(); // Don't trigger status transition events

        return $result['score'];
    }

    /**
     * Get a human-readable tier label for a score.
     */
    public static function tier(int $score): string
    {
        return match (true) {
            $score >= 80 => 'hot',
            $score >= 60 => 'warm',
            $score >= 40 => 'moderate',
            $score >= 20 => 'cold',
            default => 'frigid',
        };
    }

    /**
     * Get the color for a score tier (Tailwind classes).
     */
    public static function tierColor(int $score): string
    {
        return match (true) {
            $score >= 80 => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            $score >= 60 => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            $score >= 40 => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            $score >= 20 => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            default => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
        };
    }

    /**
     * Hex color for charts/dots.
     */
    public static function tierHex(int $score): string
    {
        return match (true) {
            $score >= 80 => '#ef4444',
            $score >= 60 => '#f97316',
            $score >= 40 => '#f59e0b',
            $score >= 20 => '#3b82f6',
            default => '#9ca3af',
        };
    }
}
