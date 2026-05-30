<?php

namespace App\Http\Requests\Teams;

use App\Rules\ValidTeamInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AcceptTeamInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invitation' => ['required', new ValidTeamInvitation($this->user())],
        ];
    }

    /**
     * Get the validation data from the request.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $data = [];

        foreach (parent::validationData() as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }

        $data['invitation'] = $this->route('invitation');

        return $data;
    }
}
