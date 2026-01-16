<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use App\Enums\BriefingDeliveryChannel;
use App\Enums\BriefingSchedulePreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

final class CreateSubscriptionRequest extends FormRequest
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
            'briefing_id' => ['required', 'integer', 'exists:briefings,id'],
            'schedule_preset' => ['required', 'string', Rule::enum(BriefingSchedulePreset::class)],
            'schedule_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'schedule_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'parameters' => ['nullable', 'array'],
            'delivery_channels' => ['nullable', 'array', 'min:1'],
            'delivery_channels.*' => ['string', Rule::enum(BriefingDeliveryChannel::class)],
            'slack_webhook_url' => ['nullable', 'url'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'delivery_channels.min' => 'At least one delivery channel is required.',
        ];
    }
}
