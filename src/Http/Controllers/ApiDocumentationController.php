<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use ProgrammersBeats\PostmanGenerator\Contracts\RouteParserInterface;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;
use ProgrammersBeats\PostmanGenerator\Services\ExampleResponseGenerator;

class ApiDocumentationController extends Controller
{
    public function __invoke(RouteParserInterface $parser)
    {
        $routes = $parser->parse();
        $routes = $parser->filter($routes);

        // Generate example responses for each route
        if (config('postman-generator.features.example_responses', true)) {
            $exampleGenerator = new ExampleResponseGenerator();
            $routes = $routes->map(function (ParsedRoute $route) use ($exampleGenerator) {
                if (empty($route->responseExample)) {
                    $example = $exampleGenerator->generate($route);
                    return new ParsedRoute(
                        uri: $route->uri,
                        methods: $route->methods,
                        name: $route->name,
                        controller: $route->controller,
                        action: $route->action,
                        middleware: $route->middleware,
                        parameters: $route->parameters,
                        description: $route->description,
                        validationRules: $route->validationRules,
                        requiresAuth: $route->requiresAuth,
                        isLoginRoute: $route->isLoginRoute,
                        isLogoutRoute: $route->isLogoutRoute,
                        prefix: $route->prefix,
                        resourceName: $route->resourceName,
                        responseResourceClass: $route->responseResourceClass,
                        modelClass: $route->modelClass,
                        responseExample: $example,
                        rateLimitInfo: $route->rateLimitInfo,
                    );
                }
                return $route;
            });
        }

        // Get grouping strategy
        $strategyName = config('postman-generator.grouping.default', 'prefix');
        $strategyClass = config("postman-generator.grouping.strategies.{$strategyName}");
        $strategy = new $strategyClass();

        $groupedRoutes = $strategy->group($routes);

        // Build tree structure from grouped routes
        $tree = $this->buildTree($groupedRoutes);

        // Build sidebar navigation JSON
        $sidebarTree = $this->buildSidebarTree($tree);

        // Flatten tree into renderable sections
        $sections = $this->flattenToSections($tree);

        $routeCount = $routes->count();
        $authRouteCount = $routes->filter(fn(ParsedRoute $r) => $r->requiresAuth)->count();

        return view('postman-generator::documentation', [
            'title' => config(
                'postman-generator.documentation.title',
                config('postman-generator.collection_name', config('app.name', 'Laravel') . ' API')
            ),
            'description' => config('postman-generator.description', ''),
            'sections' => $sections,
            'sidebarTree' => json_encode($sidebarTree, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'routeCount' => $routeCount,
            'authRouteCount' => $authRouteCount,
            'publicRouteCount' => $routeCount - $authRouteCount,
            'baseUrl' => config('postman-generator.base_url', url('/')),
            'methodCount' => $this->countByMethod($routes),
        ]);
    }

    /**
     * Build a tree structure from grouped routes.
     */
    private function buildTree(array $groupedRoutes): array
    {
        $tree = [];

        foreach ($groupedRoutes as $groupKey => $routes) {
            $segments = explode('/', $groupKey);
            $this->insertIntoTree($tree, $segments, $routes);
        }

        return $tree;
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
     * Build sidebar tree data for JavaScript rendering.
     */
    private function buildSidebarTree(array $tree, string $parentPath = ''): array
    {
        $items = [];
        ksort($tree);

        foreach ($tree as $segment => $node) {
            $currentPath = $parentPath ? $parentPath . '/' . $segment : $segment;
            $slug = Str::slug(str_replace('/', '-', $currentPath));
            $folderName = $this->getFolderDisplayName($segment, $currentPath);

            $routeItems = [];
            foreach ($node['routes'] as $route) {
                $routeItems[] = [
                    'method' => $route->getPrimaryMethod(),
                    'name' => $route->getDisplayName(),
                    'uri' => $route->uri,
                ];
            }

            $items[] = [
                'name' => $folderName,
                'slug' => $slug,
                'count' => $this->countRoutes($node),
                'routes' => $routeItems,
                'children' => $this->buildSidebarTree($node['children'], $currentPath),
            ];
        }

        return $items;
    }

    /**
     * Flatten the tree into sequential sections for main content rendering.
     */
    private function flattenToSections(array $tree, string $parentBreadcrumb = '', string $parentPath = ''): array
    {
        $sections = [];
        ksort($tree);

        foreach ($tree as $segment => $node) {
            $currentPath = $parentPath ? $parentPath . '/' . $segment : $segment;
            $folderName = $this->getFolderDisplayName($segment, $currentPath);
            $breadcrumb = $parentBreadcrumb ? $parentBreadcrumb . ' / ' . $folderName : $folderName;
            $slug = Str::slug(str_replace('/', '-', $currentPath));

            if (!empty($node['routes'])) {
                $authCount = count(array_filter($node['routes'], fn(ParsedRoute $r) => $r->requiresAuth));

                $sections[] = [
                    'name' => $folderName,
                    'slug' => $slug,
                    'breadcrumb' => $breadcrumb,
                    'routes' => $node['routes'],
                    'routeCount' => count($node['routes']),
                    'authCount' => $authCount,
                ];
            }

            if (!empty($node['children'])) {
                $childSections = $this->flattenToSections($node['children'], $breadcrumb, $currentPath);
                $sections = array_merge($sections, $childSections);
            }
        }

        return $sections;
    }

    /**
     * Count total routes in a node including children.
     */
    private function countRoutes(array $node): int
    {
        $count = count($node['routes']);
        foreach ($node['children'] as $child) {
            $count += $this->countRoutes($child);
        }
        return $count;
    }

    /**
     * Count routes by HTTP method.
     */
    private function countByMethod($routes): array
    {
        $counts = ['GET' => 0, 'POST' => 0, 'PUT' => 0, 'PATCH' => 0, 'DELETE' => 0];

        foreach ($routes as $route) {
            $method = $route->getPrimaryMethod();
            if (isset($counts[$method])) {
                $counts[$method]++;
            }
        }

        return $counts;
    }

    /**
     * Get display name for a folder from config or smart defaults.
     */
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
}
