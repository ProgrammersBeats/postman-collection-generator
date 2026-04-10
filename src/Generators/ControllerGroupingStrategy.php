<?php

declare(strict_types=1);

namespace AmeerHamzaAH\PostmanGenerator\Generators;

use Illuminate\Support\Str;
use AmeerHamzaAH\PostmanGenerator\DTOs\ParsedRoute;

class ControllerGroupingStrategy extends BaseGroupingStrategy
{
    public function getName(): string
    {
        return 'controller';
    }

    public function getDescription(): string
    {
        return 'Group routes by controller class (e.g., UserController → "User")';
    }

    protected function getGroupKey(ParsedRoute $route): string
    {
        if (!$route->controller) {
            return 'other';
        }

        $controllerName = $route->getControllerName();

        if (!$controllerName) {
            return 'other';
        }

        // Remove 'Controller' suffix
        $name = str_replace('Controller', '', $controllerName);

        return Str::kebab($name);
    }

    public function getFolderName(string $key, array $routes): string
    {
        if ($key === 'other') {
            return 'Other Routes';
        }

        // Convert kebab-case to Title Case and append "Controller"
        return Str::title(str_replace(['-', '_'], ' ', $key));
    }
}
