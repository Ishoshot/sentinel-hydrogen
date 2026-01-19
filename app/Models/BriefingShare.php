<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BriefingShareFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Represents an external share link for a Briefing.
 *
 * @property int $id
 * @property int $briefing_generation_id
 * @property int $workspace_id
 * @property int $created_by_id
 * @property string $token
 * @property string|null $password_hash
 * @property int $access_count
 * @property int|null $max_accesses
 * @property \Carbon\Carbon $expires_at
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 */
final class BriefingShare extends Model
{
    /** @use HasFactory<BriefingShareFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'briefing_generation_id',
        'workspace_id',
        'created_by_id',
        'token',
        'password_hash',
        'access_count',
        'max_accesses',
        'expires_at',
        'is_active',
        'created_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Generate a secure share token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Hash a password for the share.
     */
    public static function hashPassword(string $password): string
    {
        return Hash::make($password);
    }

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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Check if the share is still valid.
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_accesses !== null && $this->access_count >= $this->max_accesses) {
            return false;
        }

        return true;
    }

    /**
     * Check if the share is password protected.
     */
    public function isPasswordProtected(): bool
    {
        return $this->password_hash !== null;
    }

    /**
     * Verify the provided password.
     */
    public function verifyPassword(string $password): bool
    {
        if ($this->password_hash === null) {
            return true;
        }

        return Hash::check($password, $this->password_hash);
    }

    /**
     * Increment the access count.
     */
    public function recordAccess(): void
    {
        $this->increment('access_count');
    }

    /**
     * Revoke the share.
     */
    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Scope to active and valid shares.
     *
     * @param  Builder<BriefingShare>  $query
     * @return Builder<BriefingShare>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now())
            ->where(function (Builder $q): void {
                $q->whereNull('max_accesses')
                    ->orWhereColumn('access_count', '<', 'max_accesses');
            });
    }

    /**
     * Scope to expired shares.
     *
     * @param  Builder<BriefingShare>  $query
     * @return Builder<BriefingShare>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('expires_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
