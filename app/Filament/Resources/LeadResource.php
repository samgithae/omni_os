<?php

namespace App\Filament\Resources;

use App\Enums\LeadStatus;
use App\Filament\Resources\LeadResource\Pages;
use App\Filament\Resources\LeadResource\RelationManagers\EmailMessagesRelationManager;
use App\Models\Lead;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static \UnitEnum|string|null $navigationGroup = 'Pipeline';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Lead Details')
                    ->schema([
                        Forms\Components\Select::make('brand_id')
                            ->relationship('brand', 'name')
                            ->required()
                            ->searchable()
                            ->hidden(fn () => filled(session('active_brand_id')))
                            ->required(fn () => blank(session('active_brand_id'))),
                        Forms\Components\TextInput::make('company_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Segmentation & Geography')
                    ->schema([
                        Forms\Components\Select::make('segment')
                            ->options([
                                'rabbit' => 'Rabbit (~$100/mo)',
                                'deer' => 'Deer (~$1k/mo)',
                                'mouse' => 'Mouse (~$10/mo)',
                                'elephant' => 'Elephant (~$8k+/mo)',
                            ])
                            ->required()
                            ->default('rabbit'),
                        Forms\Components\TextInput::make('category')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('subcategory')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('country')
                            ->required()
                            ->default('Kenya'),
                        Forms\Components\TextInput::make('city')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('address')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Status & Enrichment')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(Lead::statusOptions())
                            ->required()
                            ->default(LeadStatus::New->value),
                        Forms\Components\TextInput::make('enrichment_attempts')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('email_verified'),
                        Forms\Components\TextInput::make('score')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Section::make('Source')
                    ->schema([
                        Forms\Components\TextInput::make('source')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('source_url')
                            ->url()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Raw Data')
                    ->schema([
                        Forms\Components\Textarea::make('raw_data')
                            ->rows(5)
                            ->json()
                            ->disabled(),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('company_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contact_name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('segment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'rabbit' => 'success',
                        'deer' => 'warning',
                        'mouse' => 'gray',
                        'elephant' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('country')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LeadStatus::fromValue($state)->label())
                    ->color(fn (string $state): string => LeadStatus::fromValue($state)->filamentColor())
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state >= 80 => 'danger',
                        $state >= 60 => 'warning',
                        $state >= 40 => 'gray',
                        $state >= 20 => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('email_verified')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand')
                    ->relationship('brand', 'name'),
                Tables\Filters\SelectFilter::make('segment')
                    ->options([
                        'rabbit' => 'Rabbit',
                        'deer' => 'Deer',
                        'mouse' => 'Mouse',
                        'elephant' => 'Elephant',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options(Lead::statusOptions()),
                Tables\Filters\SelectFilter::make('country')
                    ->options(fn () => Lead::query()->select('country')->distinct()->orderBy('country', 'asc')->pluck('country', 'country')->filter()->toArray()),
                Tables\Filters\SelectFilter::make('city')
                    ->options(fn () => Lead::query()->select('city')->distinct()->orderBy('city', 'asc')->pluck('city', 'city')->filter()->toArray()),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            EmailMessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
