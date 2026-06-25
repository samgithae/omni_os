<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuppressionResource\Pages;
use App\Models\Suppression;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SuppressionResource extends Resource
{
    protected static ?string $model = Suppression::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static \UnitEnum|string|null $navigationGroup = 'Campaigns';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Suppression')
                    ->schema([
                        Forms\Components\Select::make('brand_id')
                            ->relationship('brand', 'name')
                            ->required()
                            ->searchable()
                            ->hidden(fn () => filled(session('active_brand_id')))
                            ->required(fn () => blank(session('active_brand_id'))),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('reason')
                            ->options([
                                'unsubscribe' => 'Unsubscribe',
                                'hard_bounce' => 'Hard Bounce',
                                'complaint' => 'Complaint',
                                'manual' => 'Manual',
                            ])
                            ->required()
                            ->default('unsubscribe'),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ])->columns(2),
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
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unsubscribe' => 'warning',
                        'hard_bounce' => 'danger',
                        'complaint' => 'danger',
                        'manual' => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand')
                    ->relationship('brand', 'name'),
                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'unsubscribe' => 'Unsubscribe',
                        'hard_bounce' => 'Hard Bounce',
                        'complaint' => 'Complaint',
                        'manual' => 'Manual',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppressions::route('/'),
            'create' => Pages\CreateSuppression::route('/create'),
            'edit' => Pages\EditSuppression::route('/{record}/edit'),
        ];
    }
}
