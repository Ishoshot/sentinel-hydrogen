<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

final class PlanController
{
    /**
     * List all available plans.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->orderBy('price_monthly')
            ->orderBy('tier')
            ->get();

        return response()->json([
            'data' => PlanResource::collection($plans),
        ]);
    }
}
