<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Support;

final readonly class SentinelConfigGitHubResponseParser
{
    /**
     * Create a new parser instance.
     */
    public function __construct(private SentinelConfigFetchResultFactory $resultFactory) {}

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

        return $this->resultFactory->found($response, null, null);
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
                return $this->resultFactory->found(null, $sha, 'Failed to decode base64 content');
            }

            return $this->resultFactory->found($content, $sha, null);
        }

        return $this->resultFactory->found(null, null, 'Unexpected response format from GitHub API');
    }
}
