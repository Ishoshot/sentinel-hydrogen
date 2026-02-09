<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use App\Enums\Briefings\BriefingDeliveryChannel;
use App\Enums\Briefings\BriefingSchedulePreset;
use App\Models\SlackIntegration;
use App\Models\Workspace;
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

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $channels = $this->input('delivery_channels', []);

            if (in_array(BriefingDeliveryChannel::Slack->value, $channels, true)) {
                $workspace = $this->route('workspace');

                if (! $workspace instanceof Workspace) {
                    return;
                }

                $hasSlack = SlackIntegration::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('is_active', true)
                    ->exists();

                if (! $hasSlack) {
                    $validator->errors()->add(
                        'delivery_channels',
                        'Slack delivery requires a connected Slack integration. Connect Slack in Settings > Integrations.',
                    );
                }
            }
        });
    }
}
