<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentResource\Pages;
use App\Models\Agent;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Agent Identity')
                    ->schema([
                        Forms\Components\TextInput::make('codename')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->helperText('Machine handle — lowercase, underscores. Example: the_professor'),
                        Forms\Components\TextInput::make('display_name')
                            ->required()
                            ->maxLength(255)
                            ->label('Display Name'),
                        Forms\Components\TextInput::make('role')
                            ->maxLength(255)
                            ->placeholder('e.g. Control tower / orchestration'),
                        Forms\Components\Select::make('function_area')
                            ->options([
                                'orchestration' => 'Orchestration',
                                'mining' => 'Mining',
                                'enrichment' => 'Enrichment',
                                'drafting' => 'Drafting',
                                'triage' => 'Triage',
                                'research' => 'Research',
                            ])
                            ->placeholder('Select function area'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('avatar_path')
                            ->label('Avatar')
                            ->image()
                            ->imageEditor()
                            ->directory('agents/avatars')
                            ->disk('public')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Status & Ordering')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'maintenance' => 'Maintenance',
                            ])
                            ->required()
                            ->default('active'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Enabled')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])->columns(3),

                Section::make('Token')
                    ->schema([
                        Forms\Components\Placeholder::make('token_last_four')
                            ->label('Token (last 4 chars)')
                            ->content(fn (?Agent $record): string => $record?->token_last_four
                                ? '…'.$record->token_last_four
                                : 'No token generated yet'),
                        Forms\Components\Placeholder::make('last_active_at')
                            ->label('Last Active')
                            ->content(fn (?Agent $record): string => $record?->last_active_at
                                ? $record->last_active_at->diffForHumans()
                                : 'Never'),
                    ])->columns(2)
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn (Agent $record): string => self::initialsAvatar($record)),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('codename')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('function_area')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'orchestration' => 'primary',
                        'mining' => 'warning',
                        'enrichment' => 'info',
                        'drafting' => 'success',
                        'triage' => 'gray',
                        'research' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'paused' => 'gray',
                        'maintenance' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Enabled'),
                Tables\Columns\TextColumn::make('last_active_at')
                    ->label('Last Active')
                    ->since()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('actions_this_week')
                    ->label('This Week')
                    ->getStateUsing(fn (Agent $record): int => $record->actionsThisWeek())
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('function_area')
                    ->options([
                        'orchestration' => 'Orchestration',
                        'mining' => 'Mining',
                        'enrichment' => 'Enrichment',
                        'drafting' => 'Drafting',
                        'triage' => 'Triage',
                        'research' => 'Research',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'maintenance' => 'Maintenance',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Enabled'),
            ])
            ->actions([
                Actions\Action::make('generate_token')
                    ->label(fn (Agent $record): string => $record->token_hash ? 'Regenerate Token' : 'Generate Token')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Agent $record): string => $record->token_hash
                        ? 'Regenerate Token?'
                        : 'Generate Token')
                    ->modalDescription(fn (Agent $record): string => $record->token_hash
                        ? 'The current token will be invalidated immediately.'
                        : 'Generate a new bearer token for this agent.')
                    ->action(function (Agent $record): void {
                        $plain = $record->generateToken();
                        Notification::make()
                            ->title('Token Generated')
                            ->body("**Store this now — it will not be shown again:**\n\n`{$plain}`")
                            ->warning()
                            ->persistent()
                            ->send();
                    }),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AgentResource\RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }

    private static function initialsAvatar(Agent $record): string
    {
        $initials = collect(explode(' ', $record->display_name))
            ->map(fn (string $segment): string => mb_substr($segment, 0, 1))
            ->take(2)
            ->implode('');

        $colors = ['2563eb', '059669', '7c3aed', 'dc2626', 'd97706', '0891b2'];
        $color = $colors[crc32($record->codename) % count($colors)];

        return "https://ui-avatars.com/api/?name={$initials}&background={$color}&color=fff&size=40&font-size=0.4";
    }
}
