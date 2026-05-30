<?php

namespace App\Actions\Setup;

use App\Enums\TerritoryType;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use Illuminate\Support\Str;

class SeedGeography
{
    /**
     * Seed Nigeria with all states and a pilot FCT operating structure.
     */
    public function handle(): void
    {
        $country = Country::query()->updateOrCreate(
            ['iso_code' => 'NG'],
            [
                'name' => 'Nigeria',
                'currency_code' => 'NGN',
                'phone_country_code' => '+234',
                'active' => true,
            ],
        );

        foreach ($this->states() as $stateName) {
            State::query()->updateOrCreate(
                [
                    'country_id' => $country->id,
                    'slug' => Str::slug($stateName),
                ],
                [
                    'name' => $stateName,
                    'active' => true,
                ],
            );
        }

        $fct = State::query()
            ->where('country_id', $country->id)
            ->where('slug', 'federal-capital-territory')
            ->firstOrFail();

        foreach ($this->fctLocalGovernments() as $localGovernmentName => $territories) {
            $localGovernment = LocalGovernment::query()->updateOrCreate(
                [
                    'state_id' => $fct->id,
                    'slug' => Str::slug($localGovernmentName),
                ],
                [
                    'name' => $localGovernmentName,
                    'active' => true,
                ],
            );

            foreach ($territories as $territory) {
                Territory::query()->updateOrCreate(
                    [
                        'local_government_id' => $localGovernment->id,
                        'type' => $territory['type']->value,
                        'slug' => Str::slug($territory['name']),
                    ],
                    [
                        'name' => $territory['name'],
                        'boundaries' => null,
                        'active' => true,
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function states(): array
    {
        return [
            'Abia',
            'Adamawa',
            'Akwa Ibom',
            'Anambra',
            'Bauchi',
            'Bayelsa',
            'Benue',
            'Borno',
            'Cross River',
            'Delta',
            'Ebonyi',
            'Edo',
            'Ekiti',
            'Enugu',
            'Federal Capital Territory',
            'Gombe',
            'Imo',
            'Jigawa',
            'Kaduna',
            'Kano',
            'Katsina',
            'Kebbi',
            'Kogi',
            'Kwara',
            'Lagos',
            'Nasarawa',
            'Niger',
            'Ogun',
            'Ondo',
            'Osun',
            'Oyo',
            'Plateau',
            'Rivers',
            'Sokoto',
            'Taraba',
            'Yobe',
            'Zamfara',
        ];
    }

    /**
     * @return array<string, array<int, array{name: string, type: TerritoryType}>>
     */
    private function fctLocalGovernments(): array
    {
        return [
            'Abaji' => [
                ['name' => 'Abaji Central Ward', 'type' => TerritoryType::Ward],
                ['name' => 'Abaji Market', 'type' => TerritoryType::Market],
            ],
            'Abuja Municipal Area Council' => [
                ['name' => 'Garki Market', 'type' => TerritoryType::Market],
                ['name' => 'Gwarinpa Estate', 'type' => TerritoryType::Estate],
                ['name' => 'Jabi Community', 'type' => TerritoryType::Community],
                ['name' => 'Wuse Market', 'type' => TerritoryType::Market],
            ],
            'Bwari' => [
                ['name' => 'Bwari Central Ward', 'type' => TerritoryType::Ward],
                ['name' => 'Dutse Alhaji Community', 'type' => TerritoryType::Community],
                ['name' => 'Kubwa Trade Cluster', 'type' => TerritoryType::Cluster],
            ],
            'Gwagwalada' => [
                ['name' => 'Gwagwalada Market', 'type' => TerritoryType::Market],
                ['name' => 'Zuba Community', 'type' => TerritoryType::Community],
            ],
            'Kuje' => [
                ['name' => 'Kuje Market', 'type' => TerritoryType::Market],
                ['name' => 'Kuje Urban Ward', 'type' => TerritoryType::Ward],
            ],
            'Kwali' => [
                ['name' => 'Dafa Community', 'type' => TerritoryType::Community],
                ['name' => 'Kwali Market', 'type' => TerritoryType::Market],
            ],
        ];
    }
}
