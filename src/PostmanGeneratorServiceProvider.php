<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ProgrammersBeats\PostmanGenerator\Commands\DiffCollectionCommand;
use ProgrammersBeats\PostmanGenerator\Commands\GeneratePostmanCollectionCommand;
use ProgrammersBeats\PostmanGenerator\Contracts\CollectionGeneratorInterface;
use ProgrammersBeats\PostmanGenerator\Contracts\RouteParserInterface;
use ProgrammersBeats\PostmanGenerator\Http\Controllers\ApiDocumentationController;
use ProgrammersBeats\PostmanGenerator\Services\CollectionGenerator;
use ProgrammersBeats\PostmanGenerator\Services\RouteParser;

class PostmanGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/postman-generator.php',
            'postman-generator'
        );

        $this->app->bind(RouteParserInterface::class, RouteParser::class);
        $this->app->bind(CollectionGeneratorInterface::class, CollectionGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load views for the documentation page
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'postman-generator');

        // Register the API documentation route using loadRoutesFrom
        $this->registerDocumentationRoute();

        if ($this->app->runningInConsole()) {
            $this->commands([
                GeneratePostmanCollectionCommand::class,
                DiffCollectionCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/postman-generator.php' => config_path('postman-generator.php'),
            ], 'postman-generator-config');

            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/postman-generator'),
            ], 'postman-generator-stubs');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/postman-generator'),
            ], 'postman-generator-views');
        }
    }

    /**
     * Register the API documentation route.
     * Uses booted callback to ensure routes are registered after the app is fully booted.
     */
    protected function registerDocumentationRoute(): void
    {
        if (!config('postman-generator.documentation.enabled', true)) {
            return;
        }

        // Register route after app is booted to avoid 404 in Laravel 12/13
        $this->app->booted(function () {
            $routePath = config('postman-generator.documentation.route', 'api-documentation');
            $middleware = config('postman-generator.documentation.middleware', ['web']);

            Route::middleware($middleware)
                ->get($routePath, ApiDocumentationController::class)
                ->name('api.documentation');
        });
    }
}
