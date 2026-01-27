<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MarkGettingStartedSeenController
{
    /**
     * Mark the getting started guide as seen for the authenticated user.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user->has_seen_getting_started = true;
        $user->save();

        return response()->json(['message' => 'Getting started marked as seen']);
    }
}
