<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Briefings\BriefingSchedulePreset;
use Database\Factories\BriefingSubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Represents a scheduled recurring Briefing subscription.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property int $briefing_id
 * @property BriefingSchedulePreset $schedule_preset
 * @property int|null $schedule_day
 * @property int $schedule_hour
 * @property array<string, mixed>|null $parameters
 * @property array<string> $delivery_channels
 * @property \Carbon\Carbon|null $last_generated_at
 * @property \Carbon\Carbon $next_scheduled_at
 * @property bool $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
final class BriefingSubscription extends Model
{
    /** @use HasFactory<BriefingSubscriptionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'user_id',
        'briefing_id',
        'schedule_preset',
        'schedule_day',
        'schedule_hour',
        'parameters',
        'delivery_channels',
        'last_generated_at',
        'next_scheduled_at',
        'is_active',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Briefing, $this>
     */
    public function briefing(): BelongsTo
    {
        return $this->belongsTo(Briefing::class);
    }

    /**
     * Calculate the next scheduled time based on the current schedule.
     *
     * @param  bool  $withJitter  Whether to apply random jitter (0-5 minutes) to spread load
     */
    public function calculateNextScheduledAt(bool $withJitter = false): \Carbon\Carbon
    {
        $now = now()->utc();
        $hour = $this->schedule_hour;

        $next = match ($this->schedule_preset) {
            BriefingSchedulePreset::Daily => $now->copy()->addDay()->setTime($hour, 0),
            BriefingSchedulePreset::Weekly => $now->copy()->next(
                $this->convertIsoToCarbonDay($this->schedule_day ?? 1)
            )->setTime($hour, 0),
            BriefingSchedulePreset::Monthly => $now->copy()->addMonth()->setDay($this->schedule_day ?? 1)->setTime($hour, 0),
        };

        if ($withJitter) {
            $next->addMinutes(random_int(0, 5));
        }

        return $next;
    }

    /**
     * Update the schedule after a generation completes.
     */
    public function markGenerated(): void
    {
        $this->update([
            'last_generated_at' => now(),
            'next_scheduled_at' => $this->calculateNextScheduledAt(withJitter: true),
        ]);
    }

    /**
     * Defer the next scheduled run without marking a generation as completed.
     */
    public function markDeferred(): void
    {
        $this->update([
            'next_scheduled_at' => $this->calculateNextScheduledAt(withJitter: true),
        ]);
    }

    /**
     * Scope to active subscriptions.
     *
     * @param  Builder<BriefingSubscription>  $query
     * @return Builder<BriefingSubscription>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to subscriptions due for generation.
     *
     * @param  Builder<BriefingSubscription>  $query
     * @return Builder<BriefingSubscription>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('next_scheduled_at', '<=', now());
    }

    /**
     * Scope to workspace subscriptions.
     *
     * @param  Builder<BriefingSubscription>  $query
     * @return Builder<BriefingSubscription>
     */
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * Scope to user subscriptions.
     *
     * @param  Builder<BriefingSubscription>  $query
     * @return Builder<BriefingSubscription>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'schedule_preset' => BriefingSchedulePreset::class,
            'parameters' => 'array',
            'delivery_channels' => 'array',
            'last_generated_at' => 'datetime',
            'next_scheduled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Convert an ISO-8601 day number (1=Monday to 7=Sunday) to a Carbon day constant (0=Sunday to 6=Saturday).
     */
    private function convertIsoToCarbonDay(int $isoDay): int
    {
        return $isoDay % 7;
    }
}
