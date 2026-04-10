<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Generators;

use Illuminate\Support\Str;
use ReflectionClass;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;

class TagGroupingStrategy extends BaseGroupingStrategy
{
    public function getName(): string
    {
        return 'tag';
    }

    public function getDescription(): string
    {
        return 'Group routes by PHPDoc @tag annotation in controller (e.g., @tag Authentication)';
    }

    protected function getGroupKey(ParsedRoute $route): string
    {
        if (!$route->controller || !class_exists($route->controller)) {
            return 'untagged';
        }

        try {
            $reflection = new ReflectionClass($route->controller);

            // First check method-level tag
            if ($route->action && $reflection->hasMethod($route->action)) {
                $methodDoc = $reflection->getMethod($route->action)->getDocComment();
                if ($methodDoc && $tag = $this->extractTag($methodDoc)) {
                    return Str::kebab($tag);
                }
            }

            // Fall back to class-level tag
            $classDoc = $reflection->getDocComment();
            if ($classDoc && $tag = $this->extractTag($classDoc)) {
                return Str::kebab($tag);
            }
        } catch (\Throwable) {
            // Ignore reflection errors
        }

        // Fallback to controller name
        if ($route->controller) {
            $controllerName = $route->getControllerName();
            if ($controllerName) {
                return Str::kebab(str_replace('Controller', '', $controllerName));
            }
        }

        return 'untagged';
    }

    /**
     * Extract @tag value from PHPDoc comment.
     */
    protected function extractTag(string $docComment): ?string
    {
        // Match @tag annotation
        if (preg_match('/@tag\s+([^\n\r*]+)/i', $docComment, $matches)) {
            return trim($matches[1]);
        }

        // Also try @group annotation (common in API documentation)
        if (preg_match('/@group\s+([^\n\r*]+)/i', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public function getFolderName(string $key, array $routes): string
    {
        if ($key === 'untagged') {
            return 'Untagged Routes';
        }

        return Str::title(str_replace(['-', '_'], ' ', $key));
    }
}
