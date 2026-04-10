<?php

declare(strict_types=1);

namespace YourVendor\PostmanGenerator\DTOs;

use Illuminate\Support\Str;

class ParsedRoute
{
    /**
     * @param array<string> $methods
     * @param array<string> $middleware
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $validationRules
     */
    public function __construct(
        public readonly string $uri,
        public readonly array $methods,
        public readonly ?string $name,
        public readonly ?string $controller,
        public readonly ?string $action,
        public readonly array $middleware,
        public readonly array $parameters,
        public readonly ?string $description,
        public readonly array $validationRules,
        public readonly bool $requiresAuth,
        public readonly bool $isLoginRoute,
        public readonly bool $isLogoutRoute,
        public readonly ?string $prefix,
        public readonly ?string $resourceName,
    ) {}

    /**
     * Get the primary HTTP method (excluding HEAD).
     */
    public function getPrimaryMethod(): string
    {
        $methods = array_filter($this->methods, fn($m) => $m !== 'HEAD');
        return reset($methods) ?: 'GET';
    }

    /**
     * Get the controller class name without namespace.
     */
    public function getControllerName(): ?string
    {
        if (!$this->controller) {
            return null;
        }

        return class_basename($this->controller);
    }

    /**
     * Get a human-readable name for the route.
     */
    public function getDisplayName(): string
    {
        if ($this->name) {
            return Str::title(str_replace(['.', '_', '-'], ' ', $this->name));
        }

        if ($this->action && $this->controller) {
            return Str::title($this->action) . ' ' . Str::title(
                str_replace('Controller', '', $this->getControllerName() ?? '')
            );
        }

        return Str::title(str_replace(['/', '-', '_'], ' ', $this->uri));
    }

    /**
     * Get the route prefix (first URI segment).
     */
    public function getPrefix(): string
    {
        if ($this->prefix) {
            return $this->prefix;
        }

        $segments = explode('/', trim($this->uri, '/'));
        return $segments[0] ?? 'root';
    }

    /**
     * Check if route is a resource route.
     */
    public function isResourceRoute(): bool
    {
        return $this->resourceName !== null ||
            in_array($this->action, ['index', 'store', 'show', 'update', 'destroy', 'create', 'edit']);
    }

    /**
     * Get route parameters as Postman-formatted array.
     */
    public function getPostmanParameters(): array
    {
        $params = [];

        // Extract path parameters from URI
        preg_match_all('/\{([^}]+)\}/', $this->uri, $matches);

        foreach ($matches[1] as $param) {
            $isOptional = Str::endsWith($param, '?');
            $paramName = rtrim($param, '?');

            $params[] = [
                'key' => $paramName,
                'value' => '{{' . $paramName . '}}',
                'description' => $this->getParameterDescription($paramName),
                'required' => !$isOptional,
            ];
        }

        return $params;
    }

    /**
     * Get description for a parameter.
     */
    protected function getParameterDescription(string $param): string
    {
        if (isset($this->validationRules[$param])) {
            $rules = $this->validationRules[$param];
            if (is_array($rules)) {
                return implode(', ', array_map(fn($r) => is_string($r) ? $r : get_class($r), $rules));
            }
            return (string) $rules;
        }

        // Common parameter descriptions
        $commonDescriptions = [
            'id' => 'Resource ID',
            'uuid' => 'Resource UUID',
            'slug' => 'Resource slug',
            'user' => 'User ID',
            'user_id' => 'User ID',
            'post' => 'Post ID',
            'post_id' => 'Post ID',
        ];

        return $commonDescriptions[$param] ?? 'Parameter value';
    }

    /**
     * Convert URI to Postman-compatible path.
     */
    public function getPostmanUri(): string
    {
        // Convert Laravel route parameters to Postman variables
        return preg_replace('/\{([^}?]+)\??}/', ':$1', $this->uri);
    }

    /**
     * Get the middleware as a formatted string.
     */
    public function getMiddlewareString(): string
    {
        return implode(', ', $this->middleware);
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'uri' => $this->uri,
            'methods' => $this->methods,
            'name' => $this->name,
            'controller' => $this->controller,
            'action' => $this->action,
            'middleware' => $this->middleware,
            'parameters' => $this->parameters,
            'description' => $this->description,
            'validation_rules' => $this->validationRules,
            'requires_auth' => $this->requiresAuth,
            'is_login_route' => $this->isLoginRoute,
            'is_logout_route' => $this->isLogoutRoute,
            'prefix' => $this->prefix,
            'resource_name' => $this->resourceName,
        ];
    }
}
