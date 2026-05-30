<?php

namespace App\Http\Requests\Identity;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ClaimAccountRequest extends FormRequest
{
    use PasswordValidationRules;

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
            'name' => ['nullable', 'string', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }

    public function accountName(): ?string
    {
        $name = $this->string('name')->trim()->toString();

        return $name !== '' ? $name : null;
    }

    public function token(): string
    {
        return $this->string('token')->trim()->toString();
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
