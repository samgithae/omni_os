<?php

namespace App\Filament\Resources\WebhookEvents;

use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Models\WebhookEvent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->sortable(),
                TextColumn::make('event_type')
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
                TextColumn::make('recipient_email')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('lead.company_name')
                    ->label('Lead')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('email_message_id')
                    ->label('Email ID')
                    ->toggleable(),
                IconColumn::make('processed')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('processing_notes')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->options([
                        'open' => 'Open',
                        'click' => 'Click',
                        'bounce' => 'Bounce',
                        'complaint' => 'Complaint',
                        'unsubscribe' => 'Unsubscribe',
                        'reply' => 'Reply',
                        'delivered' => 'Delivered',
                    ]),
                TernaryFilter::make('processed'),
            ])
            ->actions([
                ViewAction::make()
                    ->schema([
                        TextEntry::make('received_at')->dateTime(),
                        TextEntry::make('event_type'),
                        TextEntry::make('recipient_email'),
                        TextEntry::make('lead.company_name'),
                        TextEntry::make('processed')->badge(),
                        TextEntry::make('processing_notes'),
                        TextEntry::make('payload')
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
