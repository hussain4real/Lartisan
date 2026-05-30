<?php

namespace App\Http\Requests\Artisan;

use App\Enums\ArtisanAvailabilityStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArtisanProfileRequest extends FormRequest
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
            'business_name' => ['required', 'string', 'max:255'],
            'public_summary' => ['nullable', 'string', 'max:1000'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'service_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'public_phone' => ['nullable', 'string', 'max:32'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'availability_status' => ['required', Rule::enum(ArtisanAvailabilityStatus::class)],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    public function businessName(): string
    {
        return $this->string('business_name')->trim()->toString();
    }

    public function publicSummary(): ?string
    {
        return $this->nullableString('public_summary');
    }

    public function yearsExperience(): ?int
    {
        return $this->nullableInteger('years_experience');
    }

    public function serviceRadiusKm(): ?int
    {
        return $this->nullableInteger('service_radius_km');
    }

    public function publicPhone(): ?string
    {
        return $this->nullableString('public_phone');
    }

    public function publicEmail(): ?string
    {
        return $this->nullableString('public_email');
    }

    public function availabilityStatus(): ArtisanAvailabilityStatus
    {
        return ArtisanAvailabilityStatus::from($this->string('availability_status')->toString());
    }

    public function isPublic(): bool
    {
        return $this->boolean('is_public');
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }

    private function nullableInteger(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
