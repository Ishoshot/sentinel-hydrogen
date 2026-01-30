<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use Illuminate\Foundation\Http\FormRequest;

final class ShowPublicBriefingRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Get the password if provided.
     */
    public function password(): ?string
    {
        $password = $this->validated('password');

        return is_string($password) ? $password : null;
    }
}
