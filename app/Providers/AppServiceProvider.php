<?php

namespace App\Providers;

use App\Services\AI\AiProviderInterface;
use App\Services\AI\GeminiProvider;
use App\Services\AI\OpenAiProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, fn (): AiProviderInterface => match (config('ai.provider')) {
            'openai' => new OpenAiProvider,
            default => new GeminiProvider,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
