<?php

namespace App\Filament\Resources\AgentResource\RelationManagers;

use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Attached Documents';

    protected static string|BackedEnum|null $icon = 'heroicon-o-document-text';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Document')
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->label('Document Label')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('file_path')
                            ->label('File')
                            ->directory('agents/documents')
                            ->disk('public')
                            ->visibility('public')
                            ->preserveFilenames()
                            ->afterStateUpdated(function (callable $set, $state, $record): void {
                                if ($state) {
                                    $path = $state instanceof TemporaryUploadedFile
                                        ? $state->store('agents/documents', 'public')
                                        : $state;
                                    $fullPath = storage_path("app/public/{$path}");
                                    if (file_exists($fullPath)) {
                                        $set('mime_type', mime_content_type($fullPath) ?: null);
                                        $set('size_bytes', filesize($fullPath) ?: null);
                                    }
                                }
                            })
                            ->required(),
                        Forms\Components\TextInput::make('mime_type')
                            ->label('MIME Type')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('size_bytes')
                            ->label('Size (bytes)')
                            ->disabled()
                            ->dehydrated(),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Document')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_path')
                    ->label('File')
                    ->limit(40)
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state
                        ? number_format($state / 1024, 1).' KB'
                        : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Upload Document'),
            ])
            ->actions([
                Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn ($record): string => $record->url)
                    ->openUrlInNewTab(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
