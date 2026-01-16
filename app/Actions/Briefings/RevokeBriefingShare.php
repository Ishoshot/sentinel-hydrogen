<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Models\BriefingShare;

final readonly class RevokeBriefingShare
{
    /**
     * Revoke a briefing share link.
     *
     * @param  BriefingShare  $share  The share to revoke
     */
    public function handle(BriefingShare $share): void
    {
        $share->update([
            'is_active' => false,
        ]);
    }
}
