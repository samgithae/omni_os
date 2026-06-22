<?php

namespace App\Filament\Resources\BrandSequenceConfigResource\Pages;

use App\Filament\Resources\BrandSequenceConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBrandSequenceConfigs extends ListRecords
{
    protected static string $resource = BrandSequenceConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
