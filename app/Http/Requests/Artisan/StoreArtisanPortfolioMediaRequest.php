<?php

namespace App\Http\Requests\Artisan;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreArtisanPortfolioMediaRequest extends FormRequest
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
            'portfolio' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function portfolioImage(): UploadedFile
    {
        $file = $this->file('portfolio');

        assert($file instanceof UploadedFile);

        return $file;
    }
}
