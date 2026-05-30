<?php

namespace App\Http\Requests\Artisan;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitializeSubscriptionPaymentRequest extends FormRequest
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
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'id')->where('active', true),
            ],
        ];
    }

    public function subscriptionPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->whereKey($this->integer('subscription_plan_id'))
            ->where('active', true)
            ->firstOrFail();
    }
}
