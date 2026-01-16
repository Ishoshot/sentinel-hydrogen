<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Promotion;

use App\Enums\PromotionValueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

final class StorePromotionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'code' => ['required', 'string', 'max:50', 'unique:promotions,code'],
            'value_type' => ['required', 'string', Rule::enum(PromotionValueType::class)],
            'value_amount' => ['required', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'sync_to_polar' => ['boolean'],
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
            'name.required' => 'The promotion name is required.',
            'code.required' => 'The promotion code is required.',
            'code.unique' => 'This promotion code is already in use.',
            'value_type.required' => 'The discount type is required.',
            'value_amount.required' => 'The discount amount is required.',
            'value_amount.min' => 'The discount amount must be at least 1.',
            'valid_to.after_or_equal' => 'The end date must be after or equal to the start date.',
        ];
    }
}
