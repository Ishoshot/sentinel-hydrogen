<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SlackIntegrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Represents a workspace-level Slack OAuth integration.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string|null $access_token
 * @property string|null $bot_user_id
 * @property string|null $slack_team_id
 * @property string|null $team_name
 * @property string|null $scope
 * @property string|null $authed_user_id
 * @property string|null $channel_id
 * @property string|null $channel_name
 * @property string|null $state
 * @property \Carbon\Carbon|null $state_expires_at
 * @property bool $is_active
 * @property \Carbon\Carbon|null $connected_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
final class SlackIntegration extends Model
{
    /** @use HasFactory<SlackIntegrationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'access_token',
        'bot_user_id',
        'slack_team_id',
        'team_name',
        'scope',
        'authed_user_id',
        'channel_id',
        'channel_name',
        'state',
        'state_expires_at',
        'is_active',
        'connected_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'state',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Scope to active integrations.
     *
     * @param  Builder<SlackIntegration>  $query
     * @return Builder<SlackIntegration>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to a specific workspace.
     *
     * @param  Builder<SlackIntegration>  $query
     * @return Builder<SlackIntegration>
     */
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    /**
     * Check if the integration has a valid bot token.
     */
    public function hasValidToken(): bool
    {
        return $this->access_token !== null && $this->is_active;
    }

    /**
     * Check if the integration is fully configured (token + channel).
     */
    public function isFullyConfigured(): bool
    {
        return $this->hasValidToken() && $this->channel_id !== null;
    }

    /**
     * Check if the integration has a valid pending OAuth state.
     */
    public function hasValidState(): bool
    {
        return $this->state !== null
            && $this->state_expires_at !== null
            && $this->state_expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
            'state_expires_at' => 'datetime',
        ];
    }
}
