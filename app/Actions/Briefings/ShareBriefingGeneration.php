<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class ShareBriefingGeneration
{
    /**
     * Create a shareable link for a briefing generation.
     *
     * @param  BriefingGeneration  $generation  The generation to share
     * @param  User  $user  The user creating the share
     * @param  DateTimeInterface|null  $expiresAt  When the share expires
     * @param  string|null  $password  Optional password protection
     * @param  int|null  $maxAccesses  Maximum number of accesses
     * @return BriefingShare The created share
     */
    public function handle(
        BriefingGeneration $generation,
        User $user,
        ?DateTimeInterface $expiresAt = null,
        ?string $password = null,
        ?int $maxAccesses = null,
    ): BriefingShare {
        $defaultExpiryDays = config('briefings.retention.shares_default_expiry_days', 7);

        return BriefingShare::create([
            'briefing_generation_id' => $generation->id,
            'workspace_id' => $generation->workspace_id,
            'created_by_id' => $user->id,
            'token' => Str::random(64),
            'password_hash' => $password !== null ? Hash::make($password) : null,
            'access_count' => 0,
            'max_accesses' => $maxAccesses,
            'expires_at' => $expiresAt ?? now()->addDays($defaultExpiryDays),
            'is_active' => true,
        ]);
    }
}
