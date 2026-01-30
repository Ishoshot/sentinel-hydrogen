<?php

declare(strict_types=1);

namespace App\Http\Requests\GitHub;

use Illuminate\Foundation\Http\FormRequest;

final class ConnectionCallbackRequest extends FormRequest
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
            'installation_id' => ['required', 'integer'],
            'state' => ['nullable', 'string'],
            'setup_action' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the installation ID.
     */
    public function installationId(): int
    {
        return (int) $this->validated('installation_id');
    }

    /**
     * Get the state parameter.
     */
    public function state(): ?string
    {
        $state = $this->validated('state');

        return is_string($state) ? $state : null;
    }

    /**
     * Get the setup action.
     */
    public function setupAction(): ?string
    {
        $action = $this->validated('setup_action');

        return is_string($action) ? $action : null;
    }

    /**
     * Check if installation was cancelled.
     */
    public function wasCancelled(): bool
    {
        return $this->setupAction() === 'request';
    }
}
