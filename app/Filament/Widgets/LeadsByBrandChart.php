<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

class LeadsByBrandChart extends ChartWidget
{
    protected ?string $heading = 'Leads by Brand';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = Lead::selectRaw('brands.name as brand, COUNT(*) as count')
            ->join('brands', 'leads.brand_id', '=', 'brands.id')
            ->groupBy('brands.name')
            ->pluck('count', 'brand')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => array_values($data),
                    'backgroundColor' => [
                        '#1a56db',
                        '#059669',
                        '#7c3aed',
                        '#dc2626',
                    ],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
