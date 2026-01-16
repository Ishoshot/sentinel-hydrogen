<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\BriefingGenerationStatus;
use App\Jobs\Briefings\ProcessBriefingGeneration;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final readonly class GenerateBriefing
{
    /**
     * Generate a new briefing for a workspace.
     *
     * @param  Workspace  $workspace  The workspace to generate the briefing for
     * @param  Briefing  $briefing  The briefing template to use
     * @param  User  $user  The user requesting the generation
     * @param  array<string, mixed>  $parameters  Parameters for the briefing
     * @return BriefingGeneration The created generation record
     */
    public function handle(
        Workspace $workspace,
        Briefing $briefing,
        User $user,
        array $parameters = [],
    ): BriefingGeneration {
        return DB::transaction(function () use ($workspace, $briefing, $user, $parameters): BriefingGeneration {
            $generation = BriefingGeneration::create([
                'workspace_id' => $workspace->id,
                'briefing_id' => $briefing->id,
                'generated_by_id' => $user->id,
                'parameters' => $parameters,
                'status' => BriefingGenerationStatus::Pending,
                'progress' => 0,
            ]);

            ProcessBriefingGeneration::dispatch($generation);

            return $generation;
        });
    }
}
