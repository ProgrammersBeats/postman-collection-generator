<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Generators;

use Illuminate\Support\Str;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;

class PrefixGroupingStrategy extends BaseGroupingStrategy
{
    public function getName(): string
    {
        return 'prefix';
    }

    public function getDescription(): string
    {
        return 'Group routes by URL prefix with nested folders (e.g., /api/auth/pin → "Auth > Pin")';
    }

    protected function getGroupKey(ParsedRoute $route): string
    {
        // Use the route's prefix for grouping with full path
        if ($route->prefix) {
            $prefix = trim($route->prefix, '/');
            $segments = explode('/', $prefix);

            // Filter out common prefixes like 'api', 'v1', 'v2'
            $filtered = array_values(array_filter($segments, function ($segment) {
                return !preg_match('/^(api|v\d+)$/i', $segment);
            }));

            if (!empty($filtered)) {
                return implode('/', $filtered);
            }
        }

        // Fallback to extracting from URI
        $uri = trim($route->uri, '/');
        $segments = explode('/', $uri);

        foreach ($segments as $segment) {
            // Skip API prefix and version numbers
            if (preg_match('/^(api|v\d+)$/i', $segment)) {
                continue;
            }

            // Skip parameter placeholders
            if (Str::startsWith($segment, '{')) {
                continue;
            }

            return $segment;
        }

        return 'general';
    }

    public function getFolderName(string $key, array $routes): string
    {
        // Check config for custom folder name by full path
        $customNames = config('postman-generator.grouping.folder_names', []);

        if (isset($customNames[$key])) {
            return $customNames[$key];
        }

        // For nested keys, use the last segment
        if (str_contains($key, '/')) {
            $segments = explode('/', $key);
            $lastSegment = end($segments);

            if (isset($customNames[$lastSegment])) {
                return $customNames[$lastSegment];
            }

            return Str::title(str_replace(['-', '_'], ' ', $lastSegment));
        }

        return Str::title(str_replace(['-', '_'], ' ', $key));
    }
}