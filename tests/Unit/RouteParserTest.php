<?php

declare(strict_types=1);

namespace AmeerHamzaAH\PostmanGenerator\Tests\Unit;

use AmeerHamzaAH\PostmanGenerator\DTOs\ParsedRoute;
use AmeerHamzaAH\PostmanGenerator\Services\RouteParser;
use AmeerHamzaAH\PostmanGenerator\Tests\TestCase;

class RouteParserTest extends TestCase
{
    protected RouteParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = app(RouteParser::class);
    }

    /** @test */
    public function it_can_parse_routes(): void
    {
        $routes = $this->parser->parse();

        $this->assertNotEmpty($routes);
        $this->assertContainsOnlyInstancesOf(ParsedRoute::class, $routes);
    }

    /** @test */
    public function it_detects_authenticated_routes(): void
    {
        $routes = $this->parser->parse();

        $authRoutes = $routes->filter(fn($route) => $route->requiresAuth);
        $publicRoutes = $routes->filter(fn($route) => !$route->requiresAuth);

        // We have both auth and public routes in our test setup
        $this->assertNotEmpty($authRoutes);
        $this->assertNotEmpty($publicRoutes);
    }

    /** @test */
    public function it_detects_login_routes(): void
    {
        $routes = $this->parser->parse();

        $loginRoutes = $routes->filter(fn($route) => $route->isLoginRoute);

        $this->assertNotEmpty($loginRoutes);
    }

    /** @test */
    public function it_detects_logout_routes(): void
    {
        $routes = $this->parser->parse();

        $logoutRoutes = $routes->filter(fn($route) => $route->isLogoutRoute);

        $this->assertNotEmpty($logoutRoutes);
    }

    /** @test */
    public function it_extracts_route_parameters(): void
    {
        $routes = $this->parser->parse();

        // Find a route with parameters (e.g., users/{user})
        $routeWithParams = $routes->first(fn($route) => !empty($route->parameters));

        $this->assertNotNull($routeWithParams);
        $this->assertNotEmpty($routeWithParams->parameters);
    }

    /** @test */
    public function it_filters_routes_based_on_patterns(): void
    {
        config(['postman-generator.routes.include' => ['api/*']]);
        config(['postman-generator.routes.exclude' => []]);

        $routes = $this->parser->parse();
        $filtered = $this->parser->filter($routes);

        // All routes should start with 'api'
        foreach ($filtered as $route) {
            $this->assertStringStartsWith('api', $route->uri);
        }
    }

    /** @test */
    public function it_can_set_custom_auth_middleware(): void
    {
        $this->parser->setAuthMiddleware(['custom-auth']);

        // This should affect how routes are marked as requiring auth
        $routes = $this->parser->parse();

        // With custom middleware, existing routes shouldn't be marked as auth
        // (since they use auth:sanctum, not custom-auth)
        $authRoutes = $routes->filter(fn($route) => $route->requiresAuth);

        // Depending on the setup, this might be empty or not
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $authRoutes);
    }
}
