<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\TrackBriefingDownload;
use App\Enums\Briefings\BriefingDownloadSource;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Enums\Briefings\BriefingOutputFormat;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final readonly class BriefingDownloadController
{
    /** @param TrackBriefingDownload $trackDownload Action to track downloads */
    public function __construct(
        private TrackBriefingDownload $trackDownload,
    ) {}

    /** Download a generation in a specific format. */
    public function __invoke(
        Request $request,
        Workspace $workspace,
        BriefingGeneration $generation,
        string $format,
    ): JsonResponse|RedirectResponse {
        if ($generation->workspace_id !== $workspace->id) {
            abort(404);
        }

        Gate::authorize('view', $generation);

        if ($generation->status !== BriefingGenerationStatus::Completed) {
            return response()->json([
                'message' => 'Briefing generation is not yet complete.',
            ], 400);
        }

        $outputFormat = BriefingOutputFormat::tryFrom($format);

        if ($outputFormat === null) {
            return response()->json([
                'message' => 'Invalid format. Supported formats: html, pdf, markdown, slides',
            ], 400);
        }

        $outputPaths = $generation->output_paths ?? [];

        if (! isset($outputPaths[$format])) {
            return response()->json([
                'message' => 'This format is not available for this briefing.',
            ], 404);
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $this->trackDownload->handle(
            generation: $generation,
            format: $outputFormat,
            source: BriefingDownloadSource::Dashboard,
            request: $request,
            user: $user,
        );

        $disk = config('briefings.storage.disk', 's3');
        $expiryMinutes = (int) config('briefings.storage.temporary_url_expiry_minutes', 60);

        $temporaryUrl = Storage::disk($disk)->temporaryUrl(
            $outputPaths[$format],
            now()->addMinutes($expiryMinutes),
            [
                'ResponseContentDisposition' => sprintf(
                    'attachment; filename="briefing-%d.%s"',
                    $generation->id,
                    $outputFormat->extension()
                ),
                'ResponseContentType' => $outputFormat->mimeType(),
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'url' => $temporaryUrl,
                'filename' => sprintf('briefing-%d.%s', $generation->id, $outputFormat->extension()),
                'content_type' => $outputFormat->mimeType(),
            ]);
        }

        return redirect()->away($temporaryUrl);
    }
}
