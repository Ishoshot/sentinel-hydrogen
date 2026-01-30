<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Plans\ListPlans;
use App\Http\Resources\PlanResource;
use Illuminate\Http\JsonResponse;

final class PlanController
{
    /**
     * List all available plans.
     */
    public function index(ListPlans $listPlans): JsonResponse
    {
        $plans = $listPlans->handle();

        return response()->json([
            'data' => PlanResource::collection($plans),
        ]);
    }
}
