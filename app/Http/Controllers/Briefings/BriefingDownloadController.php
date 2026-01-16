<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\TrackBriefingDownload;
use App\Enums\BriefingDownloadSource;
use App\Enums\BriefingGenerationStatus;
use App\Enums\BriefingOutputFormat;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    ): JsonResponse|StreamedResponse {
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

        $disk = config('briefings.storage.disk', 'r2');

        return Storage::disk($disk)->download(
            $outputPaths[$format],
            sprintf('briefing-%d.%s', $generation->id, $outputFormat->extension()),
            ['Content-Type' => $outputFormat->mimeType()]
        );
    }
}
