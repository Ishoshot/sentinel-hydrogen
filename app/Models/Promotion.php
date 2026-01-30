<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PromotionValueCast;
use App\Enums\Promotions\PromotionValueType;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a promotional discount code for subscriptions.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $code
 * @property PromotionValueType $value_type
 * @property int|float $value_amount
 * @property \Carbon\Carbon|null $valid_from
 * @property \Carbon\Carbon|null $valid_to
 * @property int|null $max_uses
 * @property int $times_used
 * @property bool $is_active
 * @property string|null $polar_discount_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
final class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'code',
        'value_type',
        'value_amount',
        'valid_from',
        'valid_to',
        'max_uses',
        'times_used',
        'is_active',
        'polar_discount_id',
    ];

    /**
     * Check if the promotion is currently valid.
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($this->valid_from !== null && $now->isBefore($this->valid_from)) {
            return false;
        }

        if ($this->valid_to !== null && $now->isAfter($this->valid_to)) {
            return false;
        }

        if ($this->max_uses !== null && $this->times_used >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the discounted price for a given amount.
     */
    public function applyDiscount(Money $price): Money
    {
        $minorAmount = $price->getMinorAmount()->toInt();

        if ($this->value_type === PromotionValueType::Percentage) {
            $discountedMinor = (int) round($minorAmount * (1 - ($this->value_amount / 100)));
        } else {
            $discountedMinor = max(0, $minorAmount - $this->getValueAmountInCents());
        }

        return Money::ofMinor($discountedMinor, $price->getCurrency()->getCurrencyCode());
    }

    /**
     * Get the discount display string.
     */
    public function discountDisplay(): string
    {
        if ($this->value_type === PromotionValueType::Percentage) {
            return $this->value_amount.'% off';
        }

        $money = Money::ofMinor($this->getValueAmountInCents(), 'USD');

        return $money->formatTo('en_US').' off';
    }

    /**
     * Get the value amount in cents (for flat types).
     * This accesses the raw database value, bypassing the cast.
     */
    public function getValueAmountInCents(): int
    {
        /** @var int|string|null $raw */
        $raw = $this->getRawOriginal('value_amount');

        return (int) $raw;
    }

    /**
     * Increment the times used counter.
     */
    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }

    /**
     * Get all usages of this promotion.
     *
     * @return HasMany<PromotionUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    /**
     * Scope to find active and valid promotions.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeValid(Builder $query): Builder
    {
        $now = CarbonImmutable::now();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $now);
            })
            ->where(function (Builder $q): void {
                $q->whereNull('max_uses')
                    ->orWhereColumn('times_used', '<', 'max_uses');
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_type' => PromotionValueType::class,
            'value_amount' => PromotionValueCast::class,
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
