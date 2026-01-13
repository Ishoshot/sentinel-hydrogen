<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PlanFeature;
use App\Enums\PlanTier;
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
        'team_size_limit',
        'features',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price_monthly' => MoneyCast::class,
            'price_yearly' => MoneyCast::class,
        ];
    }
}
