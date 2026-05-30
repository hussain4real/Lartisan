<?php

namespace App\Http\Requests\Identity;

use App\Enums\PreferredChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
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
            'phone_country_code' => ['required', 'string', 'max:8', 'regex:/^\+?[0-9]{1,4}$/'],
            'phone_number' => ['required', 'string', 'max:32', 'regex:/^[0-9 ()+\-]{7,32}$/'],
            'code' => ['required', 'string', 'digits:6'],
            'preferred_channel' => ['nullable', 'string', Rule::enum(PreferredChannel::class)],
        ];
    }

    public function phoneCountryCode(): string
    {
        return $this->string('phone_country_code')->trim()->toString();
    }

    public function phoneNumber(): string
    {
        return $this->string('phone_number')->trim()->toString();
    }

    public function code(): string
    {
        return $this->string('code')->trim()->toString();
    }

    public function preferredChannel(): ?PreferredChannel
    {
        $channel = $this->string('preferred_channel')->trim()->toString();

        return $channel !== '' ? PreferredChannel::from($channel) : null;
    }
}
