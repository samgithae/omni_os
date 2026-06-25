<?php

namespace App\Filament\Resources\MiningTargetResource\Pages;

use App\Filament\Resources\MiningTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMiningTargets extends ListRecords
{
    protected static string $resource = MiningTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
