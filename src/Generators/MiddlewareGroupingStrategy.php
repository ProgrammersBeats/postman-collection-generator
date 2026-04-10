<?php

declare(strict_types=1);

namespace AmeerHamzaAH\PostmanGenerator\Generators;

use Illuminate\Support\Str;
use AmeerHamzaAH\PostmanGenerator\DTOs\ParsedRoute;

class MiddlewareGroupingStrategy extends BaseGroupingStrategy
{
    public function getName(): string
    {
        return 'middleware';
    }

    public function getDescription(): string
    {
        return 'Group routes by middleware (e.g., auth:sanctum routes together, guest routes together)';
    }

    protected function getGroupKey(ParsedRoute $route): string
    {
        if (empty($route->middleware)) {
            return 'no-middleware';
        }

        // Prioritize auth-related middleware for grouping
        $authMiddleware = ['auth', 'auth:sanctum', 'auth:api', 'auth:web', 'guest'];

        foreach ($authMiddleware as $auth) {
            if (in_array($auth, $route->middleware)) {
                return $auth;
            }
        }

        // Use the first middleware as the group key
        $firstMiddleware = reset($route->middleware);

        // Handle middleware with parameters (e.g., throttle:60,1)
        if (Str::contains($firstMiddleware, ':')) {
            return Str::before($firstMiddleware, ':');
        }

        return $firstMiddleware;
    }

    public function getFolderName(string $key, array $routes): string
    {
        $names = [
            'no-middleware' => 'Public Routes (No Middleware)',
            'auth' => 'Authenticated Routes',
            'auth:sanctum' => 'Sanctum Protected Routes',
            'auth:api' => 'API Authenticated Routes',
            'auth:web' => 'Web Authenticated Routes',
            'guest' => 'Guest Only Routes',
            'throttle' => 'Rate Limited Routes',
            'verified' => 'Email Verified Routes',
            'signed' => 'Signed URL Routes',
            'api' => 'API Routes',
            'web' => 'Web Routes',
        ];

        return $names[$key] ?? Str::title(str_replace(['-', '_', ':'], ' ', $key)) . ' Routes';
    }
}
