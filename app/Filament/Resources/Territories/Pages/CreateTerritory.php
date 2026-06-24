<?php

namespace App\Filament\Resources\Territories\Pages;

use App\Filament\Resources\Territories\TerritoryResource;
use App\Models\LocalGovernment;
use App\Models\Territory;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;

class CreateTerritory extends CreateRecord
{
    protected static string $resource = TerritoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Gate::authorize('createForLocalGovernment', [
            Territory::class,
            LocalGovernment::query()->findOrFail($data['local_government_id']),
        ]);

        return $data;
    }
}
