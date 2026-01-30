<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

final class ChangeSubscriptionRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_tier' => ['required', 'string', Rule::in(PlanTier::values())],
            'billing_interval' => ['sometimes', 'string', Rule::in(BillingInterval::values())],
            'promo_code' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'plan_tier.required' => 'Plan tier is required.',
            'plan_tier.string' => 'Plan tier must be a string.',
            'plan_tier.in' => 'Plan tier must be a valid subscription tier.',
            'billing_interval.string' => 'Billing interval must be a string.',
            'billing_interval.in' => 'Billing interval must be monthly or yearly.',
            'promo_code.string' => 'Promo code must be a string.',
            'promo_code.max' => 'Promo code must not exceed 50 characters.',
        ];
    }
}
