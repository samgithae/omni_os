<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandSequenceConfigResource\Pages;
use App\Models\Brand;
use App\Models\BrandSequenceConfig;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Actions;
use Filament\Tables\Table;

class BrandSequenceConfigResource extends Resource
{
    protected static ?string $model = BrandSequenceConfig::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static \UnitEnum|string|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Brand & Segment')
                    ->schema([
                        Forms\Components\Select::make('brand_id')
                            ->label('Brand')
                            ->options(Brand::pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('segment')
                            ->options([
                                'all'      => 'All Segments (fallback)',
                                'rabbit'   => 'Rabbit',
                                'deer'     => 'Deer',
                                'mouse'    => 'Mouse',
                                'elephant' => 'Elephant',
                            ])
                            ->required()
                            ->default('all'),

                        Forms\Components\TextInput::make('sequence_steps')
                            ->label('Number of Emails in Sequence')
                            ->numeric()
                            ->required()
                            ->default(4)
                            ->minValue(1)
                            ->maxValue(10),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),

                Section::make('Generation Prompt / Playbook')
                    ->description('Paste the full instructions Hermes uses to generate emails for this brand + segment. This is the source of truth for email voice, structure, and rules.')
                    ->schema([
                        Forms\Components\Textarea::make('prompt_text')
                            ->label('Prompt / Playbook Text')
                            ->required()
                            ->rows(30)
                            ->columnSpanFull()
                            ->helperText('This text is sent as the system prompt to the LLM. Include all writing rules, email structures, examples, and banned phrases.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->sortable(),
                Tables\Columns\TextColumn::make('segment')
                    ->badge(),
                Tables\Columns\TextColumn::make('sequence_steps')
                    ->label('Steps'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->label('Last Updated')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(Brand::pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('segment')
                    ->options([
                        'all' => 'All',
                        'rabbit' => 'Rabbit',
                        'deer' => 'Deer',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBrandSequenceConfigs::route('/'),
            'create' => Pages\CreateBrandSequenceConfig::route('/create'),
            'edit'   => Pages\EditBrandSequenceConfig::route('/{record}/edit'),
        ];
    }
}
