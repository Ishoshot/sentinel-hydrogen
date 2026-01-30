<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use Illuminate\Foundation\Http\FormRequest;
use Override;

final class StoreBriefingFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'rating.required' => 'A rating is required.',
            'rating.min' => 'Rating must be at least 1.',
            'rating.max' => 'Rating must be at most 5.',
            'tags.max' => 'You can provide up to 10 tags.',
            'tags.*.max' => 'Tags must be 50 characters or fewer.',
        ];
    }
}
