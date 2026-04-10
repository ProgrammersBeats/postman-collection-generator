<?php

declare(strict_types=1);

namespace AmeerHamzaAH\PostmanGenerator\Generators;

use Illuminate\Support\Str;
use AmeerHamzaAH\PostmanGenerator\DTOs\ParsedRoute;

class NameGroupingStrategy extends BaseGroupingStrategy
{
    public function getName(): string
    {
        return 'name';
    }

    public function getDescription(): string
    {
        return 'Group routes by route name prefix (e.g., api.users.index → "Api Users")';
    }

    protected function getGroupKey(ParsedRoute $route): string
    {
        if (!$route->name) {
            return 'unnamed';
        }

        // Split route name by dots
        $parts = explode('.', $route->name);

        // Use first one or two parts as group key
        if (count($parts) >= 2) {
            // Skip common prefixes like 'api'
            if (in_array(strtolower($parts[0]), ['api', 'web'])) {
                return $parts[1] ?? $parts[0];
            }
            return $parts[0];
        }

        return $parts[0];
    }

    public function getFolderName(string $key, array $routes): string
    {
        if ($key === 'unnamed') {
            return 'Unnamed Routes';
        }

        return Str::title(str_replace(['-', '_'], ' ', $key));
    }
}
