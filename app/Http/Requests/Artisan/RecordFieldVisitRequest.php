<?php

namespace App\Http\Requests\Artisan;

use App\Enums\FieldVisitStatus;
use App\Models\KycSubmission;
use App\Models\Territory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordFieldVisitRequest extends FormRequest
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
            'kyc_submission_id' => ['nullable', 'integer', Rule::exists((new KycSubmission)->getTable(), 'id')],
            'territory_id' => ['nullable', 'integer', Rule::exists((new Territory)->getTable(), 'id')->where('active', true)],
            'status' => ['required', Rule::enum(FieldVisitStatus::class)],
            'visited_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
        ];
    }

    public function kycSubmission(): ?KycSubmission
    {
        $submissionId = $this->integer('kyc_submission_id');

        return $submissionId > 0 ? KycSubmission::query()->findOrFail($submissionId) : null;
    }

    public function territory(): ?Territory
    {
        $territoryId = $this->integer('territory_id');

        return $territoryId > 0 ? Territory::query()->findOrFail($territoryId) : null;
    }

    public function visitStatus(): FieldVisitStatus
    {
        return FieldVisitStatus::from($this->string('status')->toString());
    }

    public function visitedAt(): ?string
    {
        return $this->filled('visited_at') ? $this->string('visited_at')->toString() : null;
    }

    public function latitude(): ?string
    {
        return $this->filled('latitude') ? $this->string('latitude')->toString() : null;
    }

    public function longitude(): ?string
    {
        return $this->filled('longitude') ? $this->string('longitude')->toString() : null;
    }

    public function notes(): ?string
    {
        $notes = $this->string('notes')->trim()->toString();

        return $notes === '' ? null : $notes;
    }

    /**
     * @return array<string, bool>|null
     */
    public function checklist(): ?array
    {
        $checklist = $this->input('checklist');

        if (! is_array($checklist)) {
            return null;
        }

        return collect($checklist)
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [(string) $key => (bool) $value])
            ->all();
    }
}
