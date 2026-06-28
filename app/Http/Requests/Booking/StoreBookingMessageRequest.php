<?php

namespace App\Http\Requests\Booking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:1200'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->containsRestrictedContactDetail($this->body())) {
                    $validator->errors()->add('body', __('For privacy and safety, keep phone numbers, email addresses, links, and off-platform contact details out of chat.'));
                }
            },
        ];
    }

    public function body(): string
    {
        return $this->string('body')->trim()->toString();
    }

    private function containsRestrictedContactDetail(string $body): bool
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $body) === 1) {
            return true;
        }

        if (preg_match('/\b(?:https?:\/\/|www\.)\S+/i', $body) === 1) {
            return true;
        }

        if (preg_match('/(?<!\d)\+?\d[\d\s().-]{6,}\d(?!\d)/', $body, $matches) !== 1) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $matches[0]);

        return is_string($digits) && strlen($digits) >= 7;
    }
}
