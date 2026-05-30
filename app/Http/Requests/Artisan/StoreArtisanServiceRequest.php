<?php

namespace App\Http\Requests\Artisan;

use App\Enums\ArtisanServiceStatus;
use App\Models\ServiceCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArtisanServiceRequest extends FormRequest
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
            'service_category_id' => [
                'required',
                'integer',
                Rule::exists((new ServiceCategory)->getTable(), 'id')->where('active', true),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'starting_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'currency_code' => ['required', 'string', 'size:3'],
            'status' => ['required', Rule::enum(ArtisanServiceStatus::class)],
        ];
    }

    public function category(): ServiceCategory
    {
        return ServiceCategory::query()->findOrFail($this->integer('service_category_id'));
    }

    public function title(): string
    {
        return $this->string('title')->trim()->toString();
    }

    public function description(): ?string
    {
        return $this->nullableString('description');
    }

    public function startingPrice(): ?string
    {
        return $this->filled('starting_price') ? $this->string('starting_price')->toString() : null;
    }

    public function currencyCode(): string
    {
        return $this->string('currency_code')->trim()->toString();
    }

    public function serviceStatus(): ArtisanServiceStatus
    {
        return ArtisanServiceStatus::from($this->string('status')->toString());
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }
}
