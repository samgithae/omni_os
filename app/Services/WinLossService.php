<?php

namespace App\Services;

use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\LeadEvent;

/**
 * Win-Loss analytics service.
 *
 * Aggregates reply outcomes and pipeline metrics by category, city, segment,
 * and email template — so the system can bias future mining + drafting toward
 * what actually produces replies.
 */
class WinLossService
{
    /**
     * Build the full win-loss report.
     *
     * @return array{
     *     funnel: array,
     *     rates: array,
     *     by_category: array,
     *     by_city: array,
     *     by_segment: array,
     *     by_step: array,
     *     reply_outcomes: array,
     *     generated_at: string
     * }
     */
    public function report(): array
    {
        return [
            'funnel' => $this->funnel(),
            'rates' => $this->rates(),
            'by_category' => $this->byCategory(),
            'by_city' => $this->byCity(),
            'by_segment' => $this->bySegment(),
            'by_step' => $this->bySequenceStep(),
            'reply_outcomes' => $this->replyOutcomes(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Conversion funnel: leads → enriched → emailed → replied → interested.
     */
    public function funnel(): array
    {
        $totalLeads = Lead::count();
        $enriched = Lead::where('status', 'enriched')->count();
        $withEmail = Lead::whereNotNull('email')->count();
        $emailed = Lead::whereHas('emailMessages', fn ($q) => $q->where('status', 'sent'))->count();
        $replied = Lead::whereIn('status', ['replied', 'interested', 'not_interested'])->count();
        $interested = Lead::where('status', 'interested')->count();

        return [
            'leads' => $totalLeads,
            'with_email' => $withEmail,
            'enriched' => $enriched,
            'emailed' => $emailed,
            'replied' => $replied,
            'interested' => $interested,
            // Conversion rates between stages
            'enrichment_rate' => $this->pct($enriched, $totalLeads),
            'email_coverage' => $this->pct($emailed, $withEmail),
            'reply_rate' => $this->pct($replied, $emailed),
            'interest_rate' => $this->pct($interested, $replied),
            'overall_conversion' => $this->pct($interested, $totalLeads),
        ];
    }

    /**
     * Email engagement rates.
     */
    public function rates(): array
    {
        $totalSent = EmailMessage::where('status', 'sent')->count();
        $opened = EmailMessage::whereNotNull('opened_at')->count();
        $clicked = EmailMessage::whereNotNull('clicked_at')->count();

        // Replies come from lead_events with event_type=replied
        $replied = LeadEvent::where('event_type', 'replied')->count();

        return [
            'sent' => $totalSent,
            'opened' => $opened,
            'clicked' => $clicked,
            'replied' => $replied,
            'open_rate' => $this->pct($opened, $totalSent),
            'click_rate' => $this->pct($clicked, $totalSent),
            'reply_rate' => $this->pct($replied, $totalSent),
            'click_to_open_rate' => $this->pct($clicked, $opened),
        ];
    }

    /**
     * Performance breakdown by lead category.
     * Shows which categories produce the most leads, replies, and interest.
     */
    public function byCategory(): array
    {
        return $this->dimensionBreakdown('category', 15);
    }

    /**
     * Performance breakdown by city.
     */
    public function byCity(): array
    {
        return $this->dimensionBreakdown('city', 15);
    }

    /**
     * Performance breakdown by segment.
     */
    public function bySegment(): array
    {
        return $this->dimensionBreakdown('segment', 10);
    }

    /**
     * Performance breakdown by email sequence step.
     */
    public function bySequenceStep(): array
    {
        $steps = EmailMessage::selectRaw("sequence_step, COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked")
            ->groupBy('sequence_step')
            ->orderBy('sequence_step')
            ->get()
            ->map(function ($row) {
                return [
                    'step' => $row->sequence_step,
                    'total' => $row->total,
                    'sent' => $row->sent,
                    'opened' => $row->opened,
                    'clicked' => $row->clicked,
                    'open_rate' => $this->pct($row->opened, $row->sent),
                    'click_rate' => $this->pct($row->clicked, $row->sent),
                ];
            })
            ->toArray();

        return $steps;
    }

    /**
     * Reply outcome distribution (interested / not_interested / unsubscribe / etc).
     */
    public function replyOutcomes(): array
    {
        // From lead_events where event_type = status_changed and payload contains reply info
        $events = LeadEvent::where('event_type', 'status_changed')
            ->whereNotNull('payload')
            ->get();

        $outcomes = [
            'interested' => 0,
            'not_interested' => 0,
            'unsubscribe' => 0,
            'out_of_office' => 0,
            'bounce' => 0,
        ];

        foreach ($events as $event) {
            $payload = $event->payload ?? [];
            $to = $payload['to'] ?? '';

            if (array_key_exists($to, $outcomes)) {
                $outcomes[$to]++;
            }
        }

        // Also count from lead status directly
        $statusCounts = Lead::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Merge — use status counts as the source of truth for interested/not_interested
        $outcomes['interested'] = $statusCounts['interested'] ?? 0;
        $outcomes['not_interested'] = $statusCounts['not_interested'] ?? 0;

        $total = array_sum($outcomes);

        return [
            'counts' => $outcomes,
            'total' => $total,
            'percentages' => array_map(fn ($c) => $this->pct($c, $total), $outcomes),
        ];
    }

    /**
     * Generic dimension breakdown.
     * For each value of the dimension column, count leads, enriched, emailed, replied, interested.
     */
    private function dimensionBreakdown(string $column, int $limit): array
    {
        // Get all values with lead counts
        $values = Lead::selectRaw("{$column}, COUNT(*) as lead_count")
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('lead_count')
            ->limit($limit)
            ->pluck('lead_count', $column)
            ->toArray();

        $results = [];

        foreach ($values as $value => $leadCount) {
            $enriched = Lead::where($column, $value)->where('status', 'enriched')->count();
            $withEmail = Lead::where($column, $value)->whereNotNull('email')->count();
            $emailed = Lead::where($column, $value)
                ->whereHas('emailMessages', fn ($q) => $q->where('status', 'sent'))
                ->count();
            $replied = Lead::where($column, $value)
                ->whereIn('status', ['replied', 'interested', 'not_interested'])
                ->count();
            $interested = Lead::where($column, $value)->where('status', 'interested')->count();

            $results[] = [
                'dimension' => $value,
                'leads' => $leadCount,
                'with_email' => $withEmail,
                'enriched' => $enriched,
                'emailed' => $emailed,
                'replied' => $replied,
                'interested' => $interested,
                'enrichment_rate' => $this->pct($enriched, $leadCount),
                'reply_rate' => $this->pct($replied, $emailed),
                'interest_rate' => $this->pct($interested, $replied),
            ];
        }

        // Sort by replied desc, then by leads desc
        usort($results, function ($a, $b) {
            if ($b['replied'] !== $a['replied']) {
                return $b['replied'] <=> $a['replied'];
            }

            return $b['leads'] <=> $a['leads'];
        });

        return $results;
    }

    /**
     * Compute percentage, handling division by zero.
     */
    private function pct(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }
}
