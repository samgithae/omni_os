<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Models\EmailMessage;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class EmailMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'emailMessages';

    protected static ?string $title = 'Email Sequence';

    protected static string|BackedEnum|null $icon = 'heroicon-o-envelope';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Email Content')
                    ->schema([
                        Forms\Components\TextInput::make('sequence_step')
                            ->label('Sequence Step')
                            ->numeric()
                            ->required()
                            ->helperText('1 = first email, 2 = follow-up, etc.'),
                        Forms\Components\TextInput::make('subject')
                            ->label('Subject Line')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('body')
                            ->label('Email Body')
                            ->rows(12)
                            ->columnSpanFull(),
                    ])->columns(1),

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
                            ->label('Scheduled For')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->label('Sent At')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('opened_at')
                            ->label('Opened At')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('clicked_at')
                            ->label('Clicked At')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sequence_step', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('sequence_step')
                    ->label('Step')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),
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
                    ->label('Send Status')
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
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('approval_status')
                    ->options([
                        'pending' => 'Pending Approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'queued' => 'Queued',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ]),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Add Email')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['brand_id'] = $this->getOwnerRecord()->brand_id;

                        return $data;
                    }),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->modalWidth('4xl')
                    ->schema(fn (EmailMessage $record): array => [
                        Forms\Components\Placeholder::make('subject')
                            ->label('Subject')
                            ->content($record->subject ?? '(no subject)'),
                        Forms\Components\Placeholder::make('body_preview')
                            ->label('Body')
                            ->content(new HtmlString(
                                '<div style="white-space: pre-wrap; font-family: sans-serif; max-height: 400px; overflow-y: auto; padding: 12px; background: #f9fafb; border-radius: 8px;">'.
                                e($record->body ?? '').
                                '</div>'
                            )),
                        Forms\Components\Placeholder::make('approval')
                            ->label('Approval Status')
                            ->content(ucfirst($record->approval_status)),
                        Forms\Components\Placeholder::make('send_status')
                            ->label('Send Status')
                            ->content(ucfirst($record->status)),
                        Forms\Components\Placeholder::make('sent_at')
                            ->label('Sent At')
                            ->content($record->sent_at?->format('M j, Y g:i A') ?? '—'),
                        Forms\Components\Placeholder::make('opened_at')
                            ->label('Opened At')
                            ->content($record->opened_at?->format('M j, Y g:i A') ?? '—'),
                    ]),
                Actions\EditAction::make(),
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve this email?')
                    ->modalDescription(fn (EmailMessage $record): string => "Subject: {$record->subject}\n\nOnce approved, this email will be queued for sending.")
                    ->visible(fn (EmailMessage $record): bool => $record->approval_status === 'pending')
                    ->action(function (EmailMessage $record): void {
                        $record->approve();
                    })
                    ->after(fn () => $this->sendCreatedNotificationAndReceivesDirty()),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (EmailMessage $record): bool => $record->approval_status === 'pending')
                    ->action(function (EmailMessage $record): void {
                        $record->reject();
                    })
                    ->after(fn () => $this->sendCreatedNotificationAndReceivesDirty()),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('approve_all')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(fn (EmailMessage $record) => $record->approve());
                        })
                        ->after(fn () => $this->sendCreatedNotificationAndReceivesDirty()),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
