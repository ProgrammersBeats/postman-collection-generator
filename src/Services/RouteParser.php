<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ProgrammersBeats\PostmanGenerator\Contracts\RouteParserInterface;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;

class RouteParser implements RouteParserInterface
{
    protected array $authMiddleware = ['auth', 'auth:sanctum', 'auth:api', 'auth:web'];
    protected array $loginRoutePatterns = ['login', 'auth.login', 'api.login'];
    protected array $logoutRoutePatterns = ['logout', 'auth.logout', 'api.logout'];
    protected RouteFileDetector $fileDetector;

    public function __construct(
        protected Router $router,
    ) {
        $this->fileDetector = new RouteFileDetector();
    }

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
            $included = empty($includePatterns) || $this->matchesPatterns($route->uri, $includePatterns);
            $excluded = !empty($excludePatterns) && $this->matchesPatterns($route->uri, $excludePatterns);

            return $included && !$excluded;
        });
    }

    public function requiresAuth(ParsedRoute $route): bool
    {
        return $route->requiresAuth;
    }

    public function isLoginRoute(ParsedRoute $route): bool
    {
        return $route->isLoginRoute;
    }

    public function isLogoutRoute(ParsedRoute $route): bool
    {
        return $route->isLogoutRoute;
    }

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

        // Detect which route file this route belongs to (controller + URI matching)
        $sourceFileInfo = $this->fileDetector->getSourceFile($controller, $route->uri());

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
            responseResourceClass: $this->extractResponseResource($controller, $action),
            modelClass: $this->extractModelClass($controller, $action),
            rateLimitInfo: $this->extractRateLimitInfo($middleware),
            sourceFile: $sourceFileInfo['relative'] ?? null,
            sourceFileName: $sourceFileInfo['name'] ?? null,
        );
    }

    /**
     * Extract the API Resource class returned by the controller method.
     */
    protected function extractResponseResource(?string $controller, ?string $action): ?string
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

            // Check return type
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType && !$returnType->isBuiltin()) {
                $typeName = $returnType->getName();
                if (class_exists($typeName) && is_subclass_of($typeName, \Illuminate\Http\Resources\Json\JsonResource::class)) {
                    return $typeName;
                }
            }

            // Scan method body for Resource usage patterns
            $source = $this->getMethodSource($method);
            if ($source) {
                // Match: new XxxResource(...) or XxxResource::collection(...)
                if (preg_match('/new\s+(\w+Resource)\s*\(/', $source, $matches)) {
                    $resourceClass = $this->resolveClassFromImports($method, $matches[1]);
                    if ($resourceClass) {
                        return $resourceClass;
                    }
                }

                if (preg_match('/(\w+Resource)::collection\s*\(/', $source, $matches)) {
                    $resourceClass = $this->resolveClassFromImports($method, $matches[1]);
                    if ($resourceClass) {
                        return $resourceClass;
                    }
                }
            }
        } catch (\Throwable) {
            // Silently ignore
        }

        return null;
    }

    /**
     * Extract the Eloquent Model class from controller method parameters.
     */
    protected function extractModelClass(?string $controller, ?string $action): ?string
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

            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $typeName = $type->getName();

                // Check if it's an Eloquent Model
                if (class_exists($typeName) && is_subclass_of($typeName, \Illuminate\Database\Eloquent\Model::class)) {
                    return $typeName;
                }
            }

            // Fallback: infer model from controller name (UserController -> App\Models\User)
            $controllerBaseName = class_basename($controller);
            $modelName = str_replace('Controller', '', $controllerBaseName);
            $possibleModels = [
                "App\\Models\\{$modelName}",
                "App\\{$modelName}",
            ];

            foreach ($possibleModels as $model) {
                if (class_exists($model) && is_subclass_of($model, \Illuminate\Database\Eloquent\Model::class)) {
                    return $model;
                }
            }
        } catch (\Throwable) {
            // Silently ignore
        }

        return null;
    }

    /**
     * Extract rate limit info from throttle middleware.
     */
    protected function extractRateLimitInfo(array $middleware): ?string
    {
        foreach ($middleware as $m) {
            if (Str::startsWith($m, 'throttle:')) {
                $params = substr($m, 9); // Remove 'throttle:'
                $parts = explode(',', $params);

                if (count($parts) >= 2 && is_numeric($parts[0])) {
                    return "{$parts[0]} requests per {$parts[1]} minute(s)";
                } elseif (count($parts) === 1 && is_numeric($parts[0])) {
                    return "{$parts[0]} requests per minute";
                } else {
                    return "Rate limited: {$params}";
                }
            }
        }

        return null;
    }

    /**
     * Get the source code of a method.
     */
    protected function getMethodSource(ReflectionMethod $method): ?string
    {
        try {
            $filename = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            if (!$filename || !$startLine || !$endLine) {
                return null;
            }

            $source = file_get_contents($filename);
            $lines = explode("\n", $source);

            return implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve a short class name to fully qualified name using the file's use statements.
     */
    protected function resolveClassFromImports(ReflectionMethod $method, string $shortName): ?string
    {
        try {
            $filename = $method->getDeclaringClass()->getFileName();
            if (!$filename) {
                return null;
            }

            $source = file_get_contents($filename);

            // Find 'use' statement for this class
            if (preg_match('/use\s+([\\\\A-Za-z0-9_]+\\\\' . preg_quote($shortName) . ')\s*;/', $source, $matches)) {
                $fqcn = $matches[1];
                if (class_exists($fqcn)) {
                    return $fqcn;
                }
            }

            // Try namespace + shortName
            $namespace = $method->getDeclaringClass()->getNamespaceName();
            $fqcn = $namespace . '\\' . $shortName;
            if (class_exists($fqcn)) {
                return $fqcn;
            }
        } catch (\Throwable) {
            // Silently ignore
        }

        return null;
    }

    protected function getRouteMiddleware(Route $route): array
    {
        $middleware = [];

        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m)) {
                $middleware[] = $m;
            }
        }

        return array_unique($middleware);
    }

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

    protected function checkIsLoginRoute(Route $route): bool
    {
        $name = $route->getName() ?? '';
        $uri = $route->uri();
        $action = $route->getActionMethod();

        foreach ($this->loginRoutePatterns as $pattern) {
            if (Str::is($pattern, $name) || Str::contains($name, 'login')) {
                return true;
            }
        }

        if (Str::contains($uri, 'login') || Str::endsWith($uri, '/login')) {
            return true;
        }

        if (in_array($action, ['login', 'authenticate', 'attempt', 'signin'])) {
            return true;
        }

        return false;
    }

    protected function checkIsLogoutRoute(Route $route): bool
    {
        $name = $route->getName() ?? '';
        $uri = $route->uri();
        $action = $route->getActionMethod();

        foreach ($this->logoutRoutePatterns as $pattern) {
            if (Str::is($pattern, $name) || Str::contains($name, 'logout')) {
                return true;
            }
        }

        if (Str::contains($uri, 'logout') || Str::endsWith($uri, '/logout')) {
            return true;
        }

        if (in_array($action, ['logout', 'signout', 'destroy']) && Str::contains($uri, 'auth')) {
            return true;
        }

        return false;
    }

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

            if ($constraint = $route->wheres[$paramName] ?? null) {
                $params[$paramName]['pattern'] = $constraint;
            }
        }

        return $params;
    }

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

            preg_match('/\/\*\*\s*\n\s*\*\s*(.+)/', $docComment, $matches);

            return $matches[1] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

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

                if (class_exists($typeName) && is_subclass_of($typeName, \Illuminate\Foundation\Http\FormRequest::class)) {
                    try {
                        $requestReflection = new ReflectionClass($typeName);

                        if ($requestReflection->hasMethod('rules')) {
                            $request = $requestReflection->newInstanceWithoutConstructor();
                            $rulesMethod = $requestReflection->getMethod('rules');
                            $rulesMethod->setAccessible(true);

                            try {
                                return $rulesMethod->invoke($request);
                            } catch (\Throwable) {
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

    protected function extractPrefix(Route $route): ?string
    {
        $prefix = $route->getPrefix();

        if ($prefix) {
            return trim($prefix, '/');
        }

        $segments = explode('/', trim($route->uri(), '/'));
        return $segments[0] ?? null;
    }

    protected function extractResourceName(Route $route): ?string
    {
        $action = $route->getActionMethod();
        $resourceActions = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

        if (!in_array($action, $resourceActions)) {
            return null;
        }

        $name = $route->getName();
        if ($name && Str::contains($name, '.')) {
            $parts = explode('.', $name);
            array_pop($parts);
            return implode('.', $parts);
        }

        $controller = $route->getControllerClass();
        if ($controller) {
            $baseName = class_basename($controller);
            return Str::kebab(str_replace('Controller', '', $baseName));
        }

        return null;
    }

    protected function shouldIncludeRoute(Route $route): bool
    {
        if (empty($route->uri())) {
            return false;
        }

        $actionName = $route->getActionName();
        if ($actionName === 'Closure' && !$route->getName()) {
            return false;
        }

        return true;
    }

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
