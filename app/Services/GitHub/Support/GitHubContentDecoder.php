<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

/**
 * Decodes GitHub content API responses into plain text.
 */
final readonly class GitHubContentDecoder
{
    /**
     * Decode raw API payload content, handling base64 encoded responses.
     */
    public function decode(mixed $response): ?string
    {
        if (is_string($response)) {
            return $response;
        }

        if (! is_array($response)) {
            return null;
        }

        if (! isset($response['content']) || ! is_string($response['content'])) {
            return null;
        }

        $content = $response['content'];
        $encoding = $response['encoding'] ?? 'base64';

        if ($encoding !== 'base64') {
            return $content;
        }

        $decoded = base64_decode(str_replace("\n", '', $content), true);

        return $decoded !== false ? $decoded : null;
    }
}
