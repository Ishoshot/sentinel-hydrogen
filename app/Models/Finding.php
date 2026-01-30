<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Reviews\FindingCategory;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SentinelConfigSeverity|null $severity
 * @property FindingCategory|null $category
 * @property string|null $finding_hash
 */
final class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'run_id',
        'finding_hash',
        'workspace_id',
        'severity',
        'category',
        'title',
        'description',
        'file_path',
        'line_start',
        'line_end',
        'confidence',
        'metadata',
        'created_at',
    ];

    /**
     * @return BelongsTo<Run, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<Annotation, $this>
     */
    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class);
    }

    /**
     * @param  Builder<Finding>  $query
     * @return Builder<Finding>
     */
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => SentinelConfigSeverity::class,
            'category' => FindingCategory::class,
            'line_start' => 'integer',
            'line_end' => 'integer',
            'confidence' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
