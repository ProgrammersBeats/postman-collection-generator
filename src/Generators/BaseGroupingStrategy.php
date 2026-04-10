<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Generators;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ProgrammersBeats\PostmanGenerator\Contracts\GroupingStrategyInterface;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;

abstract class BaseGroupingStrategy implements GroupingStrategyInterface
{
    /**
     * Get the strategy name.
     */
    abstract public function getName(): string;

    /**
     * Get the strategy description.
     */
    abstract public function getDescription(): string;

    /**
     * Get the grouping key for a route.
     */
    abstract protected function getGroupKey(ParsedRoute $route): string;

    /**
     * Group routes according to the strategy.
     *
     * @param Collection<int, ParsedRoute> $routes
     * @return array<string, array<int, ParsedRoute>>
     */
    public function group(Collection $routes): array
    {
        $grouped = [];

        foreach ($routes as $route) {
            $key = $this->getGroupKey($route);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }

            $grouped[$key][] = $route;
        }

        // Sort groups alphabetically
        ksort($grouped);

        // Sort routes within each group by URI
        foreach ($grouped as $key => $groupRoutes) {
            usort($groupRoutes, fn($a, $b) => strcmp($a->uri, $b->uri));
            $grouped[$key] = $groupRoutes;
        }

        return $grouped;
    }

    /**
     * Get the folder name for a route group.
     */
    public function getFolderName(string $key, array $routes): string
    {
        return Str::title(str_replace(['-', '_', '.'], ' ', $key));
    }
}
