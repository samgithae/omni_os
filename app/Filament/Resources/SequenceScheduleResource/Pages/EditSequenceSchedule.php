<?php

namespace App\Filament\Resources\SequenceScheduleResource\Pages;

use App\Filament\Resources\SequenceScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSequenceSchedule extends EditRecord
{
    protected static string $resource = SequenceScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
