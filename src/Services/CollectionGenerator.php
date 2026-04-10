<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ProgrammersBeats\PostmanGenerator\Contracts\CollectionGeneratorInterface;
use ProgrammersBeats\PostmanGenerator\Contracts\GroupingStrategyInterface;
use ProgrammersBeats\PostmanGenerator\Contracts\RouteParserInterface;
use ProgrammersBeats\PostmanGenerator\DTOs\GeneratorOptions;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;
use ProgrammersBeats\PostmanGenerator\DTOs\PostmanCollection;
use ProgrammersBeats\PostmanGenerator\Generators\ControllerGroupingStrategy;
use ProgrammersBeats\PostmanGenerator\Generators\MiddlewareGroupingStrategy;
use ProgrammersBeats\PostmanGenerator\Generators\NameGroupingStrategy;
use ProgrammersBeats\PostmanGenerator\Generators\PrefixGroupingStrategy;
use ProgrammersBeats\PostmanGenerator\Generators\ResourceGroupingStrategy;
use ProgrammersBeats\PostmanGenerator\Generators\TagGroupingStrategy;

class CollectionGenerator implements CollectionGeneratorInterface
{
    protected array $strategies = [];

    public function __construct(
        protected RouteParserInterface $routeParser,
    ) {
        $this->registerDefaultStrategies();
    }

    /**
     * Generate a Postman collection from parsed routes.
     */
    public function generate(GeneratorOptions $options): PostmanCollection
    {
        // Parse routes
        $routes = $this->routeParser->parse();
        $routes = $this->routeParser->filter($routes);

        // Get grouping strategy
        $strategy = $this->getStrategy($options->groupingStrategy);

        // Group routes
        $groupedRoutes = $strategy->group($routes);

        // Build collection items (folders and requests) with nested structure
        $items = $this->buildItems($groupedRoutes, $options, $strategy);

        // Create collection
        return PostmanCollection::create(
            name: $options->collectionName,
            description: $this->buildCollectionDescription($options),
            items: $items,
            includeBearer: $options->includeBearer,
        );
    }

    /**
     * Export the collection to a JSON file.
     */
    public function export(PostmanCollection $collection, string $path): string
    {
        $directory = dirname($path);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $json = $collection->toJson(config('postman-generator.export.pretty_print', true));

        File::put($path, $json);

        return $path;
    }

    /**
     * Generate an environment file for the collection.
     */
    public function generateEnvironment(string $name): array
    {
        $envVars = config('postman-generator.export.environment_variables', []);

        $values = [];
        foreach ($envVars as $key => $defaultValue) {
            $values[] = [
                'key' => $key,
                'value' => $defaultValue,
                'type' => 'default',
                'enabled' => true,
            ];
        }

        return [
            'id' => (string) Str::uuid(),
            'name' => $name . ' Environment',
            'values' => $values,
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => now()->toISOString(),
            '_postman_exported_using' => 'Laravel Postman Generator',
        ];
    }

    /**
     * Export environment file.
     */
    public function exportEnvironment(array $environment, string $path): string
    {
        $directory = dirname($path);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $json = json_encode($environment, $flags);

        File::put($path, $json);

        return $path;
    }

    /**
     * Register a grouping strategy.
     */
    public function registerStrategy(string $name, GroupingStrategyInterface $strategy): self
    {
        $this->strategies[$name] = $strategy;
        return $this;
    }

    /**
     * Get a grouping strategy by name.
     */
    protected function getStrategy(string $name): GroupingStrategyInterface
    {
        if (!isset($this->strategies[$name])) {
            throw new \InvalidArgumentException("Unknown grouping strategy: {$name}");
        }

        return $this->strategies[$name];
    }

    /**
     * Register default grouping strategies.
     */
    protected function registerDefaultStrategies(): void
    {
        $this->strategies = [
            'prefix' => new PrefixGroupingStrategy(),
            'controller' => new ControllerGroupingStrategy(),
            'resource' => new ResourceGroupingStrategy(),
            'name' => new NameGroupingStrategy(),
            'middleware' => new MiddlewareGroupingStrategy(),
            'tag' => new TagGroupingStrategy(),
        ];
    }

    /**
     * Build collection items with nested folder structure from grouped routes.
     *
     * Group keys containing '/' are split into nested folders.
     * E.g., key 'auth/pin' creates Auth > Pin folder hierarchy.
     */
    protected function buildItems(array $groupedRoutes, GeneratorOptions $options, GroupingStrategyInterface $strategy): array
    {
        // Build a tree from group keys
        $tree = [];

        foreach ($groupedRoutes as $groupKey => $routes) {
            $segments = explode('/', $groupKey);
            $this->insertIntoTree($tree, $segments, $routes);
        }

        // Convert the tree into Postman folder items
        return $this->renderTree($tree, $options);
    }

    /**
     * Insert routes into the tree at the correct depth.
     */
    private function insertIntoTree(array &$tree, array $segments, array $routes): void
    {
        $segment = array_shift($segments);

        if (!isset($tree[$segment])) {
            $tree[$segment] = [
                'routes' => [],
                'children' => [],
            ];
        }

        if (empty($segments)) {
            $tree[$segment]['routes'] = array_merge($tree[$segment]['routes'], $routes);
        } else {
            $this->insertIntoTree($tree[$segment]['children'], $segments, $routes);
        }
    }

    /**
     * Recursively convert the tree into Postman-compatible folder/request items.
     */
    private function renderTree(array $tree, GeneratorOptions $options, string $parentPath = ''): array
    {
        $items = [];

        ksort($tree);

        foreach ($tree as $segment => $node) {
            $currentPath = $parentPath ? $parentPath . '/' . $segment : $segment;
            $folderName = $this->getFolderDisplayName($segment, $currentPath);

            $folder = [
                'name' => $folderName,
                'item' => [],
            ];

            // Add nested sub-folders first
            if (!empty($node['children'])) {
                $childItems = $this->renderTree($node['children'], $options, $currentPath);
                $folder['item'] = array_merge($folder['item'], $childItems);
            }

            // Add routes as request items
            if (!empty($node['routes'])) {
                $folder['description'] = $this->buildFolderDescription($node['routes'], $options);

                foreach ($node['routes'] as $route) {
                    $folder['item'][] = $this->buildRequest($route, $options);
                }
            }

            // Only include folders that have content
            if (!empty($folder['item'])) {
                $items[] = $folder;
            }
        }

        return $items;
    }

    /**
     * Get a display name for a folder segment, checking config overrides first.
     */
    private function getFolderDisplayName(string $segment, string $fullPath): string
    {
        $customNames = config('postman-generator.grouping.folder_names', []);

        // Check full path first (e.g., 'auth/pin' => 'PIN Management')
        if (isset($customNames[$fullPath])) {
            return $customNames[$fullPath];
        }

        // Check segment name (e.g., 'pin' => 'PIN Management')
        if (isset($customNames[$segment])) {
            return $customNames[$segment];
        }

        // Default to Title Case
        return Str::title(str_replace(['-', '_'], ' ', $segment));
    }

    /**
     * Build a single Postman request from a ParsedRoute.
     */
    protected function buildRequest(ParsedRoute $route, GeneratorOptions $options): array
    {
        $request = [
            'name' => $route->getDisplayName(),
            'request' => [
                'method' => $route->getPrimaryMethod(),
                'header' => $this->buildHeaders($route),
                'url' => $this->buildUrl($route, $options),
            ],
        ];

        // Add description if full descriptive mode
        if ($options->fullDescriptive) {
            $request['request']['description'] = $this->buildRequestDescription($route, $options);
        }

        // Add body for POST/PUT/PATCH requests
        if (in_array($route->getPrimaryMethod(), ['POST', 'PUT', 'PATCH'])) {
            $request['request']['body'] = $this->buildRequestBody($route, $options);
        }

        // Add pre-request script for authenticated routes
        if ($route->requiresAuth && $options->includeBearer) {
            $request['event'] = $this->buildRequestEvents($route, $options);
        }

        // Add post-response script for login routes
        if ($route->isLoginRoute) {
            $request['event'] = array_merge(
                $request['event'] ?? [],
                $this->buildLoginEvents()
            );
        }

        // Add post-response script for logout routes
        if ($route->isLogoutRoute) {
            $request['event'] = array_merge(
                $request['event'] ?? [],
                $this->buildLogoutEvents()
            );
        }

        // Add auth inheritance
        if ($route->requiresAuth) {
            $request['request']['auth'] = [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => '{{auth_token}}',
                        'type' => 'string',
                    ],
                ],
            ];
        }

        return $request;
    }

    /**
     * Build request headers.
     */
    protected function buildHeaders(ParsedRoute $route): array
    {
        return config('postman-generator.request_defaults.headers', [
            ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
            ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
        ]);
    }

    /**
     * Build request URL.
     */
    protected function buildUrl(ParsedRoute $route, GeneratorOptions $options): array
    {
        $baseUrl = $options->baseUrl ?? config('postman-generator.base_url', '{{base_url}}');

        // Build path segments
        $uri = $route->getPostmanUri();
        $pathSegments = array_filter(explode('/', $uri));

        // Convert :param to {{param}} and track variables
        $path = [];
        $variables = [];

        foreach ($pathSegments as $segment) {
            if (Str::startsWith($segment, ':')) {
                $paramName = substr($segment, 1);
                $path[] = ':' . $paramName;
                $variables[] = [
                    'key' => $paramName,
                    'value' => '',
                    'description' => $route->parameters[$paramName]['description'] ?? 'Parameter value',
                ];
            } else {
                $path[] = $segment;
            }
        }

        $url = [
            'raw' => rtrim($baseUrl, '/') . '/' . ltrim($uri, '/'),
            'host' => [$baseUrl],
            'path' => $path,
        ];

        if (!empty($variables)) {
            $url['variable'] = $variables;
        }

        return $url;
    }

    /**
     * Build request body for POST/PUT/PATCH.
     */
    protected function buildRequestBody(ParsedRoute $route, GeneratorOptions $options): array
    {
        $body = [
            'mode' => 'raw',
            'raw' => '',
            'options' => [
                'raw' => [
                    'language' => 'json',
                ],
            ],
        ];

        // Generate sample body from validation rules
        if ($options->includeValidationRules && !empty($route->validationRules)) {
            $sampleBody = $this->generateSampleBody($route->validationRules);
            $body['raw'] = json_encode($sampleBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $body['raw'] = "{\n    \n}";
        }

        return $body;
    }

    /**
     * Generate a sample request body from validation rules.
     */
    protected function generateSampleBody(array $rules): array
    {
        $body = [];

        foreach ($rules as $field => $rule) {
            // Skip nested field notation for now
            if (Str::contains($field, '.') && !Str::endsWith($field, '.*')) {
                continue;
            }

            // Handle array fields
            if (Str::endsWith($field, '.*')) {
                $arrayField = Str::beforeLast($field, '.*');
                $body[$arrayField] = [];
                continue;
            }

            // Determine value based on rule type
            $ruleString = is_array($rule) ? implode('|', array_map(fn($r) => is_string($r) ? $r : '', $rule)) : (string) $rule;

            $body[$field] = $this->getSampleValue($field, $ruleString);
        }

        return $body;
    }

    /**
     * Get a sample value based on field name and rules.
     */
    protected function getSampleValue(string $field, string $rules): mixed
    {
        // Check specific field names first
        $fieldSamples = [
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'address' => '123 Main St',
            'city' => 'New York',
            'country' => 'US',
            'zip' => '10001',
            'title' => 'Sample Title',
            'description' => 'Sample description text',
            'body' => 'Sample body content',
            'content' => 'Sample content',
            'status' => 'active',
            'type' => 'default',
            'url' => 'https://example.com',
            'website' => 'https://example.com',
            'image' => 'https://example.com/image.jpg',
            'avatar' => 'https://example.com/avatar.jpg',
        ];

        if (isset($fieldSamples[$field])) {
            return $fieldSamples[$field];
        }

        // Check rules for type hints
        if (Str::contains($rules, 'integer') || Str::contains($rules, 'numeric')) {
            return 1;
        }

        if (Str::contains($rules, 'boolean')) {
            return true;
        }

        if (Str::contains($rules, 'array')) {
            return [];
        }

        if (Str::contains($rules, 'date')) {
            return now()->format('Y-m-d');
        }

        if (Str::contains($rules, 'email')) {
            return 'user@example.com';
        }

        if (Str::contains($rules, 'url')) {
            return 'https://example.com';
        }

        // Default to string
        return 'sample_value';
    }

    /**
     * Build request events (pre-request scripts).
     */
    protected function buildRequestEvents(ParsedRoute $route, GeneratorOptions $options): array
    {
        return [
            [
                'listen' => 'prerequest',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        '// This request requires authentication',
                        '// Token is automatically set from collection-level pre-request script',
                        'const token = pm.environment.get("auth_token");',
                        'if (!token) {',
                        '    console.warn("No auth token found. Please authenticate first.");',
                        '}',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build login route post-response events.
     */
    protected function buildLoginEvents(): array
    {
        $script = config('postman-generator.scripts.login_post_response');
        $lines = explode("\n", $script);

        return [
            [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => $lines,
                ],
            ],
        ];
    }

    /**
     * Build logout route post-response events.
     */
    protected function buildLogoutEvents(): array
    {
        $script = config('postman-generator.scripts.logout_post_response');
        $lines = explode("\n", $script);

        return [
            [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => $lines,
                ],
            ],
        ];
    }

    /**
     * Build collection description.
     */
    protected function buildCollectionDescription(GeneratorOptions $options): string
    {
        $description = $options->description;

        $description .= "\n\n---\n\n";
        $description .= "**Generated by Laravel Postman Generator**\n\n";
        $description .= "- Generated at: " . now()->toDateTimeString() . "\n";
        $description .= "- Grouping Strategy: " . Str::title($options->groupingStrategy) . "\n";

        if ($options->includeBearer) {
            $description .= "\n### Authentication\n\n";
            $description .= "This collection uses Bearer token authentication with Laravel Sanctum.\n\n";
            $description .= "1. Call the login endpoint first\n";
            $description .= "2. The token will be automatically stored in the `auth_token` environment variable\n";
            $description .= "3. All authenticated endpoints will automatically use this token\n";
        }

        return $description;
    }

    /**
     * Build folder description.
     */
    protected function buildFolderDescription(array $routes, GeneratorOptions $options): string
    {
        $count = count($routes);
        $authCount = count(array_filter($routes, fn($r) => $r->requiresAuth));

        return "Contains {$count} endpoint(s). {$authCount} require(s) authentication.";
    }

    /**
     * Build request description.
     */
    protected function buildRequestDescription(ParsedRoute $route, GeneratorOptions $options): string
    {
        $parts = [];

        // PHPDoc description
        if ($options->includePHPDoc && $route->description) {
            $parts[] = $route->description;
        }

        // Route information
        $parts[] = "**Route:** `{$route->uri}`";
        $parts[] = "**Method:** `{$route->getPrimaryMethod()}`";

        if ($route->name) {
            $parts[] = "**Name:** `{$route->name}`";
        }

        // Authentication
        if ($route->requiresAuth) {
            $parts[] = "**Authentication:** Required (Bearer Token)";
        }

        // Middleware
        if ($options->includeMiddlewareInfo && !empty($route->middleware)) {
            $parts[] = "**Middleware:** `" . implode('`, `', $route->middleware) . "`";
        }

        // Validation rules
        if ($options->includeValidationRules && !empty($route->validationRules)) {
            $parts[] = "\n### Request Parameters\n";
            foreach ($route->validationRules as $field => $rules) {
                $ruleString = is_array($rules)
                    ? implode(', ', array_map(fn($r) => is_string($r) ? $r : get_class($r), $rules))
                    : (string) $rules;
                $parts[] = "- **{$field}**: {$ruleString}";
            }
        }

        return implode("\n", $parts);
    }
}
