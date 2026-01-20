<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommandRunStatus;
use App\Enums\CommandType;
use App\Models\CommandRun;
use App\Models\Repository;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommandRun>
 */
final class CommandRunFactory extends Factory
{
    /**
     * @use RefreshOnCreate<CommandRun>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workspace = Workspace::factory();

        return [
            'workspace_id' => $workspace,
            'repository_id' => Repository::factory()->state(fn (): array => [
                'workspace_id' => $workspace,
            ]),
            'initiated_by_id' => null,
            'external_reference' => sprintf('github:comment:%d', fake()->randomNumber(8)),
            'github_comment_id' => fake()->randomNumber(8),
            'issue_number' => fake()->randomNumber(4),
            'is_pull_request' => fake()->boolean(),
            'command_type' => CommandType::Explain,
            'query' => fake()->sentence(),
            'status' => CommandRunStatus::Queued,
            'started_at' => null,
            'completed_at' => null,
            'duration_seconds' => null,
            'response' => null,
            'context_snapshot' => null,
            'metrics' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Set the command run as in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommandRunStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    /**
     * Set the command run as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommandRunStatus::Completed,
            'started_at' => now()->subSeconds(15),
            'completed_at' => now(),
            'duration_seconds' => 15,
            'response' => [
                'answer' => 'This is a sample response from the agent.',
                'sources' => ['app/Models/User.php'],
            ],
        ]);
    }

    /**
     * Set the command run as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommandRunStatus::Failed,
            'started_at' => now()->subSeconds(5),
            'completed_at' => now(),
            'duration_seconds' => 5,
            'metadata' => [
                'error' => 'Failed to execute command',
            ],
        ]);
    }

    /**
     * Set a specific command type.
     */
    public function withCommandType(CommandType $commandType): static
    {
        return $this->state(fn (array $attributes): array => [
            'command_type' => $commandType,
        ]);
    }

    /**
     * Set the command as coming from a pull request.
     */
    public function fromPullRequest(?int $prNumber = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_pull_request' => true,
            'issue_number' => $prNumber ?? fake()->randomNumber(4),
        ]);
    }

    /**
     * Set the repository for the command run.
     */
    public function forRepository(Repository $repository): static
    {
        return $this->state(fn (array $attributes): array => [
            'repository_id' => $repository->id,
            'workspace_id' => $repository->workspace_id,
        ]);
    }
}
