<?php

namespace App\Filament\Resources\BrandSequenceConfigResource\Pages;

use App\Filament\Resources\BrandSequenceConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBrandSequenceConfig extends EditRecord
{
    protected static string $resource = BrandSequenceConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
