<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingDownloadSource;
use App\Enums\Briefings\BriefingOutputFormat;
use App\Models\BriefingDownload;
use App\Models\BriefingGeneration;
use App\Models\User;
use Illuminate\Http\Request;

final readonly class TrackBriefingDownload
{
    /**
     * Track a briefing download event.
     *
     * @param  BriefingGeneration  $generation  The generation being downloaded
     * @param  BriefingOutputFormat  $format  The download format
     * @param  BriefingDownloadSource  $source  The download source
     * @param  Request  $request  The HTTP request for IP and user agent
     * @param  User|null  $user  The user downloading (null for external shares)
     * @return BriefingDownload The created download record
     */
    public function handle(
        BriefingGeneration $generation,
        BriefingOutputFormat $format,
        BriefingDownloadSource $source,
        Request $request,
        ?User $user = null,
    ): BriefingDownload {
        return BriefingDownload::create([
            'briefing_generation_id' => $generation->id,
            'workspace_id' => $generation->workspace_id,
            'user_id' => $user?->id,
            'format' => $format,
            'source' => $source,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'downloaded_at' => now(),
        ]);
    }
}
