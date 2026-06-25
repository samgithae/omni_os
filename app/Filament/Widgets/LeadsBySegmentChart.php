<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

class LeadsBySegmentChart extends ChartWidget
{
    protected ?string $heading = 'Leads by Segment';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = Lead::selectRaw('segment, COUNT(*) as count')
            ->groupBy('segment')
            ->pluck('count', 'segment')
            ->toArray();

        $colors = [
            'rabbit' => '#10b981',
            'deer' => '#f59e0b',
            'mouse' => '#6b7280',
            'elephant' => '#ef4444',
        ];

        $labels = array_keys($data);
        $bgColors = array_map(fn ($label) => $colors[$label] ?? '#999', $labels);

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => array_values($data),
                    'backgroundColor' => $bgColors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
