<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use App\Enums\Briefings\BriefingDeliveryChannel;
use App\Enums\Briefings\BriefingSchedulePreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSubscriptionRequest extends FormRequest
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
            'schedule_preset' => ['nullable', 'string', Rule::enum(BriefingSchedulePreset::class)],
            'schedule_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'schedule_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'parameters' => ['nullable', 'array'],
            'delivery_channels' => ['nullable', 'array', 'min:1'],
            'delivery_channels.*' => ['string', Rule::enum(BriefingDeliveryChannel::class)],
            'slack_webhook_url' => ['nullable', 'url'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
