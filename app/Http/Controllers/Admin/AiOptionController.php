<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\AiOptions\CreateAiOption;
use App\Actions\Admin\AiOptions\DeleteAiOption;
use App\Actions\Admin\AiOptions\ListAiOptions;
use App\Actions\Admin\AiOptions\UpdateAiOption;
use App\Enums\AiProvider;
use App\Http\Requests\Admin\AiOption\StoreAiOptionRequest;
use App\Http\Requests\Admin\AiOption\UpdateAiOptionRequest;
use App\Http\Resources\Admin\AiOptionResource;
use App\Models\AiOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

/**
 * Admin controller for managing AI options (provider models).
 */
final class AiOptionController
{
    /**
     * List all AI options.
     */
    public function index(Request $request, ListAiOptions $listAiOptions): AnonymousResourceCollection
    {
        $provider = $request->has('provider')
            ? AiProvider::tryFrom($request->string('provider')->toString())
            : null;

        $aiOptions = $listAiOptions->handle(
            provider: $provider,
            activeOnly: $request->boolean('active_only'),
            perPage: $request->integer('per_page', 15),
        );

        return AiOptionResource::collection($aiOptions);
    }

    /**
     * Store a new AI option.
     */
    public function store(
        StoreAiOptionRequest $request,
        CreateAiOption $createAiOption,
    ): JsonResponse {
        $aiOption = $createAiOption->handle($request->validated());

        return response()->json([
            'data' => new AiOptionResource($aiOption),
            'message' => 'AI model created successfully.',
        ], 201);
    }

    /**
     * Show a specific AI option.
     */
    public function show(AiOption $aiOption): JsonResponse
    {
        $aiOption->loadCount('providerKeys');

        return response()->json([
            'data' => new AiOptionResource($aiOption),
        ]);
    }

    /**
     * Update an AI option.
     */
    public function update(
        UpdateAiOptionRequest $request,
        AiOption $aiOption,
        UpdateAiOption $updateAiOption,
    ): JsonResponse {
        $aiOption = $updateAiOption->handle($aiOption, $request->validated());

        return response()->json([
            'data' => new AiOptionResource($aiOption),
            'message' => 'AI model updated successfully.',
        ]);
    }

    /**
     * Delete an AI option.
     */
    public function destroy(
        AiOption $aiOption,
        DeleteAiOption $deleteAiOption,
    ): JsonResponse {
        try {
            $deleteAiOption->handle($aiOption);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'AI model deleted successfully.',
        ]);
    }
}
