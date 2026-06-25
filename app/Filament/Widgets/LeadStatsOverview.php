<?php

namespace App\Filament\Widgets;

use App\Models\Brand;
use App\Models\Lead;
use App\Models\Suppression;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalLeads = Lead::count();
        $enrichedLeads = Lead::where('status', 'enriched')->count();
        $newLeads = Lead::where('status', 'new')->count();
        $suppressedCount = Suppression::count();
        $activeBrands = Brand::where('is_active', true)->count();

        $rabbits = Lead::where('segment', 'rabbit')->count();
        $deer = Lead::where('segment', 'deer')->count();

        return [
            Stat::make('Total Leads', $totalLeads)
                ->description("{$rabbits} rabbits, {$deer} deer")
                ->icon('heroicon-o-user-group')
                ->color('indigo'),

            Stat::make('New / Enriched', "{$newLeads} / {$enrichedLeads}")
                ->description('Lead status breakdown')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('success'),

            Stat::make('Suppressed', $suppressedCount)
                ->description('Do-not-contact list')
                ->icon('heroicon-o-no-symbol')
                ->color('danger'),

            Stat::make('Active Brands', $activeBrands)
                ->description('Brands in portfolio')
                ->icon('heroicon-o-building-office-2')
                ->color('warning'),
        ];
    }
}
