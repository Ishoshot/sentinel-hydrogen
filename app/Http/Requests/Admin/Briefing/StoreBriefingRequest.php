<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Briefing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

final class StoreBriefingRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('briefings', 'slug')],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string'],
            'parameter_schema' => ['nullable', 'array'],
            'prompt_path' => ['nullable', 'string', 'max:255'],
            'requires_ai' => ['boolean'],
            'eligible_plan_ids' => ['nullable', 'array'],
            'eligible_plan_ids.*' => ['integer'],
            'output_formats' => ['array'],
            'output_formats.*' => ['string', Rule::in(['html', 'pdf', 'markdown', 'slides'])],
            'is_schedulable' => ['boolean'],
            'is_system' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
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
            'title.required' => 'The briefing title is required.',
            'slug.required' => 'The briefing slug is required.',
            'slug.unique' => 'This slug is already in use by another briefing.',
        ];
    }
}
