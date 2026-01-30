<?php

declare(strict_types=1);

namespace App\Services\Context\TokenCounting;

use App\Services\Context\Contracts\TokenCounter;
use Throwable;
use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

/**
 * Token counter using the OpenAI-compatible tiktoken encoder.
 */
final readonly class OpenAiTokenCounter implements TokenCounter
{
    private const string DEFAULT_MODEL = 'gpt-4o';

    /**
     * Create a new OpenAI token counter.
     */
    public function __construct(private EncoderProvider $encoderProvider = new EncoderProvider())
    {
        $this->configureCacheDir();
    }

    /**
     * {@inheritdoc}
     */
    public function countTextTokens(string $text, TokenCounterContext $context): int
    {
        try {
            $encoder = $this->resolveEncoder($context->model);

            return count($encoder->encode($text));
        } catch (Throwable) {
            return new HeuristicTokenCounter()->countTextTokens($text, $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function countMessageTokens(?string $systemPrompt, string $userPrompt, TokenCounterContext $context): int
    {
        $combined = $systemPrompt === null
            ? $userPrompt
            : $systemPrompt."\n".$userPrompt;

        return $this->countTextTokens($combined, $context);
    }

    /**
     * Resolve a tokenizer encoder for the given model.
     */
    private function resolveEncoder(?string $model): Encoder
    {
        if ($model === null || $model === '') {
            $model = self::DEFAULT_MODEL;
        }

        return $this->encoderProvider->getForModel($model);
    }

    /**
     * Configure the cache directory for tokenizer vocab files.
     */
    private function configureCacheDir(): void
    {
        $cacheDir = storage_path('framework/cache/tiktoken');
        if ($cacheDir === '') {
            $cacheDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tiktoken';
        }

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $this->encoderProvider->setVocabCache($cacheDir);
    }
}
