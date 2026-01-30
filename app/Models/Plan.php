<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\PlanTier;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tier',
        'description',
        'monthly_runs_limit',
        'monthly_commands_limit',
        'team_size_limit',
        'features',
        'limits',
        'price_monthly',
        'price_yearly',
    ];

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Workspace, $this>
     */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /**
     * Check if this plan has a specific feature enabled.
     */
    public function hasFeature(PlanFeature|string $feature): bool
    {
        $key = $feature instanceof PlanFeature ? $feature->value : $feature;
        $features = $this->features ?? [];

        return array_key_exists($key, $features) && (bool) $features[$key];
    }

    /**
     * Get the value of a specific feature.
     */
    public function featureValue(PlanFeature|string $feature, mixed $default = null): mixed
    {
        $key = $feature instanceof PlanFeature ? $feature->value : $feature;
        $features = $this->features ?? [];

        return $features[$key] ?? $default;
    }

    /**
     * Check if this plan is of the specified tier.
     */
    public function isTier(PlanTier $tier): bool
    {
        return $this->tier === $tier->value;
    }

    /**
     * Calculate yearly savings percentage compared to monthly billing.
     */
    public function yearlySavingsPercent(): int
    {
        /** @var \Brick\Money\Money|null $monthly */
        $monthly = $this->price_monthly;
        /** @var \Brick\Money\Money|null $yearly */
        $yearly = $this->price_yearly;

        if ($monthly === null || $yearly === null) {
            return 0;
        }

        $monthlyAnnual = $monthly->getMinorAmount()->toInt() * 12;
        $yearlyAmount = $yearly->getMinorAmount()->toInt();

        if ($monthlyAnnual <= 0) {
            return 0;
        }

        return (int) round((($monthlyAnnual - $yearlyAmount) / $monthlyAnnual) * 100);
    }

    /**
     * Get a specific limit value from the limits JSON.
     *
     * Returns null if the limit is not set (unlimited).
     * Returns 0 if explicitly set to 0 (blocked).
     *
     * @param  string  $path  Dot-notation path (e.g., 'briefings.daily')
     */
    public function getLimit(string $path): ?int
    {
        $limits = $this->limits ?? [];

        $value = data_get($limits, $path);

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Check if a limit is set (not null).
     */
    public function hasLimit(string $path): bool
    {
        $limits = $this->limits ?? [];

        return data_get($limits, $path) !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'price_monthly' => MoneyCast::class,
            'price_yearly' => MoneyCast::class,
        ];
    }
}
