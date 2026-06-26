<?php

namespace App\Http\Requests\BookingTracker;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpgradeGuestBookingAccountRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

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
     * @return array<string, array<int, ValidationRule|Password|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'token' => ['required', 'string'],
        ];
    }

    public function guestName(): string
    {
        return $this->string('name')->trim()->toString();
    }

    public function email(): string
    {
        return $this->string('email')->trim()->lower()->toString();
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
