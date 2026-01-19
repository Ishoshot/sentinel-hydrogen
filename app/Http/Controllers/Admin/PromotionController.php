<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Promotions\CreatePromotion;
use App\Actions\Admin\Promotions\DeletePromotion;
use App\Actions\Admin\Promotions\ListPromotions;
use App\Actions\Admin\Promotions\UpdatePromotion;
use App\Http\Requests\Admin\Promotion\StorePromotionRequest;
use App\Http\Requests\Admin\Promotion\UpdatePromotionRequest;
use App\Http\Resources\Admin\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin controller for managing promotions.
 */
final class PromotionController
{
    /**
     * List all promotions.
     */
    public function index(Request $request, ListPromotions $listPromotions): AnonymousResourceCollection
    {
        $promotions = $listPromotions->handle(
            activeOnly: $request->boolean('active_only'),
            validOnly: $request->boolean('valid_only'),
            perPage: $request->integer('per_page', 15),
        );

        return PromotionResource::collection($promotions);
    }

    /**
     * Store a new promotion.
     */
    public function store(
        StorePromotionRequest $request,
        CreatePromotion $createPromotion,
    ): JsonResponse {
        $promotion = $createPromotion->handle(
            data: $request->validated(),
            syncToPolar: $request->boolean('sync_to_polar'),
        );

        return response()->json([
            'data' => new PromotionResource($promotion),
            'message' => 'Promotion created successfully.',
        ], 201);
    }

    /**
     * Show a specific promotion.
     */
    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json([
            'data' => new PromotionResource($promotion),
        ]);
    }

    /**
     * Update a promotion.
     */
    public function update(
        UpdatePromotionRequest $request,
        Promotion $promotion,
        UpdatePromotion $updatePromotion,
    ): JsonResponse {
        $promotion = $updatePromotion->handle(
            promotion: $promotion,
            data: $request->validated(),
            syncToPolar: $request->boolean('sync_to_polar'),
        );

        return response()->json([
            'data' => new PromotionResource($promotion),
            'message' => 'Promotion updated successfully.',
        ]);
    }

    /**
     * Delete a promotion.
     */
    public function destroy(
        Request $request,
        Promotion $promotion,
        DeletePromotion $deletePromotion,
    ): JsonResponse {
        $deletePromotion->handle(
            promotion: $promotion,
            syncToPolar: $request->boolean('sync_to_polar'),
        );

        return response()->json([
            'message' => 'Promotion deleted successfully.',
        ]);
    }
}
