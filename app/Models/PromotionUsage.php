<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Promotions\PromotionUsageStatus;
use Database\Factories\PromotionUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PromotionUsage extends Model
{
    /** @use HasFactory<PromotionUsageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'promotion_id',
        'workspace_id',
        'subscription_id',
        'status',
        'checkout_url',
        'confirmed_at',
    ];

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Mark this usage as completed and increment the promotion counter.
     */
    public function confirm(?Subscription $subscription = null): void
    {
        if ($this->status === PromotionUsageStatus::Completed) {
            return;
        }

        $this->forceFill([
            'status' => PromotionUsageStatus::Completed,
            'subscription_id' => $subscription?->id ?? $this->subscription_id,
            'confirmed_at' => now(),
        ])->save();

        $this->promotion?->incrementUsage();
    }

    /**
     * Mark this usage as failed.
     */
    public function markFailed(): void
    {
        $this->forceFill([
            'status' => PromotionUsageStatus::Failed,
        ])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PromotionUsageStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }
}
