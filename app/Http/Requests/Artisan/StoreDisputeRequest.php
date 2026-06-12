<?php

namespace App\Http\Requests\Artisan;

use App\Enums\DisputeSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreDisputeRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'severity' => ['required', Rule::enum(DisputeSeverity::class)],
            'evidence' => ['nullable', 'array', 'max:4'],
            'evidence.*' => [
                'file',
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp'])->max(8 * 1024),
            ],
        ];
    }
}
