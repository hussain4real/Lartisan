<?php

namespace App\Http\Requests\Marketplace;

use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Territory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchArtisansRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:255'],
            'service_category_id' => [
                'nullable',
                'integer',
                Rule::exists((new ServiceCategory)->getTable(), 'id')->where('active', true),
            ],
            'state_id' => [
                'nullable',
                'integer',
                Rule::exists((new State)->getTable(), 'id')->where('active', true),
            ],
            'local_government_id' => [
                'nullable',
                'integer',
                Rule::exists((new LocalGovernment)->getTable(), 'id')->where('active', true),
            ],
            'territory_id' => [
                'nullable',
                'integer',
                Rule::exists((new Territory)->getTable(), 'id')->where('active', true),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function queryText(): ?string
    {
        $query = $this->string('query')->trim()->toString();

        return $query === '' ? null : $query;
    }

    public function category(): ?ServiceCategory
    {
        return $this->filled('service_category_id')
            ? ServiceCategory::query()->findOrFail($this->integer('service_category_id'))
            : null;
    }

    public function state(): ?State
    {
        return $this->filled('state_id')
            ? State::query()->findOrFail($this->integer('state_id'))
            : null;
    }

    public function localGovernment(): ?LocalGovernment
    {
        if (! $this->filled('local_government_id')) {
            return null;
        }

        $localGovernment = LocalGovernment::query()->findOrFail($this->integer('local_government_id'));
        $state = $this->state();

        if ($state instanceof State && $localGovernment->state_id !== $state->id) {
            return null;
        }

        return $localGovernment;
    }

    public function territory(): ?Territory
    {
        if (! $this->filled('territory_id')) {
            return null;
        }

        $territory = Territory::query()
            ->with('localGovernment')
            ->findOrFail($this->integer('territory_id'));
        $localGovernment = $this->localGovernment();

        if ($localGovernment instanceof LocalGovernment) {
            return $territory->local_government_id === $localGovernment->id ? $territory : null;
        }

        $state = $this->state();

        if ($state instanceof State && $territory->localGovernment?->state_id !== $state->id) {
            return null;
        }

        return $territory;
    }

    public function page(): int
    {
        return max(1, $this->integer('page', 1));
    }
}
