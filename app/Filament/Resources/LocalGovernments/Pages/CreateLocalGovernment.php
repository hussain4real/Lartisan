<?php

namespace App\Filament\Resources\LocalGovernments\Pages;

use App\Filament\Resources\LocalGovernments\LocalGovernmentResource;
use App\Models\LocalGovernment;
use App\Models\State;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;

class CreateLocalGovernment extends CreateRecord
{
    protected static string $resource = LocalGovernmentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Gate::authorize('createForState', [
            LocalGovernment::class,
            State::query()->findOrFail($data['state_id']),
        ]);

        return $data;
    }
}
