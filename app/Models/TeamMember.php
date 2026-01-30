<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Workspace\TeamRole;
use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property int $workspace_id
 * @property TeamRole $role
 * @property \Illuminate\Support\Carbon|null $joined_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'team_id',
        'workspace_id',
        'role',
        'joined_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Check if this member has the owner role.
     */
    public function isOwner(): bool
    {
        return $this->role === TeamRole::Owner;
    }

    /**
     * Check if this member has the admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === TeamRole::Admin;
    }

    /**
     * Check if this member has the member role.
     */
    public function isMember(): bool
    {
        return $this->role === TeamRole::Member;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
            'joined_at' => 'datetime',
        ];
    }
}
