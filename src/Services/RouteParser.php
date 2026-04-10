<?php

declare(strict_types=1);

namespace YourVendor\PostmanGenerator\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use YourVendor\PostmanGenerator\Contracts\RouteParserInterface;
use YourVendor\PostmanGenerator\DTOs\ParsedRoute;

class RouteParser implements RouteParserInterface
{
    protected array $authMiddleware = ['auth', 'auth:sanctum', 'auth:api', 'auth:web'];
    protected array $loginRoutePatterns = ['login', 'auth.login', 'api.login'];
    protected array $logoutRoutePatterns = ['logout', 'auth.logout', 'api.logout'];

    public function __construct(
        protected Router $router,
    ) {}

    /**
     * Parse all registered routes and return a collection of ParsedRoute DTOs.
     *
     * @return Collection<int, ParsedRoute>
     */
    public function parse(): Collection
    {
        return collect($this->router->getRoutes()->getRoutes())
            ->filter(fn(Route $route) => $this->shouldIncludeRoute($route))
            ->map(fn(Route $route) => $this->parseRoute($route))
            ->values();
    }

    /**
     * Filter routes based on include/exclude patterns.
     *
     * @param Collection<int, ParsedRoute> $routes
     * @return Collection<int, ParsedRoute>
     */
    public function filter(Collection $routes): Collection
    {
        $includePatterns = config('postman-generator.routes.include', ['api/*']);
        $excludePatterns = config('postman-generator.routes.exclude', []);

        return $routes->filter(function (ParsedRoute $route) use ($includePatterns, $excludePatterns) {
            // Check include patterns
            $included = empty($includePatterns) || $this->matchesPatterns($route->uri, $includePatterns);

            // Check exclude patterns
            $excluded = !empty($excludePatterns) && $this->matchesPatterns($route->uri, $excludePatterns);

            return $included && !$excluded;
        });
    }

    /**
     * Check if a route requires authentication.
     */
    public function requiresAuth(ParsedRoute $route): bool
    {
        return $route->requiresAuth;
    }

    /**
     * Check if a route is a login endpoint.
     */
    public function isLoginRoute(ParsedRoute $route): bool
    {
        return $route->isLoginRoute;
    }

    /**
     * Check if a route is a logout endpoint.
     */
    public function isLogoutRoute(ParsedRoute $route): bool
    {
        return $route->isLogoutRoute;
    }

    /**
     * Set authentication middleware patterns.
     */
    public function setAuthMiddleware(array $middleware): self
    {
        $this->authMiddleware = $middleware;
        return $this;
    }

    /**
     * Parse a single Laravel Route into a ParsedRoute DTO.
     */
    protected function parseRoute(Route $route): ParsedRoute
    {
        $controller = null;
        $action = null;

        $actionName = $route->getActionName();
        if ($actionName !== 'Closure' && str_contains($actionName, '@')) {
            [$controller, $action] = explode('@', $actionName);
        } elseif ($actionName !== 'Closure' && class_exists($actionName)) {
            $controller = $actionName;
            $action = '__invoke';
        }

        $middleware = $this->getRouteMiddleware($route);
        $requiresAuth = $this->checkRequiresAuth($middleware);
        $isLoginRoute = $this->checkIsLoginRoute($route);
        $isLogoutRoute = $this->checkIsLogoutRoute($route);

        return new ParsedRoute(
            uri: $route->uri(),
            methods: $route->methods(),
            name: $route->getName(),
            controller: $controller,
            action: $action,
            middleware: $middleware,
            parameters: $this->extractParameters($route),
            description: $this->extractDescription($controller, $action),
            validationRules: $this->extractValidationRules($controller, $action),
            requiresAuth: $requiresAuth,
            isLoginRoute: $isLoginRoute,
            isLogoutRoute: $isLogoutRoute,
            prefix: $this->extractPrefix($route),
            resourceName: $this->extractResourceName($route),
        );
    }

    /**
     * Get all middleware applied to a route.
     */
    protected function getRouteMiddleware(Route $route): array
    {
        $middleware = [];

        // Get middleware from the route definition
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m)) {
                $middleware[] = $m;
            }
        }

        return array_unique($middleware);
    }

    /**
     * Check if the route requires authentication based on middleware.
     */
    protected function checkRequiresAuth(array $middleware): bool
    {
        foreach ($middleware as $m) {
            foreach ($this->authMiddleware as $authM) {
                if ($m === $authM || Str::startsWith($m, $authM . ':')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the route is a login endpoint.
     */
    protected function checkIsLoginRoute(Route $route): bool
    {
        $name = $route->getName() ?? '';
        $uri = $route->uri();
        $action = $route->getActionMethod();

        // Check by route name
        foreach ($this->loginRoutePatterns as $pattern) {
            if (Str::is($pattern, $name) || Str::contains($name, 'login')) {
                return true;
            }
        }

        // Check by URI
        if (Str::contains($uri, 'login') || Str::endsWith($uri, '/login')) {
            return true;
        }

        // Check by action method
        if (in_array($action, ['login', 'authenticate', 'attempt', 'signin'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if the route is a logout endpoint.
     */
    protected function checkIsLogoutRoute(Route $route): bool
    {
        $name = $route->getName() ?? '';
        $uri = $route->uri();
        $action = $route->getActionMethod();

        // Check by route name
        foreach ($this->logoutRoutePatterns as $pattern) {
            if (Str::is($pattern, $name) || Str::contains($name, 'logout')) {
                return true;
            }
        }

        // Check by URI
        if (Str::contains($uri, 'logout') || Str::endsWith($uri, '/logout')) {
            return true;
        }

        // Check by action method
        if (in_array($action, ['logout', 'signout', 'destroy']) && Str::contains($uri, 'auth')) {
            return true;
        }

        return false;
    }

    /**
     * Extract route parameters from URI.
     */
    protected function extractParameters(Route $route): array
    {
        preg_match_all('/\{([^}]+)\}/', $route->uri(), $matches);

        $params = [];
        foreach ($matches[1] as $param) {
            $isOptional = Str::endsWith($param, '?');
            $paramName = rtrim($param, '?');

            $params[$paramName] = [
                'name' => $paramName,
                'required' => !$isOptional,
                'type' => 'string',
            ];

            // Check for route parameter constraints
            if ($constraint = $route->wheres[$paramName] ?? null) {
                $params[$paramName]['pattern'] = $constraint;
            }
        }

        return $params;
    }

    /**
     * Extract PHPDoc description from controller method.
     */
    protected function extractDescription(?string $controller, ?string $action): ?string
    {
        if (!$controller || !$action || !class_exists($controller)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($controller);

            if (!$reflection->hasMethod($action)) {
                return null;
            }

            $method = $reflection->getMethod($action);
            $docComment = $method->getDocComment();

            if (!$docComment) {
                return null;
            }

            // Parse the first line of the PHPDoc (description)
            preg_match('/\/\*\*\s*\n\s*\*\s*(.+)/', $docComment, $matches);

            return $matches[1] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract validation rules from FormRequest or controller.
     */
    protected function extractValidationRules(?string $controller, ?string $action): array
    {
        if (!$controller || !$action || !class_exists($controller)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($controller);

            if (!$reflection->hasMethod($action)) {
                return [];
            }

            $method = $reflection->getMethod($action);
            $parameters = $method->getParameters();

            foreach ($parameters as $param) {
                $type = $param->getType();

                if (!$type || $type->isBuiltin()) {
                    continue;
                }

                $typeName = $type->getName();

                // Check if it's a FormRequest class
                if (class_exists($typeName) && is_subclass_of($typeName, \Illuminate\Foundation\Http\FormRequest::class)) {
                    try {
                        $requestReflection = new ReflectionClass($typeName);

                        if ($requestReflection->hasMethod('rules')) {
                            // Create an instance to get rules
                            $request = $requestReflection->newInstanceWithoutConstructor();
                            $rulesMethod = $requestReflection->getMethod('rules');
                            $rulesMethod->setAccessible(true);

                            // Try to get rules, handling potential errors
                            try {
                                return $rulesMethod->invoke($request);
                            } catch (\Throwable) {
                                // If we can't invoke, try to parse the method body
                                return $this->parseRulesFromSource($requestReflection, 'rules');
                            }
                        }
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }

            return [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Parse rules from source code when reflection fails.
     */
    protected function parseRulesFromSource(ReflectionClass $class, string $methodName): array
    {
        try {
            $method = $class->getMethod($methodName);
            $filename = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            if (!$filename || !$startLine || !$endLine) {
                return [];
            }

            $source = file_get_contents($filename);
            $lines = explode("\n", $source);
            $methodSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

            // Basic parsing - extract array keys
            preg_match_all("/['\"]([^'\"]+)['\"]\s*=>/", $methodSource, $matches);

            $rules = [];
            foreach ($matches[1] as $field) {
                $rules[$field] = 'validation rules defined';
            }

            return $rules;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Extract route prefix.
     */
    protected function extractPrefix(Route $route): ?string
    {
        $prefix = $route->getPrefix();

        if ($prefix) {
            return trim($prefix, '/');
        }

        // Fallback to first URI segment
        $segments = explode('/', trim($route->uri(), '/'));
        return $segments[0] ?? null;
    }

    /**
     * Extract resource name if it's a resource route.
     */
    protected function extractResourceName(Route $route): ?string
    {
        $action = $route->getActionMethod();
        $resourceActions = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

        if (!in_array($action, $resourceActions)) {
            return null;
        }

        // Try to extract from route name
        $name = $route->getName();
        if ($name && Str::contains($name, '.')) {
            $parts = explode('.', $name);
            array_pop($parts); // Remove the action part
            return implode('.', $parts);
        }

        // Try to extract from controller name
        $controller = $route->getControllerClass();
        if ($controller) {
            $baseName = class_basename($controller);
            return Str::kebab(str_replace('Controller', '', $baseName));
        }

        return null;
    }

    /**
     * Check if route should be included based on basic filters.
     */
    protected function shouldIncludeRoute(Route $route): bool
    {
        // Skip routes without URI
        if (empty($route->uri())) {
            return false;
        }

        // Skip closure-based routes with no name
        $actionName = $route->getActionName();
        if ($actionName === 'Closure' && !$route->getName()) {
            return false;
        }

        return true;
    }

    /**
     * Check if URI matches any of the given patterns.
     */
    protected function matchesPatterns(string $uri, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }
}
