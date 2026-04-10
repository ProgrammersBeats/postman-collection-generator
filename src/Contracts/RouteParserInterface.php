<?php

declare(strict_types=1);

namespace YourVendor\PostmanGenerator\Contracts;

use Illuminate\Support\Collection;
use YourVendor\PostmanGenerator\DTOs\ParsedRoute;

interface RouteParserInterface
{
    /**
     * Parse all registered routes and return a collection of ParsedRoute DTOs.
     *
     * @return Collection<int, ParsedRoute>
     */
    public function parse(): Collection;

    /**
     * Filter routes based on include/exclude patterns.
     *
     * @param Collection<int, ParsedRoute> $routes
     * @return Collection<int, ParsedRoute>
     */
    public function filter(Collection $routes): Collection;

    /**
     * Check if a route requires authentication.
     */
    public function requiresAuth(ParsedRoute $route): bool;

    /**
     * Check if a route is a login endpoint.
     */
    public function isLoginRoute(ParsedRoute $route): bool;

    /**
     * Check if a route is a logout endpoint.
     */
    public function isLogoutRoute(ParsedRoute $route): bool;
}
