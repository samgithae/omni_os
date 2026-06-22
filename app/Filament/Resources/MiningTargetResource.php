<?php

namespace App\Filament\Resources;

use BackedEnum;

use App\Filament\Resources\MiningTargetResource\Pages;
use App\Models\MiningTarget;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Actions;
use Filament\Tables\Table;

class MiningTargetResource extends Resource
{
    protected static ?string $model = MiningTarget::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static \UnitEnum|string|null $navigationGroup = 'Email';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Mining Target')
                    ->schema([
                        Forms\Components\Select::make('brand_id')
                            ->relationship('brand', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('country')
                            ->required()
                            ->default('Kenya'),
                        Forms\Components\TextInput::make('city')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('category')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('search_template')
                            ->maxLength(255)
                            ->placeholder('{category} in {city}'),
                        Forms\Components\Select::make('segment')
                            ->options([
                                'rabbit' => 'Rabbit',
                                'deer' => 'Deer',
                                'mouse' => 'Mouse',
                                'elephant' => 'Elephant',
                            ])
                            ->required()
                            ->default('rabbit'),
                        Forms\Components\Select::make('cadence')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                            ])
                            ->required()
                            ->default('weekly'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->sortable(),
                Tables\Columns\TextColumn::make('country')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('segment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'rabbit' => 'success',
                        'deer' => 'warning',
                        'mouse' => 'gray',
                        'elephant' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('cadence')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_mined_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand')
                    ->relationship('brand', 'name'),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('segment'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMiningTargets::route('/'),
            'create' => Pages\CreateMiningTarget::route('/create'),
            'edit' => Pages\EditMiningTarget::route('/{record}/edit'),
        ];
    }
}