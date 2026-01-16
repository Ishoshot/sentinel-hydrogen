<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BriefingDeliveryChannel;
use App\Enums\BriefingSchedulePreset;
use App\Models\Briefing;
use App\Models\BriefingSubscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BriefingSubscription>
 */
final class BriefingSubscriptionFactory extends Factory
{
    protected $model = BriefingSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workspace = Workspace::factory();

        return [
            'workspace_id' => $workspace,
            'user_id' => User::factory(),
            'briefing_id' => Briefing::factory(),
            'schedule_preset' => BriefingSchedulePreset::Weekly,
            'schedule_day' => 1,
            'schedule_hour' => 9,
            'parameters' => [
                'date_range_days' => 7,
            ],
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
            'slack_webhook_url' => null,
            'last_generated_at' => null,
            'next_scheduled_at' => now()->addWeek()->startOfDay()->addHours(9),
            'is_active' => true,
        ];
    }

    /**
     * Set as daily schedule.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes): array => [
            'schedule_preset' => BriefingSchedulePreset::Daily,
            'schedule_day' => null,
            'next_scheduled_at' => now()->addDay()->startOfDay()->addHours(9),
        ]);
    }

    /**
     * Set as weekly schedule.
     */
    public function weekly(int $day = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'schedule_preset' => BriefingSchedulePreset::Weekly,
            'schedule_day' => $day,
            'next_scheduled_at' => now()->next($day)->startOfDay()->addHours(9),
        ]);
    }

    /**
     * Set as monthly schedule.
     */
    public function monthly(int $day = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'schedule_preset' => BriefingSchedulePreset::Monthly,
            'schedule_day' => $day,
            'next_scheduled_at' => now()->addMonth()->setDay($day)->startOfDay()->addHours(9),
        ]);
    }

    /**
     * Set as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Set as due for generation (next_scheduled_at in the past).
     */
    public function due(): static
    {
        return $this->state(fn (array $attributes): array => [
            'next_scheduled_at' => now()->subHour(),
        ]);
    }

    /**
     * Set for a specific workspace.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes): array => [
            'workspace_id' => $workspace->id,
        ]);
    }

    /**
     * Set for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set with Slack delivery.
     */
    public function withSlack(string $webhookUrl = 'https://hooks.slack.com/services/test'): static
    {
        return $this->state(fn (array $attributes): array => [
            'delivery_channels' => [
                BriefingDeliveryChannel::Push->value,
                BriefingDeliveryChannel::Slack->value,
            ],
            'slack_webhook_url' => $webhookUrl,
        ]);
    }
}
