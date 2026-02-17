<?php

declare(strict_types=1);

namespace Tests\Support\GitHub;

use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class PreventRealGitHubRequestsPlugin implements Plugin
{
    /**
     * @param  callable(RequestInterface): Promise  $next
     * @param  callable(RequestInterface): Promise  $first
     */
    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        throw new RuntimeException(sprintf(
            'Blocked real GitHub HTTP request in test: %s. Mock GitHubApiServiceContract or fake the job path.',
            (string) $request->getUri(),
        ));
    }
}
