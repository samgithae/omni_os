<?php

namespace App\Services;

use App\Models\Lead;

class HiringSignalScoreCalculator
{
    /**
     * Calculate a deterministic 0-150 hiring signal score from raw_data.
     * This is SEPARATE from LeadScoringService and stored in hiring_signal_score.
     *
     * @param  array<string, mixed>  $rawData
     *
     * Point table:
     *   Multiple active vacancies      +40
     *   Hiring customer-facing teams   +30
     *   Hiring graduate trainees       +20
     *   Multiple branches              +20
     *   Company size over 100 employees +15
     *   HR decision-maker identified   +15
     *   Public email found             +10
     */
    public function calculate(array $rawData): int
    {
        $hiringSignal = $rawData['hiring_signal'] ?? [];
        $score = 0;

        // Multiple active vacancies (+40)
        $vacancyCount = $hiringSignal['vacancy_count'] ?? 0;
        if ($vacancyCount > 1) {
            $score += 40;
        }

        // Hiring customer-facing teams (+30)
        $titles = $hiringSignal['vacancy_titles'] ?? [];
        $customerFacingKeywords = [
            'sales', 'business development', 'customer service', 'customer care',
            'customer success', 'call centre', 'call center', 'contact centre',
            'contact center', 'telesales', 'account manager', 'retail assistant',
            'cashier', 'front office', 'relationship officer', 'branch officer',
        ];
        if ($this->titlesContainKeywords($titles, $customerFacingKeywords)) {
            $score += 30;
        }

        // Hiring graduate trainees (+20)
        $traineeKeywords = ['graduate trainee', 'management trainee', 'trainee', 'internship'];
        if ($this->titlesContainKeywords($titles, $traineeKeywords)) {
            $score += 20;
        }

        // Multiple branches (+20)
        if (! empty($hiringSignal['multiple_branches'])) {
            $score += 20;
        }

        // Company size over 100 employees (+15)
        $companySize = $hiringSignal['company_size'] ?? 0;
        if ($companySize > 100) {
            $score += 15;
        }

        // HR decision-maker identified (+15)
        $hrContact = $hiringSignal['hr_contact'] ?? [];
        if (! empty($hrContact['name']) || ! empty($hrContact['email'])) {
            $score += 15;
        }

        // Public email found (+10)
        if (! empty($hiringSignal['public_email'])) {
            $score += 10;
        }

        return min(150, $score);
    }

    /**
     * Recalculate and persist hiring_signal_score on a Lead model.
     */
    public function recalculate(Lead $lead): int
    {
        $score = $this->calculate($lead->raw_data ?? []);
        $lead->hiring_signal_score = $score;
        $lead->saveQuietly();

        return $score;
    }

    /**
     * @param  array<int, string>  $titles
     * @param  array<int, string>  $keywords
     */
    private function titlesContainKeywords(array $titles, array $keywords): bool
    {
        foreach ($titles as $title) {
            $lower = strtolower($title);
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }
}
