<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Brick\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @property-read \App\Models\Plan $resource
 */
final class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        /** @var Money|null $priceMonthly */
        $priceMonthly = $this->resource->price_monthly;
        /** @var Money|null $priceYearly */
        $priceYearly = $this->resource->price_yearly;

        $tier = $this->resource->tier;
        $priceAmount = $priceMonthly?->getAmount()->toInt() ?? 0;

        return [
            'id' => $this->resource->id,
            'tier' => $tier,
            'name' => $this->formatTierName($tier),
            'description' => $this->resource->description,

            // Limits
            'monthly_runs_limit' => $this->resource->monthly_runs_limit,
            'monthly_commands_limit' => $this->resource->monthly_commands_limit,
            'team_size_limit' => $this->resource->team_size_limit,

            // Formatted limit labels
            'runs_label' => $this->formatLimit($this->resource->monthly_runs_limit),
            'commands_label' => $this->formatLimit($this->resource->monthly_commands_limit),
            'team_size_label' => $this->formatLimit($this->resource->team_size_limit),

            // Pricing
            'price' => $priceAmount,
            'price_label' => $this->formatPrice($priceMonthly),
            'period' => $priceAmount === 0 ? 'Free forever' : 'per month',
            'price_monthly_cents' => $priceMonthly?->getMinorAmount()->toInt(),
            'price_monthly' => $priceMonthly?->getAmount()->__toString(),
            'price_yearly_cents' => $priceYearly?->getMinorAmount()->toInt(),
            'price_yearly' => $priceYearly?->getAmount()->__toString(),
            'yearly_savings_percent' => $this->resource->yearlySavingsPercent(),
            'currency' => $priceMonthly?->getCurrency()->getCurrencyCode(),

            // Features
            'features' => $this->resource->features ?? [],
            'feature_list' => $this->getFeatureList($tier),
            'support' => $this->getSupportLevel($tier),

            // Presentation
            'highlighted' => $tier === 'illuminate',
            'cta' => $this->getCtaText($tier),
            'cta_link' => $this->getCtaLink($tier),
            'color' => $this->getColor($tier),
        ];
    }

    /**
     * Format tier name for display.
     */
    private function formatTierName(string $tier): string
    {
        return ucfirst($tier);
    }

    /**
     * Format a limit value for display.
     */
    private function formatLimit(?int $limit): string
    {
        if ($limit === null) {
            return 'Unlimited';
        }

        return number_format($limit);
    }

    /**
     * Format price for display.
     */
    private function formatPrice(?Money $price): string
    {
        if (! $price instanceof Money) {
            return '$0';
        }

        $amount = $price->getAmount()->toInt();

        return '$'.number_format($amount);
    }

    /**
     * Get feature list for display.
     *
     * @return array<int, string>
     */
    private function getFeatureList(string $tier): array
    {
        return match ($tier) {
            'foundation' => [
                '20 reviews per month',
                '2 team members',
                'GitHub integration',
                'Basic findings',
            ],
            'illuminate' => [
                '500 reviews per month',
                '5 team members',
                'Custom guidelines',
                'Priority processing',
            ],
            'orchestrate' => [
                '2,000 reviews per month',
                'Unlimited members',
                'API access',
                'Advanced analytics',
            ],
            'sanctum' => [
                'Unlimited reviews',
                'Unlimited members',
                'SSO & SAML',
                'Audit logs',
            ],
            default => [],
        };
    }

    /**
     * Get support level for the tier.
     */
    private function getSupportLevel(string $tier): string
    {
        return match ($tier) {
            'foundation' => 'Community',
            'illuminate' => 'Email',
            'orchestrate' => 'Priority',
            'sanctum' => 'Dedicated',
            default => 'Community',
        };
    }

    /**
     * Get CTA text for the tier.
     */
    private function getCtaText(string $tier): string
    {
        return match ($tier) {
            'foundation' => 'Start free',
            'sanctum' => 'Contact sales',
            default => 'Get started',
        };
    }

    /**
     * Get CTA link for the tier.
     */
    private function getCtaLink(string $tier): string
    {
        return match ($tier) {
            'sanctum' => 'mailto:hello@usesentinel.ai',
            default => '/login',
        };
    }

    /**
     * Get color theme for the tier.
     */
    private function getColor(string $tier): string
    {
        return match ($tier) {
            'foundation' => 'slate',
            'illuminate' => 'blue',
            'orchestrate' => 'purple',
            'sanctum' => 'amber',
            default => 'slate',
        };
    }
}
