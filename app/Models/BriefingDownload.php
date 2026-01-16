<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BriefingDownloadSource;
use App\Enums\BriefingOutputFormat;
use Database\Factories\BriefingDownloadFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks Briefing download/access events.
 *
 * @property int $id
 * @property int $briefing_generation_id
 * @property int $workspace_id
 * @property int|null $user_id
 * @property BriefingOutputFormat $format
 * @property BriefingDownloadSource $source
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $downloaded_at
 */
final class BriefingDownload extends Model
{
    /** @use HasFactory<BriefingDownloadFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'briefing_generation_id',
        'workspace_id',
        'user_id',
        'format',
        'source',
        'ip_address',
        'user_agent',
        'downloaded_at',
    ];

    /**
     * @return BelongsTo<BriefingGeneration, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(BriefingGeneration::class, 'briefing_generation_id');
    }

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
     * Scope to workspace downloads.
     *
     * @param  Builder<BriefingDownload>  $query
     * @return Builder<BriefingDownload>
     */
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * Scope to downloads within a date range.
     *
     * @param  Builder<BriefingDownload>  $query
     * @return Builder<BriefingDownload>
     */
    public function scopeWithinDateRange(Builder $query, DateTimeInterface $start, DateTimeInterface $end): Builder
    {
        return $query->whereBetween('downloaded_at', [$start, $end]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => BriefingOutputFormat::class,
            'source' => BriefingDownloadSource::class,
            'downloaded_at' => 'datetime',
        ];
    }
}
