<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ProgrammersBeats\PostmanGenerator\Contracts\RouteParserInterface;

class DiffCollectionCommand extends Command
{
    protected $signature = 'postman:diff
                            {--collection= : Path to existing collection file to compare against}
                            {--output= : Save diff report to a file}';

    protected $description = 'Compare current API routes with the last generated Postman collection to show changes';

    public function __construct(
        protected RouteParserInterface $routeParser,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan>+----------------------------------------------------------+</>');
        $this->line('<fg=cyan>|</>          <fg=white;options=bold>API Changelog / Route Diff</>                    <fg=cyan>|</>');
        $this->line('<fg=cyan>+----------------------------------------------------------+</>');
        $this->newLine();

        // Find existing collection file
        $collectionPath = $this->option('collection') ?? $this->findLatestCollection();

        if (!$collectionPath || !File::exists($collectionPath)) {
            $this->warn('No existing collection found to compare against.');
            $this->line('Generate one first: <fg=cyan>php artisan postman:collection --no-interaction</>');
            $this->line('Or specify a path: <fg=cyan>php artisan postman:diff --collection=path/to/file.json</>');
            return self::FAILURE;
        }

        $this->info("Comparing against: {$collectionPath}");
        $this->newLine();

        // Parse existing collection
        $existingRoutes = $this->parseExistingCollection($collectionPath);

        // Parse current routes
        $currentRoutes = $this->getCurrentRoutes();

        // Calculate diff
        $added = array_diff_key($currentRoutes, $existingRoutes);
        $removed = array_diff_key($existingRoutes, $currentRoutes);

        $changed = [];
        foreach ($currentRoutes as $key => $current) {
            if (isset($existingRoutes[$key])) {
                $changes = $this->detectChanges($existingRoutes[$key], $current);
                if (!empty($changes)) {
                    $changed[$key] = $changes;
                }
            }
        }

        // Display results
        $hasChanges = !empty($added) || !empty($removed) || !empty($changed);

        if (!$hasChanges) {
            $this->line('<fg=green>No changes detected.</> Your collection is up to date.');
            return self::SUCCESS;
        }

        // Added routes
        if (!empty($added)) {
            $this->line("<fg=green;options=bold>+ " . count($added) . " New Endpoint(s):</>");
            foreach ($added as $key => $route) {
                $this->line("  <fg=green>+ [{$route['method']}]</> {$route['uri']}" .
                    ($route['name'] ? " <fg=gray>({$route['name']})</>" : ''));
            }
            $this->newLine();
        }

        // Removed routes
        if (!empty($removed)) {
            $this->line("<fg=red;options=bold>- " . count($removed) . " Removed Endpoint(s):</>");
            foreach ($removed as $key => $route) {
                $this->line("  <fg=red>- [{$route['method']}]</> {$route['uri']}" .
                    ($route['name'] ? " <fg=gray>({$route['name']})</>" : ''));
            }
            $this->newLine();
        }

        // Changed routes
        if (!empty($changed)) {
            $this->line("<fg=yellow;options=bold>~ " . count($changed) . " Modified Endpoint(s):</>");
            foreach ($changed as $key => $changes) {
                $route = $currentRoutes[$key];
                $this->line("  <fg=yellow>~ [{$route['method']}]</> {$route['uri']}");
                foreach ($changes as $change) {
                    $this->line("    <fg=gray>  {$change}</>");
                }
            }
            $this->newLine();
        }

        // Summary
        $this->line('<fg=white;options=bold>Summary:</>');
        $this->line("  Added:    <fg=green>" . count($added) . "</>");
        $this->line("  Removed:  <fg=red>" . count($removed) . "</>");
        $this->line("  Modified: <fg=yellow>" . count($changed) . "</>");
        $this->line("  Total:    <fg=white>" . count($currentRoutes) . " endpoints</>");

        // Save report if requested
        if ($outputPath = $this->option('output')) {
            $this->saveReport($outputPath, $added, $removed, $changed, $currentRoutes);
            $this->newLine();
            $this->info("Diff report saved to: {$outputPath}");
        }

        $this->newLine();
        $this->line('Run <fg=cyan>php artisan postman:collection</> to regenerate the collection.');

        return self::SUCCESS;
    }

    /**
     * Find the latest generated collection file.
     */
    protected function findLatestCollection(): ?string
    {
        $outputPath = config('postman-generator.output_path', storage_path('postman'));

        if (!File::isDirectory($outputPath)) {
            return null;
        }

        $files = File::glob($outputPath . '/*.postman_collection.json');

        if (empty($files)) {
            return null;
        }

        // Return most recently modified
        usort($files, fn($a, $b) => File::lastModified($b) - File::lastModified($a));

        return $files[0];
    }

    /**
     * Parse routes from an existing Postman collection file.
     */
    protected function parseExistingCollection(string $path): array
    {
        $content = json_decode(File::get($path), true);
        $routes = [];

        $this->extractRoutesFromItems($content['item'] ?? [], $routes);

        return $routes;
    }

    /**
     * Recursively extract routes from Postman collection items.
     */
    protected function extractRoutesFromItems(array $items, array &$routes): void
    {
        foreach ($items as $item) {
            if (isset($item['item'])) {
                // It's a folder
                $this->extractRoutesFromItems($item['item'], $routes);
            } elseif (isset($item['request'])) {
                // It's a request
                $method = $item['request']['method'] ?? 'GET';
                $url = $item['request']['url']['raw'] ?? '';
                $name = $item['name'] ?? '';

                // Normalize the URL for comparison
                $uri = preg_replace('/\{\{base_url\}\}\/?/', '', $url);
                $uri = preg_replace('/^https?:\/\/[^\/]+\/?/', '', $uri);

                $key = strtoupper($method) . ':' . $uri;
                $routes[$key] = [
                    'method' => $method,
                    'uri' => $uri,
                    'name' => $name,
                    'has_auth' => isset($item['request']['auth']),
                    'has_body' => isset($item['request']['body']),
                    'has_tests' => !empty(array_filter($item['event'] ?? [], fn($e) => $e['listen'] === 'test')),
                ];
            }
        }
    }

    /**
     * Get current routes from the application.
     */
    protected function getCurrentRoutes(): array
    {
        $parsedRoutes = $this->routeParser->parse();
        $parsedRoutes = $this->routeParser->filter($parsedRoutes);

        $routes = [];
        foreach ($parsedRoutes as $route) {
            $method = $route->getPrimaryMethod();
            $uri = $route->getPostmanUri();
            $key = strtoupper($method) . ':' . $uri;

            $routes[$key] = [
                'method' => $method,
                'uri' => $route->uri,
                'name' => $route->name,
                'has_auth' => $route->requiresAuth,
                'has_body' => in_array($method, ['POST', 'PUT', 'PATCH']),
                'middleware' => $route->middleware,
            ];
        }

        return $routes;
    }

    /**
     * Detect changes between old and new route.
     */
    protected function detectChanges(array $old, array $new): array
    {
        $changes = [];

        if (($old['name'] ?? '') !== ($new['name'] ?? '') && !empty($new['name'])) {
            $changes[] = "Name changed: '{$old['name']}' -> '{$new['name']}'";
        }

        if (($old['has_auth'] ?? false) !== ($new['has_auth'] ?? false)) {
            $changes[] = $new['has_auth'] ? 'Now requires authentication' : 'Authentication removed';
        }

        return $changes;
    }

    /**
     * Save diff report to a markdown file.
     */
    protected function saveReport(string $path, array $added, array $removed, array $changed, array $current): void
    {
        $lines = [];
        $lines[] = '# API Changelog';
        $lines[] = '';
        $lines[] = 'Generated: ' . now()->toDateTimeString();
        $lines[] = 'Total endpoints: ' . count($current);
        $lines[] = '';

        if (!empty($added)) {
            $lines[] = '## Added';
            foreach ($added as $route) {
                $lines[] = "- `{$route['method']} {$route['uri']}`" . ($route['name'] ? " ({$route['name']})" : '');
            }
            $lines[] = '';
        }

        if (!empty($removed)) {
            $lines[] = '## Removed';
            foreach ($removed as $route) {
                $lines[] = "- `{$route['method']} {$route['uri']}`" . ($route['name'] ? " ({$route['name']})" : '');
            }
            $lines[] = '';
        }

        if (!empty($changed)) {
            $lines[] = '## Modified';
            foreach ($changed as $key => $changes) {
                $route = $current[$key] ?? ['method' => '?', 'uri' => $key];
                $lines[] = "- `{$route['method']} {$route['uri']}`";
                foreach ($changes as $change) {
                    $lines[] = "  - {$change}";
                }
            }
        }

        File::put($path, implode("\n", $lines));
    }
}
