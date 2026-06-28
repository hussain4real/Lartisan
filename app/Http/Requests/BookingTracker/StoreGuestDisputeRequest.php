<?php

namespace App\Http\Requests\BookingTracker;

use App\Enums\DisputeSeverity;
use App\Enums\DisputeTargetType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreGuestDisputeRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'severity' => ['required', Rule::enum(DisputeSeverity::class)],
            'target' => ['nullable', Rule::enum(DisputeTargetType::class)],
            'review_id' => ['nullable', 'integer', Rule::exists('reviews', 'id')],
            'payment_id' => ['nullable', 'integer', Rule::exists('payments', 'id')],
            'evidence' => ['nullable', 'array', 'max:4'],
            'evidence.*' => [
                'file',
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp'])->max(8 * 1024),
            ],
        ];
    }

    public function subject(): string
    {
        return $this->string('subject')->trim()->toString();
    }

    public function description(): ?string
    {
        $description = $this->string('description')->trim()->toString();

        return $description === '' ? null : $description;
    }

    public function severity(): DisputeSeverity
    {
        return DisputeSeverity::from($this->string('severity')->toString());
    }

    public function target(): DisputeTargetType
    {
        $target = $this->string('target')->toString();

        return $target === '' ? DisputeTargetType::Booking : DisputeTargetType::from($target);
    }

    public function paymentId(): ?int
    {
        $paymentId = $this->integer('payment_id');

        return $paymentId === 0 ? null : $paymentId;
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function evidence(): array
    {
        $uploadedEvidence = $this->file('evidence', []);

        if ($uploadedEvidence instanceof UploadedFile) {
            return [$uploadedEvidence];
        }

        return array_values($uploadedEvidence);
    }
}
