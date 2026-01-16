<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BriefingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a Briefing template.
 *
 * @property int $id
 * @property int|null $workspace_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property array<string>|null $target_roles
 * @property array<string, mixed>|null $parameter_schema
 * @property string|null $prompt_path
 * @property bool $requires_ai
 * @property array<int>|null $eligible_plan_ids
 * @property int $estimated_duration_seconds
 * @property array<string> $output_formats
 * @property bool $is_schedulable
 * @property bool $is_system
 * @property int $sort_order
 * @property bool $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
final class Briefing extends Model
{
    /** @use HasFactory<BriefingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'title',
        'slug',
        'description',
        'icon',
        'target_roles',
        'parameter_schema',
        'prompt_path',
        'requires_ai',
        'eligible_plan_ids',
        'estimated_duration_seconds',
        'output_formats',
        'is_schedulable',
        'is_system',
        'sort_order',
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
     * @return HasMany<BriefingGeneration, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(BriefingGeneration::class);
    }

    /**
     * @return HasMany<BriefingSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(BriefingSubscription::class);
    }

    /**
     * Check if a plan is eligible for this briefing.
     */
    public function isEligibleForPlan(Plan $plan): bool
    {
        if ($this->eligible_plan_ids === null) {
            return true;
        }

        return in_array($plan->id, $this->eligible_plan_ids, true);
    }

    /**
     * Scope to active briefings.
     *
     * @param  Builder<Briefing>  $query
     * @return Builder<Briefing>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to system briefings.
     *
     * @param  Builder<Briefing>  $query
     * @return Builder<Briefing>
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to briefings available for a workspace.
     *
     * @param  Builder<Briefing>  $query
     * @return Builder<Briefing>
     */
    public function scopeAvailableForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where(function (Builder $q) use ($workspace): void {
            $q->where('is_system', true)
                ->orWhere('workspace_id', $workspace->id);
        });
    }

    /**
     * Scope to briefings eligible for a specific plan.
     *
     * @param  Builder<Briefing>  $query
     * @return Builder<Briefing>
     */
    public function scopeEligibleForPlan(Builder $query, Plan $plan): Builder
    {
        return $query->where(function (Builder $q) use ($plan): void {
            $q->whereNull('eligible_plan_ids')
                ->orWhereJsonContains('eligible_plan_ids', $plan->id);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'parameter_schema' => 'array',
            'requires_ai' => 'boolean',
            'eligible_plan_ids' => 'array',
            'output_formats' => 'array',
            'is_schedulable' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
