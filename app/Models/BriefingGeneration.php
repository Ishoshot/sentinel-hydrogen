<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Briefings\BriefingGenerationStatus;
use Database\Factories\BriefingGenerationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a generated Briefing instance.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $briefing_id
 * @property int $generated_by_id
 * @property array<string, mixed>|null $parameters
 * @property BriefingGenerationStatus $status
 * @property int $progress
 * @property string|null $progress_message
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property string|null $narrative
 * @property array<string, mixed>|null $structured_data
 * @property array<array<string, mixed>>|null $achievements
 * @property array<string, string>|null $excerpts
 * @property array<string, string>|null $output_paths
 * @property array<string, mixed>|null $metadata
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon $created_at
 */
final class BriefingGeneration extends Model
{
    /** @use HasFactory<BriefingGenerationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'briefing_id',
        'generated_by_id',
        'parameters',
        'status',
        'progress',
        'progress_message',
        'started_at',
        'completed_at',
        'narrative',
        'structured_data',
        'achievements',
        'excerpts',
        'output_paths',
        'metadata',
        'error_message',
        'expires_at',
        'created_at',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Briefing, $this>
     */
    public function briefing(): BelongsTo
    {
        return $this->belongsTo(Briefing::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_id');
    }

    /**
     * @return HasMany<BriefingDownload, $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(BriefingDownload::class);
    }

    /**
     * @return HasMany<BriefingShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(BriefingShare::class);
    }

    /**
     * Check if the generation is complete.
     */
    public function isCompleted(): bool
    {
        return $this->status === BriefingGenerationStatus::Completed;
    }

    /**
     * Check if the generation has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === BriefingGenerationStatus::Failed;
    }

    /**
     * Check if the generation is still processing.
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, [
            BriefingGenerationStatus::Pending,
            BriefingGenerationStatus::Processing,
        ], true);
    }

    /**
     * Scope to workspace generations.
     *
     * @param  Builder<BriefingGeneration>  $query
     * @return Builder<BriefingGeneration>
     */
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * Scope to completed generations.
     *
     * @param  Builder<BriefingGeneration>  $query
     * @return Builder<BriefingGeneration>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', BriefingGenerationStatus::Completed);
    }

    /**
     * Scope to expired generations.
     *
     * @param  Builder<BriefingGeneration>  $query
     * @return Builder<BriefingGeneration>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'status' => BriefingGenerationStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'structured_data' => 'array',
            'achievements' => 'array',
            'excerpts' => 'array',
            'output_paths' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
