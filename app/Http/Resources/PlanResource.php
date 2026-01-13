<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Brick\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
    public function toArray(Request $request): array
    {
        /** @var Money|null $priceMonthly */
        $priceMonthly = $this->resource->price_monthly;
        /** @var Money|null $priceYearly */
        $priceYearly = $this->resource->price_yearly;

        return [
            'id' => $this->resource->id,
            'tier' => $this->resource->tier,
            'description' => $this->resource->description,
            'monthly_runs_limit' => $this->resource->monthly_runs_limit,
            'team_size_limit' => $this->resource->team_size_limit,
            'features' => $this->resource->features ?? [],
            'price_monthly_cents' => $priceMonthly?->getMinorAmount()->toInt(),
            'price_monthly' => $priceMonthly?->getAmount()->__toString(),
            'price_yearly_cents' => $priceYearly?->getMinorAmount()->toInt(),
            'price_yearly' => $priceYearly?->getAmount()->__toString(),
            'yearly_savings_percent' => $this->resource->yearlySavingsPercent(),
            'currency' => $priceMonthly?->getCurrency()->getCurrencyCode(),
        ];
    }
}
