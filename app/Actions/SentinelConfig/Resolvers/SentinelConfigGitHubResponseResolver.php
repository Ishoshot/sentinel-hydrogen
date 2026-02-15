<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Resolvers;

final readonly class SentinelConfigGitHubResponseResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(private SentinelConfigFetchResultResolver $resultResolver) {}

    /**
     * Parse a GitHub file contents response into a config fetch result.
     *
     * @param  array<string, mixed>|string  $response
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function parse(array|string $response): array
    {
        if (is_array($response)) {
            return $this->parseArrayResponse($response);
        }

        return $this->resultResolver->found($response, null, null);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    private function parseArrayResponse(array $response): array
    {
        $responseContent = $response['content'] ?? null;
        $responseEncoding = $response['encoding'] ?? null;
        $responseSha = $response['sha'] ?? null;

        if (is_string($responseContent) && is_string($responseEncoding)) {
            $content = $responseEncoding === 'base64'
                ? base64_decode($responseContent, true)
                : $responseContent;

            $sha = is_string($responseSha) ? $responseSha : null;

            if ($content === false) {
                return $this->resultResolver->found(null, $sha, 'Failed to decode base64 content');
            }

            return $this->resultResolver->found($content, $sha, null);
        }

        return $this->resultResolver->found(null, null, 'Unexpected response format from GitHub API');
    }
}
