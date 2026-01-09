<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'settings',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasOne<Team, $this>
     */
    public function team(): HasOne
    {
        return $this->hasOne(Team::class);
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * @return HasManyThrough<User, TeamMember, $this>
     */
    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            TeamMember::class,
            'workspace_id',
            'id',
            'id',
            'user_id'
        );
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @param  Builder<Workspace>  $query
     * @return Builder<Workspace>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('teamMembers', function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return Attribute<array<string, mixed>|null, array<string, mixed>|null>
     */
    protected function settings(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?array {
                if ($value === null || $value === '') {
                    return null;
                }

                if (! is_string($value)) {
                    return null;
                }

                $decoded = json_decode($value, true);

                return is_array($decoded) ? $decoded : null;
            },
            set: function (?array $value): ?string {
                if ($value === null) {
                    return null;
                }

                $encoded = json_encode($value);

                return $encoded !== false ? $encoded : null;
            },
        );
    }
}
