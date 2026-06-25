<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\SequenceSchedule;
use Illuminate\Console\Command;

class SeedSequenceSchedules extends Command
{
    protected $signature = 'sequence:seed-schedules';

    protected $description = 'Seed initial sequence schedules for all brands';

    public function handle(): int
    {
        $brands = Brand::all();

        foreach ($brands as $brand) {
            $schedules = [
                // Rabbits — faster cadence for smaller deals
                ['segment' => 'rabbit', 'step' => 1, 'days_after_previous' => 0, 'purpose' => 'Introduction — specific observation about their business'],
                ['segment' => 'rabbit', 'step' => 2, 'days_after_previous' => 2, 'purpose' => 'Quick follow-up — add one proof point'],
                ['segment' => 'rabbit', 'step' => 3, 'days_after_previous' => 4, 'purpose' => 'New angle — different value proposition'],
                ['segment' => 'rabbit', 'step' => 4, 'days_after_previous' => 7, 'purpose' => 'Case study or social proof'],
                ['segment' => 'rabbit', 'step' => 5, 'days_after_previous' => 8, 'purpose' => 'Low-pressure breakup — closing the loop'],

                // Deer — slower cadence for larger deals
                ['segment' => 'deer', 'step' => 1, 'days_after_previous' => 0, 'purpose' => 'Introduction — reference their sector specifically'],
                ['segment' => 'deer', 'step' => 2, 'days_after_previous' => 3, 'purpose' => 'Follow-up with relevant insight'],
                ['segment' => 'deer', 'step' => 3, 'days_after_previous' => 6, 'purpose' => 'New angle — bigger value proposition'],
                ['segment' => 'deer', 'step' => 4, 'days_after_previous' => 9, 'purpose' => 'Case study or ROI breakdown'],
                ['segment' => 'deer', 'step' => 5, 'days_after_previous' => 12, 'purpose' => 'Low-pressure breakup'],
            ];

            foreach ($schedules as $schedule) {
                SequenceSchedule::updateOrCreate(
                    ['brand_id' => $brand->id, 'segment' => $schedule['segment'], 'step' => $schedule['step']],
                    $schedule
                );
            }

            $this->line("Seeded schedules for {$brand->name}");
        }

        $this->info('Sequence schedules seeded: '.SequenceSchedule::count().' total.');

        return self::SUCCESS;
    }
}
