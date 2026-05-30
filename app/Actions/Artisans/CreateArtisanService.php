<?php

namespace App\Actions\Artisans;

use App\Enums\ArtisanServiceStatus;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\ServiceCategory;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateArtisanService
{
    public function handle(
        ArtisanProfile $profile,
        ServiceCategory $category,
        string $title,
        ?string $description = null,
        ?string $startingPrice = null,
        string $currencyCode = 'NGN',
        ArtisanServiceStatus $status = ArtisanServiceStatus::Draft,
    ): ArtisanService {
        $title = trim($title);
        $currencyCode = Str::upper(trim($currencyCode));

        if ($title === '') {
            throw new InvalidArgumentException('The artisan service title is required.');
        }

        if (! $category->active) {
            throw new InvalidArgumentException('The selected service category is inactive.');
        }

        if (mb_strlen($currencyCode) !== 3) {
            throw new InvalidArgumentException('The currency code must be three characters.');
        }

        $maxSortOrder = $profile->services()->max('sort_order');
        $sortOrder = (is_numeric($maxSortOrder) ? (int) $maxSortOrder : 0) + 1;

        return $profile->services()->create([
            'service_category_id' => $category->id,
            'title' => $title,
            'description' => $this->blankToNull($description),
            'starting_price' => $this->blankToNull($startingPrice),
            'currency_code' => $currencyCode,
            'status' => $status,
            'sort_order' => $sortOrder,
        ]);
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
