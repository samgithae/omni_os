<?php

namespace App\Filament\Resources\MiningTargetResource\Pages;

use App\Filament\Resources\MiningTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMiningTarget extends EditRecord
{
    protected static string $resource = MiningTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}