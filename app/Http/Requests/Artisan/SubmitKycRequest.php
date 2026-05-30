<?php

namespace App\Http\Requests\Artisan;

use App\Models\KycSubmission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class SubmitKycRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
            KycSubmission::GOVERNMENT_ID_COLLECTION => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            KycSubmission::SELF_PORTRAIT_COLLECTION => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            KycSubmission::ADDRESS_EVIDENCE_COLLECTION => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            KycSubmission::BUSINESS_REGISTRATION_COLLECTION => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function notes(): ?string
    {
        $notes = $this->string('notes')->trim()->toString();

        return $notes === '' ? null : $notes;
    }

    /**
     * @return array<string, UploadedFile>
     */
    public function mediaFiles(): array
    {
        $files = [];

        foreach (KycSubmission::mediaCollectionNames() as $collectionName) {
            $file = $this->file($collectionName);

            if ($file instanceof UploadedFile) {
                $files[$collectionName] = $file;
            }
        }

        return $files;
    }
}
