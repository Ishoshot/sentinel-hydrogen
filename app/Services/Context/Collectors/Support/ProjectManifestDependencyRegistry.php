<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

final class ProjectManifestDependencyRegistry
{
    /**
     * Known frameworks for categorization.
     *
     * @var array<string, array<string, string>>
     */
    private const array KNOWN_FRAMEWORKS = [
        'php' => [
            'laravel/framework' => 'Laravel',
            'symfony/symfony' => 'Symfony',
            'slim/slim' => 'Slim',
            'cakephp/cakephp' => 'CakePHP',
            'yiisoft/yii2' => 'Yii',
        ],
        'javascript' => [
            'react' => 'React',
            'vue' => 'Vue.js',
            'next' => 'Next.js',
            'nuxt' => 'Nuxt',
            '@angular/core' => 'Angular',
            'svelte' => 'Svelte',
            'express' => 'Express',
            'fastify' => 'Fastify',
            '@nestjs/core' => 'NestJS',
        ],
        'python' => [
            'django' => 'Django',
            'flask' => 'Flask',
            'fastapi' => 'FastAPI',
            'tornado' => 'Tornado',
        ],
        'ruby' => [
            'rails' => 'Ruby on Rails',
            'sinatra' => 'Sinatra',
            'hanami' => 'Hanami',
        ],
        'rust' => [
            'actix-web' => 'Actix Web',
            'rocket' => 'Rocket',
            'axum' => 'Axum',
            'warp' => 'Warp',
        ],
        'go' => [
            'github.com/gin-gonic/gin' => 'Gin',
            'github.com/labstack/echo' => 'Echo',
            'github.com/gofiber/fiber' => 'Fiber',
        ],
    ];

    /**
     * @return array{frameworks: array<int, array{name: string, version: string}>, dependencies: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function emptyResult(): array
    {
        return [
            'frameworks' => [],
            'dependencies' => [],
        ];
    }

    /**
     * @param  array{frameworks: array<int, array{name: string, version: string}>, dependencies: array<int, array{name: string, version: string, dev?: bool}>}  $result
     * @param  array{name: string, version: string, dev?: bool}  $dependency
     */
    public function addDependencyWithFrameworkDetection(array &$result, array $dependency, string $language, bool $isDev = false): void
    {
        if ($isDev) {
            $dependency['dev'] = true;
        }

        if (isset(self::KNOWN_FRAMEWORKS[$language][$dependency['name']])) {
            $result['frameworks'][] = [
                'name' => self::KNOWN_FRAMEWORKS[$language][$dependency['name']],
                'version' => $dependency['version'],
            ];
        }

        $result['dependencies'][] = $dependency;
    }
}
