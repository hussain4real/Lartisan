<?php

namespace App\Filament\Resources\KycSubmissions\Pages;

use App\Filament\Resources\KycSubmissions\KycSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListKycSubmissions extends ListRecords
{
    protected static string $resource = KycSubmissionResource::class;
}
