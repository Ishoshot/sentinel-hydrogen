<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Users\MarkGettingStartedAsSeen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MarkGettingStartedSeenController
{
    /**
     * Mark the getting started guide as seen for the authenticated user.
     */
    public function __invoke(
        Request $request,
        MarkGettingStartedAsSeen $markGettingStartedAsSeen,
    ): JsonResponse {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $markGettingStartedAsSeen->handle($user);

        return response()->json(['message' => 'Getting started marked as seen']);
    }
}
