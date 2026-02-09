<?php

declare(strict_types=1);

namespace App\Http\Requests\Slack;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSlackChannelRequest extends FormRequest
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
            'channel_id' => ['required', 'string', 'max:50'],
            'channel_name' => ['required', 'string', 'max:100'],
        ];
    }
}
