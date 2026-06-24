<?php

namespace App\Filament\Resources\WebhookEvents;

use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Models\WebhookEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebhookEventResource extends Resource
{
    protected static ?string $model = WebhookEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static \UnitEnum|string|null $navigationGroup = 'Intelligence';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('event_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open', 'opened' => 'info',
                        'click', 'clicked' => 'purple',
                        'bounce', 'bounced', 'hard_bounce' => 'danger',
                        'complaint', 'spam' => 'danger',
                        'unsubscribe' => 'warning',
                        'reply' => 'success',
                        'delivered', 'delivery' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('recipient_email')
                    ->searchable()
                    ->limit(40),
                \Filament\Tables\Columns\TextColumn::make('lead.company_name')
                    ->label('Lead')
                    ->limit(30)
                    ->toggleable(),
                \Filament\Tables\Columns\TextColumn::make('email_message_id')
                    ->label('Email ID')
                    ->toggleable(),
                \Filament\Tables\Columns\IconColumn::make('processed')
                    ->boolean()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('processing_notes')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('event_type')
                    ->options([
                        'open' => 'Open',
                        'click' => 'Click',
                        'bounce' => 'Bounce',
                        'complaint' => 'Complaint',
                        'unsubscribe' => 'Unsubscribe',
                        'reply' => 'Reply',
                        'delivered' => 'Delivered',
                    ]),
                \Filament\Tables\Filters\TernaryFilter::make('processed'),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('received_at')->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('event_type'),
                        \Filament\Infolists\Components\TextEntry::make('recipient_email'),
                        \Filament\Infolists\Components\TextEntry::make('lead.company_name'),
                        \Filament\Infolists\Components\TextEntry::make('processed')->badge(),
                        \Filament\Infolists\Components\TextEntry::make('processing_notes'),
                        \Filament\Infolists\Components\TextEntry::make('payload')
                            ->json()
                            ->columnSpanFull(),
                    ]),
            ])
            ->bulkActions([])
            ->defaultSort('received_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEvents::route('/'),
        ];
    }
}