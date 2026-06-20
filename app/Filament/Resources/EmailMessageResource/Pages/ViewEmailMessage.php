<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailMessage extends ViewRecord
{
    protected static string $resource = EmailMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}