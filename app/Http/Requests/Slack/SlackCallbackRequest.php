<?php

declare(strict_types=1);

namespace App\Http\Requests\Slack;

use Illuminate\Foundation\Http\FormRequest;

final class SlackCallbackRequest extends FormRequest
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
            'code' => ['required_without:error', 'string'],
            'state' => ['required_without:error', 'string'],
            'error' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the authorization code.
     */
    public function code(): string
    {
        return $this->string('code')->toString();
    }

    /**
     * Get the state parameter.
     */
    public function state(): string
    {
        return $this->string('state')->toString();
    }

    /**
     * Check if the user declined the authorization.
     */
    public function wasDeclined(): bool
    {
        return $this->filled('error');
    }
}
