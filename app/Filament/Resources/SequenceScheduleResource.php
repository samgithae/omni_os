<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SequenceScheduleResource\Pages;
use App\Models\SequenceSchedule;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SequenceScheduleResource extends Resource
{
    protected static ?string $model = SequenceSchedule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static \UnitEnum|string|null $navigationGroup = 'Email';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('brand_id')
                    ->relationship('brand', 'name')
                    ->required(),
                Select::make('segment')
                    ->options(['rabbit' => 'Rabbit', 'deer' => 'Deer'])
                    ->required(),
                Select::make('step')
                    ->options([1 => 'Step 1', 2 => 'Step 2', 3 => 'Step 3', 4 => 'Step 4', 5 => 'Step 5'])
                    ->required(),
                TextInput::make('days_after_previous')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->helperText('Days gap after previous step is sent'),
                TextInput::make('purpose')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('segment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'rabbit' => 'success',
                        'deer' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('step')
                    ->label('Step')
                    ->sortable(),
                Tables\Columns\TextInputColumn::make('days_after_previous')
                    ->label('Days gap')
                    ->sortable(),
                Tables\Columns\TextColumn::make('purpose')
                    ->limit(40),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('brand_id')
            ->defaultSort('segment')
            ->defaultSort('step')
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->relationship('brand', 'name'),
                Tables\Filters\SelectFilter::make('segment')
                    ->options(['rabbit' => 'Rabbit', 'deer' => 'Deer']),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSequenceSchedules::route('/'),
            'edit' => Pages\EditSequenceSchedule::route('/{record}/edit'),
        ];
    }
}
