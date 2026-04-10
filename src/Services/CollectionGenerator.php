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
    protected TestScriptGenerator $testScriptGenerator;
    protected ExampleResponseGenerator $exampleResponseGenerator;
    protected FactoryDataGenerator $factoryDataGenerator;

    public function __construct(
        protected RouteParserInterface $routeParser,
    ) {
        $this->registerDefaultStrategies();
        $this->testScriptGenerator = new TestScriptGenerator();
        $this->exampleResponseGenerator = new ExampleResponseGenerator();
        $this->factoryDataGenerator = new FactoryDataGenerator();
    }

    /**
     * Generate a Postman collection from parsed routes.
     *
     * When multiple route files are detected (api.php, candidate.php, etc.),
     * routes are grouped under top-level folders named after their source file.
     */
    public function generate(GeneratorOptions $options): PostmanCollection
    {
        $routes = $this->routeParser->parse();
        $routes = $this->routeParser->filter($routes);

        $strategy = $this->getStrategy($options->groupingStrategy);

        // Check if routes come from multiple files
        $sourceFiles = $routes->pluck('sourceFileName')->filter()->unique();
        $hasMultipleFiles = $sourceFiles->count() > 1;

        if ($hasMultipleFiles) {
            // Group by source file first, then by strategy within each file
            $items = $this->buildMultiFileItems($routes, $options, $strategy);
        } else {
            $groupedRoutes = $strategy->group($routes);
            $items = $this->buildItems($groupedRoutes, $options, $strategy);
        }

        return PostmanCollection::create(
            name: $options->collectionName,
            description: $this->buildCollectionDescription($options),
            items: $items,
            includeBearer: $options->includeBearer,
        );
    }

    /**
     * Build items grouped by source file as top-level folders.
     * e.g., "Api" folder, "Candidate" folder, "Public" folder
     */
    protected function buildMultiFileItems($routes, GeneratorOptions $options, GroupingStrategyInterface $strategy): array
    {
        $items = [];

        // Group routes by source file
        $byFile = [];
        $noFile = [];

        foreach ($routes as $route) {
            $fileName = $route->sourceFileName;
            if ($fileName) {
                $byFile[$fileName][] = $route;
            } else {
                $noFile[] = $route;
            }
        }

        ksort($byFile);

        // Build each source file as a top-level folder
        foreach ($byFile as $fileName => $fileRoutes) {
            $collection = collect($fileRoutes);
            $groupedRoutes = $strategy->group($collection);
            $childItems = $this->buildItems($groupedRoutes, $options, $strategy);

            $authCount = count(array_filter($fileRoutes, fn($r) => $r->requiresAuth));
            $totalCount = count($fileRoutes);

            $items[] = [
                'name' => $fileName . ' APIs',
                'description' => "Routes from {$fileName} ({$totalCount} endpoints, {$authCount} require authentication)",
                'item' => $childItems,
            ];
        }

        // Add ungrouped routes
        if (!empty($noFile)) {
            $collection = collect($noFile);
            $groupedRoutes = $strategy->group($collection);
            $childItems = $this->buildItems($groupedRoutes, $options, $strategy);

            $items[] = [
                'name' => 'Other APIs',
                'description' => 'Routes without a detected source file',
                'item' => $childItems,
            ];
        }

        return $items;
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
     * Generate a zero-config environment file.
     * Uses actual APP_URL so users can import and use immediately.
     */
    public function generateEnvironment(string $name): array
    {
        // Auto-detect the base URL from the application config
        $appUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        $apiBaseUrl = $appUrl . '/api';

        $values = [
            [
                'key' => 'base_url',
                'value' => $apiBaseUrl,
                'type' => 'default',
                'enabled' => true,
            ],
            [
                'key' => 'Bearer',
                'value' => '',
                'type' => 'secret',
                'enabled' => true,
            ],
            [
                'key' => 'token_expiry',
                'value' => '',
                'type' => 'default',
                'enabled' => true,
            ],
            [
                'key' => 'app_url',
                'value' => $appUrl,
                'type' => 'default',
                'enabled' => true,
            ],
        ];

        // Add any custom environment variables from config
        $customVars = config('postman-generator.export.environment_variables', []);
        $existingKeys = array_column($values, 'key');

        foreach ($customVars as $key => $defaultValue) {
            if (!in_array($key, $existingKeys)) {
                $values[] = [
                    'key' => $key,
                    'value' => $defaultValue,
                    'type' => 'default',
                    'enabled' => true,
                ];
            }
        }

        return [
            'id' => (string) Str::uuid(),
            'name' => $name . ' Environment',
            'values' => $values,
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => now()->toISOString(),
            '_postman_exported_using' => 'Laravel Postman Generator by ProgrammersBeats',
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

    public function registerStrategy(string $name, GroupingStrategyInterface $strategy): self
    {
        $this->strategies[$name] = $strategy;
        return $this;
    }

    protected function getStrategy(string $name): GroupingStrategyInterface
    {
        if (!isset($this->strategies[$name])) {
            throw new \InvalidArgumentException("Unknown grouping strategy: {$name}");
        }

        return $this->strategies[$name];
    }

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
     */
    protected function buildItems(array $groupedRoutes, GeneratorOptions $options, GroupingStrategyInterface $strategy): array
    {
        $tree = [];

        foreach ($groupedRoutes as $groupKey => $routes) {
            $segments = explode('/', $groupKey);
            $this->insertIntoTree($tree, $segments, $routes);
        }

        return $this->renderTree($tree, $options);
    }

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

            if (!empty($node['children'])) {
                $childItems = $this->renderTree($node['children'], $options, $currentPath);
                $folder['item'] = array_merge($folder['item'], $childItems);
            }

            if (!empty($node['routes'])) {
                $folder['description'] = $this->buildFolderDescription($node['routes'], $options);

                foreach ($node['routes'] as $route) {
                    $folder['item'][] = $this->buildRequest($route, $options);
                }
            }

            if (!empty($folder['item'])) {
                $items[] = $folder;
            }
        }

        return $items;
    }

    private function getFolderDisplayName(string $segment, string $fullPath): string
    {
        $customNames = config('postman-generator.grouping.folder_names', []);

        if (isset($customNames[$fullPath])) {
            return $customNames[$fullPath];
        }

        if (isset($customNames[$segment])) {
            return $customNames[$segment];
        }

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

        // Add description
        if ($options->fullDescriptive) {
            $request['request']['description'] = $this->buildRequestDescription($route, $options);
        }

        // Add body for POST/PUT/PATCH requests
        if (in_array($route->getPrimaryMethod(), ['POST', 'PUT', 'PATCH'])) {
            $request['request']['body'] = $this->buildRequestBody($route, $options);
        }

        // Build events (pre-request + test scripts)
        $events = [];

        // Pre-request script for authenticated routes
        if ($route->requiresAuth && $options->includeBearer) {
            $events = array_merge($events, $this->buildRequestEvents($route, $options));
        }

        // Login post-response scripts
        if ($route->isLoginRoute) {
            $events = array_merge($events, $this->buildLoginEvents());
        }

        // Logout post-response scripts
        if ($route->isLogoutRoute) {
            $events = array_merge($events, $this->buildLogoutEvents());
        }

        // Auto-generated test scripts
        if ($options->includeTestScripts) {
            $events[] = $this->testScriptGenerator->buildTestEvent($route);
        }

        if (!empty($events)) {
            $request['event'] = $events;
        }

        // Auth inheritance
        if ($route->requiresAuth) {
            $request['request']['auth'] = [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => '{{Bearer}}',
                        'type' => 'string',
                    ],
                ],
            ];
        }

        // Example responses
        if ($options->includeExampleResponses) {
            $request['response'] = [
                $this->exampleResponseGenerator->buildPostmanExample($route),
            ];
        }

        return $request;
    }

    protected function buildHeaders(ParsedRoute $route): array
    {
        return config('postman-generator.request_defaults.headers', [
            ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
            ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
        ]);
    }

    protected function buildUrl(ParsedRoute $route, GeneratorOptions $options): array
    {
        $baseUrl = $options->baseUrl ?? config('postman-generator.base_url', '{{base_url}}');

        $uri = $route->getPostmanUri();
        $pathSegments = array_filter(explode('/', $uri));

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
     * Uses Factory data when available, then validation rules, then empty body.
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

        $sampleBody = [];

        // Try factory data first (most realistic)
        if ($options->includeFactoryData && $route->modelClass) {
            $factoryData = $this->factoryDataGenerator->generate($route->modelClass);
            if (!empty($factoryData)) {
                // Filter factory data to only include fields from validation rules if available
                if (!empty($route->validationRules)) {
                    $ruleFields = array_keys($route->validationRules);
                    $sampleBody = array_intersect_key($factoryData, array_flip($ruleFields));
                    // Add any validation fields not in factory
                    foreach ($ruleFields as $field) {
                        if (!isset($sampleBody[$field])) {
                            $sampleBody[$field] = $this->getSampleValue($field, '');
                        }
                    }
                } else {
                    $sampleBody = $factoryData;
                }
            }
        }

        // Fallback to validation rules
        if (empty($sampleBody) && $options->includeValidationRules && !empty($route->validationRules)) {
            $sampleBody = $this->generateSampleBody($route->validationRules);
        }

        if (!empty($sampleBody)) {
            $body['raw'] = json_encode($sampleBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $body['raw'] = "{\n    \n}";
        }

        return $body;
    }

    protected function generateSampleBody(array $rules): array
    {
        $body = [];

        foreach ($rules as $field => $rule) {
            if (Str::contains($field, '.') && !Str::endsWith($field, '.*')) {
                continue;
            }

            if (Str::endsWith($field, '.*')) {
                $arrayField = Str::beforeLast($field, '.*');
                $body[$arrayField] = [];
                continue;
            }

            $ruleString = is_array($rule) ? implode('|', array_map(fn($r) => is_string($r) ? $r : '', $rule)) : (string) $rule;

            $body[$field] = $this->getSampleValue($field, $ruleString);
        }

        return $body;
    }

    protected function getSampleValue(string $field, string $rules): mixed
    {
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

        if (Str::contains($rules, 'integer') || Str::contains($rules, 'numeric')) return 1;
        if (Str::contains($rules, 'boolean')) return true;
        if (Str::contains($rules, 'array')) return [];
        if (Str::contains($rules, 'date')) return now()->format('Y-m-d');
        if (Str::contains($rules, 'email')) return 'user@example.com';
        if (Str::contains($rules, 'url')) return 'https://example.com';

        return 'sample_value';
    }

    protected function buildRequestEvents(ParsedRoute $route, GeneratorOptions $options): array
    {
        return [
            [
                'listen' => 'prerequest',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        '// This request requires authentication',
                        'const token = pm.collectionVariables.get("Bearer") || pm.environment.get("Bearer");',
                        'if (!token) {',
                        '    console.warn("No auth token found. Please authenticate first.");',
                        '}',
                    ],
                ],
            ],
        ];
    }

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

    protected function buildCollectionDescription(GeneratorOptions $options): string
    {
        $description = $options->description;

        $description .= "\n\n---\n\n";
        $description .= "**Generated by Laravel Postman Generator** by [ProgrammersBeats](https://github.com/ProgrammersBeats)\n\n";
        $description .= "- Generated at: " . now()->toDateTimeString() . "\n";
        $description .= "- Grouping Strategy: " . Str::title($options->groupingStrategy) . "\n";
        $description .= "- Base URL: `{{base_url}}`\n";

        if ($options->includeTestScripts) {
            $description .= "- Auto-generated test scripts included\n";
        }

        if ($options->includeExampleResponses) {
            $description .= "- Example responses included for each endpoint\n";
        }

        if ($options->includeBearer) {
            $description .= "\n### Authentication\n\n";
            $description .= "This collection uses Bearer token authentication with Laravel Sanctum.\n\n";
            $description .= "1. Call the login endpoint first\n";
            $description .= "2. The token will be automatically stored in the `Bearer` environment variable\n";
            $description .= "3. All authenticated endpoints will automatically use this token\n";
        }

        $description .= "\n### Quick Start\n\n";
        $description .= "1. Import this collection and the environment file\n";
        $description .= "2. Select the environment from the dropdown\n";
        $description .= "3. The `base_url` is pre-configured - start making requests!\n";

        return $description;
    }

    protected function buildFolderDescription(array $routes, GeneratorOptions $options): string
    {
        $count = count($routes);
        $authCount = count(array_filter($routes, fn($r) => $r->requiresAuth));

        $desc = "Contains {$count} endpoint(s). {$authCount} require(s) authentication.";

        // Add rate limit info if any route has it
        $rateLimited = array_filter($routes, fn($r) => $r->rateLimitInfo !== null);
        if (!empty($rateLimited)) {
            $first = reset($rateLimited);
            $desc .= "\n\nRate Limit: {$first->rateLimitInfo}";
        }

        return $desc;
    }

    protected function buildRequestDescription(ParsedRoute $route, GeneratorOptions $options): string
    {
        $parts = [];

        if ($options->includePHPDoc && $route->description) {
            $parts[] = $route->description;
        }

        $parts[] = "**Route:** `{$route->uri}`";
        $parts[] = "**Method:** `{$route->getPrimaryMethod()}`";

        if ($route->name) {
            $parts[] = "**Name:** `{$route->name}`";
        }

        if ($route->requiresAuth) {
            $parts[] = "**Authentication:** Required (Bearer Token)";
        }

        if ($route->rateLimitInfo) {
            $parts[] = "**Rate Limit:** {$route->rateLimitInfo}";
        }

        if ($options->includeMiddlewareInfo && !empty($route->middleware)) {
            $parts[] = "**Middleware:** `" . implode('`, `', $route->middleware) . "`";
        }

        // cURL example
        $baseUrl = config('app.url', 'http://localhost:8000');
        $parts[] = "\n### cURL\n\n```bash\n" . $route->toCurl($baseUrl) . "\n```";

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
