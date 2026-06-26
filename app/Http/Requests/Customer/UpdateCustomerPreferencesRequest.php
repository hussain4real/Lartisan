<?php

namespace App\Http\Requests\Customer;

use App\Enums\PreferredChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerPreferencesRequest extends FormRequest
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
            'preferred_channel' => ['required', 'string', Rule::enum(PreferredChannel::class)],
            'schedule_window' => ['required', 'string', Rule::in(['flexible', 'morning', 'afternoon', 'evening', 'weekend'])],
            'default_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{preferred_channel: string, schedule_window: string, default_notes: string|null}
     */
    public function preferences(): array
    {
        $notes = $this->string('default_notes')->trim()->toString();

        return [
            'preferred_channel' => $this->string('preferred_channel')->toString(),
            'schedule_window' => $this->string('schedule_window')->toString(),
            'default_notes' => $notes === '' ? null : $notes,
        ];
    }
}
