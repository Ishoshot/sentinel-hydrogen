<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use Illuminate\Foundation\Http\FormRequest;

final class CreateShareRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $maxExpiryDays = config('briefings.retention.generations_days', 90);

        return [
            'expires_at' => ['nullable', 'date', 'after:now', 'before_or_equal:'.now()->addDays($maxExpiryDays)->toDateString(), 'prohibits:expires_in_days'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.$maxExpiryDays, 'prohibits:expires_at'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'max_accesses' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
