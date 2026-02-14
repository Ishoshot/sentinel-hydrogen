<?php

declare(strict_types=1);

namespace App\Http\Requests\Briefings;

use App\Enums\Briefings\BriefingDeliveryChannel;
use App\Enums\Briefings\BriefingSchedulePreset;
use App\Models\SlackIntegration;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Override;

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
            'schedule_day' => [
                'nullable',
                'integer',
                'required_if:schedule_preset,weekly',
                'required_if:schedule_preset,monthly',
                Rule::when(
                    $this->input('schedule_preset') === BriefingSchedulePreset::Weekly->value,
                    ['between:1,7'],
                ),
                Rule::when(
                    $this->input('schedule_preset') === BriefingSchedulePreset::Monthly->value,
                    ['between:1,28'],
                ),
            ],
            'schedule_hour' => ['nullable', 'integer', 'between:0,23'],
            'parameters' => ['nullable', 'array'],
            'delivery_channels' => ['nullable', 'array', 'min:1'],
            'delivery_channels.*' => ['string', Rule::enum(BriefingDeliveryChannel::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    #[Override]
    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'schedule_day.required_if' => 'A schedule day is required for weekly and monthly presets.',
            'schedule_day.between' => 'The schedule day must be between :min and :max.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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
