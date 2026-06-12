<?php

namespace App\Http\Requests;

use App\Enums\WaitlistAudienceType;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Territory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreWaitlistEntryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'audience_type' => ['required', new Enum(WaitlistAudienceType::class)],
            'business_name' => ['nullable', 'string', 'max:255'],
            'service_category_id' => ['nullable', 'integer', Rule::exists((new ServiceCategory)->getTable(), 'id')->where('active', true)],
            'country_id' => ['required', 'integer', Rule::exists((new Country)->getTable(), 'id')->where('active', true)],
            'state_id' => ['required', 'integer', Rule::exists((new State)->getTable(), 'id')->where('active', true)],
            'local_government_id' => ['required', 'integer', Rule::exists((new LocalGovernment)->getTable(), 'id')->where('active', true)],
            'territory_id' => ['nullable', 'integer', Rule::exists((new Territory)->getTable(), 'id')->where('active', true)],
            'note' => ['nullable', 'string', 'max:1000'],
            'contact_consent' => ['accepted'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $country = Country::query()->find($this->integer('country_id'));
                $state = State::query()->find($this->integer('state_id'));
                $localGovernment = LocalGovernment::query()->find($this->integer('local_government_id'));
                $territory = Territory::query()->find($this->integer('territory_id'));

                if ($country instanceof Country && $state instanceof State && $state->country_id !== $country->id) {
                    $validator->errors()->add('state_id', 'The selected state does not belong to the selected country.');
                }

                if ($state instanceof State && $localGovernment instanceof LocalGovernment && $localGovernment->state_id !== $state->id) {
                    $validator->errors()->add('local_government_id', 'The selected local government does not belong to the selected state.');
                }

                if ($localGovernment instanceof LocalGovernment && $territory instanceof Territory && $territory->local_government_id !== $localGovernment->id) {
                    $validator->errors()->add('territory_id', 'The selected territory does not belong to the selected local government.');
                }
            },
        ];
    }

    public function entryName(): string
    {
        return $this->string('name')->trim()->toString();
    }

    public function email(): string
    {
        return Str::lower($this->string('email')->trim()->toString());
    }

    public function phone(): string
    {
        return $this->string('phone')->trim()->toString();
    }

    public function audienceType(): WaitlistAudienceType
    {
        return WaitlistAudienceType::from($this->string('audience_type')->toString());
    }

    public function businessName(): ?string
    {
        return $this->nullableString('business_name');
    }

    public function note(): ?string
    {
        return $this->nullableString('note');
    }

    public function serviceCategory(): ?ServiceCategory
    {
        $serviceCategoryId = $this->integer('service_category_id');

        return $serviceCategoryId > 0 ? ServiceCategory::query()->findOrFail($serviceCategoryId) : null;
    }

    public function country(): Country
    {
        return Country::query()->findOrFail($this->integer('country_id'));
    }

    public function state(): State
    {
        return State::query()->findOrFail($this->integer('state_id'));
    }

    public function localGovernment(): LocalGovernment
    {
        return LocalGovernment::query()->findOrFail($this->integer('local_government_id'));
    }

    public function territory(): ?Territory
    {
        $territoryId = $this->integer('territory_id');

        return $territoryId > 0 ? Territory::query()->findOrFail($territoryId) : null;
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value !== '' ? $value : null;
    }
}
