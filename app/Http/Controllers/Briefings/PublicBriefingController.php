<?php

declare(strict_types=1);

namespace App\Http\Controllers\Briefings;

use App\Actions\Briefings\ViewSharedBriefing;
use App\Http\Requests\Briefings\ShowPublicBriefingRequest;
use App\Http\Resources\Briefings\BriefingGenerationResource;
use Illuminate\Http\JsonResponse;

final readonly class PublicBriefingController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private ViewSharedBriefing $viewSharedBriefing,
    ) {}

    /**
     * View a shared briefing.
     */
    public function show(ShowPublicBriefingRequest $request, string $token): JsonResponse
    {
        $result = $this->viewSharedBriefing->handle(
            token: $token,
            password: $request->password(),
            request: $request,
        );

        if (! $result->isSuccessful()) {
            $response = ['message' => $result->error];

            if ($result->isPasswordRequired()) {
                $response['requires_password'] = true;
            }

            return response()->json($response, $result->httpStatus);
        }

        return response()->json([
            'data' => new BriefingGenerationResource($result->generation),
        ]);
    }
}
