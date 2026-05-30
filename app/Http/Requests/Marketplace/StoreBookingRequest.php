<?php

namespace App\Http\Requests\Marketplace;

use App\Models\ArtisanService;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StoreBookingRequest extends FormRequest
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
            'artisan_service_id' => ['required', 'integer', Rule::exists((new ArtisanService)->getTable(), 'id')],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
            'line_1' => ['required', 'string', 'max:255'],
            'line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer', Rule::exists((new Country)->getTable(), 'id')],
            'state_id' => ['nullable', 'integer', Rule::exists((new State)->getTable(), 'id')],
            'local_government_id' => ['nullable', 'integer', Rule::exists((new LocalGovernment)->getTable(), 'id')],
            'territory_id' => ['nullable', 'integer', Rule::exists((new Territory)->getTable(), 'id')],
            'attachments' => ['nullable', 'array', 'max:4'],
            'attachments.*' => [
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max(5120),
            ],
        ];
    }

    public function service(): ArtisanService
    {
        return ArtisanService::query()->findOrFail($this->integer('artisan_service_id'));
    }

    public function customerName(): string
    {
        return $this->string('customer_name')->trim()->toString();
    }

    public function customerPhone(): string
    {
        return $this->string('customer_phone')->trim()->toString();
    }

    public function customerEmail(): ?string
    {
        return $this->nullableString('customer_email');
    }

    public function scheduledAt(): ?CarbonInterface
    {
        return $this->filled('scheduled_at')
            ? CarbonImmutable::parse($this->string('scheduled_at')->toString())
            : null;
    }

    public function description(): ?string
    {
        return $this->nullableString('description');
    }

    /**
     * @return array<string, mixed>
     */
    public function addressSnapshot(): array
    {
        return [
            'line_1' => $this->string('line_1')->trim()->toString(),
            'line_2' => $this->nullableString('line_2'),
            'landmark' => $this->nullableString('landmark'),
            'country_id' => $this->filled('country_id') ? $this->integer('country_id') : null,
            'state_id' => $this->filled('state_id') ? $this->integer('state_id') : null,
            'local_government_id' => $this->filled('local_government_id') ? $this->integer('local_government_id') : null,
            'territory_id' => $this->filled('territory_id') ? $this->integer('territory_id') : null,
        ];
    }

    /**
     * @return array<int, UploadedFile|string>
     */
    public function attachments(): array
    {
        $files = $this->file('attachments', []);

        return is_array($files) ? array_values($files) : [];
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }
}
