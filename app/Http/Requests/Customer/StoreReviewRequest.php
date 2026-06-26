<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class StoreReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'array', 'max:'.$this->integerConfig('lartisan.trust.review_proof_max_files', 4)],
            'proof.*' => [
                'file',
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp'])
                    ->max($this->integerConfig('lartisan.trust.review_proof_max_kilobytes', 8192)),
            ],
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function proof(): array
    {
        $uploadedProof = $this->file('proof', []);

        if ($uploadedProof instanceof UploadedFile) {
            return [$uploadedProof];
        }

        return array_values($uploadedProof);
    }

    private function integerConfig(string $key, int $fallback): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $fallback;
    }
}
