<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                \App\Filament\Widgets\LeadStatsOverview::class,
                \App\Filament\Widgets\LeadsByBrandChart::class,
                \App\Filament\Widgets\LeadsBySegmentChart::class,
                \App\Filament\Widgets\LeadsByCityChart::class,
            ])
            ->navigationItems([
                NavigationItem::make('Dashboard')
                    ->url('/dashboard', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-home')
                    ->group('Overview')
                    ->sort(1),
                NavigationItem::make('Leads')
                    ->url('/admin/leads', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-user-group')
                    ->group('CRM')
                    ->sort(1),
                NavigationItem::make('Activity Feed')
                    ->url('/activity', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-arrow-trending-up')
                    ->group('Analytics')
                    ->sort(1),
                NavigationItem::make('Brands')
                    ->url('/admin/brands', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-building-office-2')
                    ->group('Configuration')
                    ->sort(1),
                NavigationItem::make('Email Sequences')
                    ->url('/email-sequences', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-envelope')
                    ->group('Email')
                    ->sort(1),
                NavigationItem::make('Sequence Schedules')
                    ->url('/admin/sequence-schedules', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-clock')
                    ->group('Email')
                    ->sort(2),
                NavigationItem::make('Suppressions')
                    ->url('/admin/suppressions', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-no-symbol')
                    ->group('Email')
                    ->sort(3),
                NavigationItem::make('Mining Targets')
                    ->url('/admin/mining-targets', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-map-pin')
                    ->group('Email')
                    ->sort(4),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
