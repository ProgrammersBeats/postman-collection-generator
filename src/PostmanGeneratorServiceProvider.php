<?php

declare(strict_types=1);

namespace YourVendor\PostmanGenerator;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use YourVendor\PostmanGenerator\Commands\GeneratePostmanCollectionCommand;
use YourVendor\PostmanGenerator\Contracts\CollectionGeneratorInterface;
use YourVendor\PostmanGenerator\Contracts\RouteParserInterface;
use YourVendor\PostmanGenerator\Http\Controllers\ApiDocumentationController;
use YourVendor\PostmanGenerator\Services\CollectionGenerator;
use YourVendor\PostmanGenerator\Services\RouteParser;

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

        // Register the API documentation web route
        $this->registerDocumentationRoute();

        if ($this->app->runningInConsole()) {
            $this->commands([
                GeneratePostmanCollectionCommand::class,
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
     *
     * This route serves a beautiful browser-based documentation page
     * that automatically discovers all registered routes from every
     * route file (api.php, custom files registered in bootstrap/app.php, etc.).
     */
    protected function registerDocumentationRoute(): void
    {
        if (!config('postman-generator.documentation.enabled', true)) {
            return;
        }

        $routePath = config('postman-generator.documentation.route', 'api-documentation');
        $middleware = config('postman-generator.documentation.middleware', ['web']);

        Route::middleware($middleware)
            ->get($routePath, ApiDocumentationController::class)
            ->name('api.documentation');
    }
}
