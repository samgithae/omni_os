<?php

namespace App\Filament\Resources;

use BackedEnum;

use App\Filament\Resources\LeadResource\Pages;
use App\Models\Brand;
use App\Models\Lead;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Actions;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Lead Details')
                    ->schema([
                        Forms\Components\Select::make('brand_id')
                            ->relationship('brand', 'name')
                            ->required()
                            ->searchable(),
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
                            ->options([
                                'new' => 'New',
                                'enriching' => 'Enriching',
                                'enriched' => 'Enriched',
                                'no_email_found' => 'No Email Found',
                            ])
                            ->required()
                            ->default('new'),
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
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'info',
                        'enriching' => 'warning',
                        'enriched' => 'success',
                        'no_email_found' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->sortable()
                    ->toggleable(),
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
                    ->options([
                        'new' => 'New',
                        'enriching' => 'Enriching',
                        'enriched' => 'Enriched',
                        'no_email_found' => 'No Email Found',
                    ]),
                Tables\Filters\SelectFilter::make('country')
                    ->options(fn () => Lead::whereNotNull('country')->distinct()->pluck('country', 'country')->filter()->toArray()),
                Tables\Filters\SelectFilter::make('city')
                    ->options(fn () => Lead::whereNotNull('city')->distinct()->pluck('city', 'city')->filter()->toArray()),
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
            \App\Filament\Resources\LeadResource\RelationManagers\EmailMessagesRelationManager::class,
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