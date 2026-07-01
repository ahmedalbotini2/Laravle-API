<?php

namespace App\Providers;

use App\Contracts\AiProviderInterface;
use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the AI provider binding in the service container.
 *
 * The binding is a singleton so the same provider instance is reused
 * within a single request lifecycle, avoiding redundant construction.
 */
class AiServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->app->singleton(AiProviderInterface::class, function () {
            return AiProviderFactory::make();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
