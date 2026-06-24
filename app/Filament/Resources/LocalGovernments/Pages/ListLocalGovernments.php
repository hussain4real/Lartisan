<?php

namespace App\Filament\Resources\LocalGovernments\Pages;

use App\Filament\Resources\LocalGovernments\LocalGovernmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocalGovernments extends ListRecords
{
    protected static string $resource = LocalGovernmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
