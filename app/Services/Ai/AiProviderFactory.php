<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderInterface;
use InvalidArgumentException;


/**
 * Factory that resolves the correct AI provider based on configuration.
 */
class AiProviderFactory
{
    /**
     * Map of provider keys → concrete class names.
     *
     * @var array<string, class-string<AiProviderInterface>>
     */
    private static array $providers = [
        'stub'       => StubAiProvider::class,
        'openrouter' => OpenRouterProvider::class,
    ];

    /**
     * Resolve and return the configured AI provider instance.
     *
     * @throws InvalidArgumentException
     */
    public static function make(): AiProviderInterface
    {
        $key = strtolower(config('ai.provider', 'stub'));

        if (! isset(self::$providers[$key])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unknown AI provider [%s]. Supported providers: %s',
                    $key,
                    implode(', ', array_keys(self::$providers))
                )
            );
        }

        return app(self::$providers[$key]);
    }

    /**
     * Register a custom provider at runtime.
     *
     * @param string $key
     * @param class-string<AiProviderInterface> $class
     */
    public static function register(string $key, string $class): void
    {
        self::$providers[strtolower($key)] = $class;
    }
}
