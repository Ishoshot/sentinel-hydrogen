<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityType;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int|null $actor_id
 * @property ActivityType $type
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $description
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 */
final class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'actor_id',
        'type',
        'subject_type',
        'subject_id',
        'description',
        'metadata',
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if this activity was performed by the system.
     */
    public function isSystemAction(): bool
    {
        return $this->actor_id === null;
    }

    /**
     * Check if this activity was performed by a user.
     */
    public function isUserAction(): bool
    {
        return $this->actor_id !== null;
    }

    /**
     * Boot the model.
     */
    #[Override]
    protected static function booted(): void
    {
        self::creating(function (Activity $activity): void {
            if ($activity->created_at === null) {
                $activity->created_at = now();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
