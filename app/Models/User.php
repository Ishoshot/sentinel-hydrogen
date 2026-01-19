<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TeamRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string|null $avatar_url
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property bool $has_seen_getting_started
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return HasMany<ProviderIdentity, $this>
     */
    public function providerIdentities(): HasMany
    {
        return $this->hasMany(ProviderIdentity::class);
    }

    /**
     * @return HasMany<Workspace, $this>
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by_id');
    }

    /**
     * Check if the user belongs to the given workspace.
     */
    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->teamMemberships()
            ->where('workspace_id', $workspace->id)
            ->exists();
    }

    /**
     * Get the user's role in the given workspace.
     */
    public function roleInWorkspace(Workspace $workspace): ?TeamRole
    {
        $membership = $this->teamMemberships()
            ->where('workspace_id', $workspace->id)
            ->first();

        return $membership?->role;
    }

    /**
     * Check if the user is the owner of the given workspace.
     */
    public function isOwnerOf(Workspace $workspace): bool
    {
        return $this->roleInWorkspace($workspace) === TeamRole::Owner;
    }

    /**
     * Get the user's membership in the given workspace.
     */
    public function membershipInWorkspace(Workspace $workspace): ?TeamMember
    {
        return $this->teamMemberships()
            ->where('workspace_id', $workspace->id)
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'has_seen_getting_started' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
