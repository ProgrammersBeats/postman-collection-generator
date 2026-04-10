<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Generators;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;

class ResourceGroupingStrategy extends BaseGroupingStrategy
{
    protected array $resourceActions = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

    public function getName(): string
    {
        return 'resource';
    }

    public function getDescription(): string
    {
        return 'Group routes by resource (CRUD operations grouped together, e.g., users.index, users.store → "Users")';
    }

    protected function getGroupKey(ParsedRoute $route): string
    {
        // Check if it's a resource route
        if ($route->resourceName) {
            return $route->resourceName;
        }

        // Try to extract resource name from route name
        if ($route->name && Str::contains($route->name, '.')) {
            $parts = explode('.', $route->name);

            // Check if last part is a resource action
            $lastPart = end($parts);
            if (in_array($lastPart, $this->resourceActions)) {
                array_pop($parts);
                return implode('.', $parts);
            }
        }

        // Try to extract from controller
        if ($route->controller && $route->action) {
            if (in_array($route->action, $this->resourceActions)) {
                $controllerName = $route->getControllerName();
                if ($controllerName) {
                    return Str::kebab(str_replace('Controller', '', $controllerName));
                }
            }
        }

        // Fallback to prefix-based grouping
        return $this->extractResourceFromUri($route->uri);
    }

    /**
     * Extract resource name from URI.
     */
    protected function extractResourceFromUri(string $uri): string
    {
        $uri = trim($uri, '/');
        $segments = explode('/', $uri);

        // Find the first non-API, non-version, non-parameter segment
        foreach ($segments as $segment) {
            // Skip common prefixes
            if (preg_match('/^(api|v\d+)$/i', $segment)) {
                continue;
            }

            // Skip parameters
            if (Str::startsWith($segment, '{')) {
                continue;
            }

            return $segment;
        }

        return 'other';
    }

    /**
     * Group routes with resource-specific ordering.
     *
     * @param Collection<int, ParsedRoute> $routes
     * @return array<string, array<int, ParsedRoute>>
     */
    public function group(Collection $routes): array
    {
        $grouped = parent::group($routes);

        // Order routes within each group by resource action priority
        $actionOrder = array_flip($this->resourceActions);

        foreach ($grouped as $key => $groupRoutes) {
            usort($groupRoutes, function ($a, $b) use ($actionOrder) {
                $aOrder = $actionOrder[$a->action] ?? 999;
                $bOrder = $actionOrder[$b->action] ?? 999;

                if ($aOrder === $bOrder) {
                    return strcmp($a->uri, $b->uri);
                }

                return $aOrder - $bOrder;
            });

            $grouped[$key] = $groupRoutes;
        }

        return $grouped;
    }

    public function getFolderName(string $key, array $routes): string
    {
        if ($key === 'other') {
            return 'Other Routes';
        }

        // Convert to plural title case
        $name = str_replace(['-', '_', '.'], ' ', $key);

        return Str::title(Str::plural($name));
    }
}
