<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailMessageResource\Pages;
use App\Models\EmailMessage;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EmailMessageResource extends Resource
{
    protected static ?string $model = EmailMessage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Email';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $navigationLabel = 'Email Messages';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Email Details')
                    ->schema([
                        Forms\Components\Select::make('brand_id')
                            ->relationship('brand', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('lead_id')
                            ->relationship('lead', 'company_name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('sequence_step')
                            ->label('Sequence Step')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->helperText('1 = first email, 2 = follow-up, 3-5 = later in drip'),
                        Forms\Components\TextInput::make('subject')
                            ->label('Subject Line')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('body')
                            ->label('Email Body')
                            ->rows(12)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Approval & Status')
                    ->schema([
                        Forms\Components\Select::make('approval_status')
                            ->options([
                                'pending' => 'Pending Approval',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->default('pending'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'queued' => 'Queued',
                                'sent' => 'Sent',
                                'failed' => 'Failed',
                            ])
                            ->required()
                            ->default('draft'),
                        Forms\Components\Textarea::make('approval_notes')
                            ->label('Approval Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('scheduled_for')
                            ->label('Scheduled For'),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->label('Sent At')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('opened_at')
                            ->label('Opened At')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('clicked_at')
                            ->label('Clicked At')
                            ->disabled(),
                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lead.company_name')
                    ->label('Lead')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sequence_step')
                    ->label('Step')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(50)
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'queued' => 'info',
                        'sent' => 'success',
                        'failed' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('clicked_at')
                    ->label('Clicked')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand')
                    ->relationship('brand', 'name'),
                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Approval Status')
                    ->options([
                        'pending' => 'Pending Approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Send Status')
                    ->options([
                        'draft' => 'Draft',
                        'queued' => 'Queued',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('sequence_step')
                    ->label('Sequence Step')
                    ->options([
                        1 => 'Step 1',
                        2 => 'Step 2',
                        3 => 'Step 3',
                        4 => 'Step 4',
                        5 => 'Step 5',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->modalWidth('4xl'),
                Actions\EditAction::make(),
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve this email?')
                    ->visible(fn (EmailMessage $record): bool => $record->approval_status === 'pending')
                    ->action(fn (EmailMessage $record) => $record->approve()),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (EmailMessage $record): bool => $record->approval_status === 'pending')
                    ->action(fn (EmailMessage $record) => $record->reject()),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('approve_all')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $records->each(fn (EmailMessage $record) => $record->approve());
                        }),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sequence_step', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailMessages::route('/'),
            'create' => Pages\CreateEmailMessage::route('/create'),
            'view' => Pages\ViewEmailMessage::route('/{record}'),
            'edit' => Pages\EditEmailMessage::route('/{record}/edit'),
        ];
    }
}