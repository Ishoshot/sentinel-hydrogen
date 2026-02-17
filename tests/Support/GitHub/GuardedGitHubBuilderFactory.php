<?php

declare(strict_types=1);

namespace Tests\Support\GitHub;

use Github\HttpClient\Builder;
use GrahamCampbell\GitHub\HttpClient\BuilderFactory;
use Http\Client\Common\Plugin;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class GuardedGitHubBuilderFactory extends BuilderFactory
{
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        private readonly Plugin $guardPlugin,
    ) {
        parent::__construct($httpClient, $requestFactory, $streamFactory);
    }

    public function make(): Builder
    {
        $builder = parent::make();
        $builder->addPlugin($this->guardPlugin);

        return $builder;
    }
}
