<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Services;

use Illuminate\Support\Str;

class RouteFileDetector
{
    protected ?array $detectedFiles = null;
    protected ?array $controllerMap = null;
    protected ?array $uriMap = null;

    /**
     * Auto-detect all registered route files from bootstrap/app.php or RouteServiceProvider.
     */
    public function detect(): array
    {
        if ($this->detectedFiles !== null) {
            return $this->detectedFiles;
        }

        $files = [];

        // Try Laravel 11/12/13 bootstrap/app.php
        $bootstrapPath = base_path('bootstrap/app.php');
        if (file_exists($bootstrapPath)) {
            $files = $this->parseBootstrapApp(file_get_contents($bootstrapPath));
        }

        // If no files detected, try Laravel 10 RouteServiceProvider
        if (empty($files)) {
            $providerPath = app_path('Providers/RouteServiceProvider.php');
            if (file_exists($providerPath)) {
                $files = $this->parseRouteServiceProvider(file_get_contents($providerPath));
            }
        }

        // Fallback: just api.php
        if (empty($files)) {
            $apiPath = base_path('routes/api.php');
            if (file_exists($apiPath)) {
                $files[] = [
                    'path' => $apiPath,
                    'relative' => 'routes/api.php',
                    'name' => 'Api',
                    'priority' => 0,
                ];
            }
        }

        // Apply custom names from config
        $customNames = config('postman-generator.route_files', []);
        foreach ($files as &$file) {
            if (isset($customNames[$file['relative']])) {
                $file['name'] = $customNames[$file['relative']];
            }
        }

        $this->detectedFiles = $files;
        return $files;
    }

    /**
     * Parse Laravel 11/12/13 bootstrap/app.php for route file registrations.
     */
    protected function parseBootstrapApp(string $content): array
    {
        $files = [];
        $priority = 0;

        // Match: api: __DIR__.'/../routes/api.php'
        // This is the PRIMARY api file - gets highest priority
        if (preg_match("/api:\s*__DIR__\s*\.\s*'[^']*\/routes\/([^']+)'/", $content, $match)) {
            $filename = $match[1];
            $files[] = [
                'path' => base_path('routes/' . $filename),
                'relative' => 'routes/' . $filename,
                'name' => $this->deriveNameFromFilename($filename),
                'priority' => $priority++,
            ];
        }

        // Match: ->group(base_path('routes/candidate.php'))
        preg_match_all("/group\(\s*base_path\(\s*'routes\/([^']+)'\s*\)/", $content, $matches);
        foreach ($matches[1] as $filename) {
            $relative = 'routes/' . $filename;
            $alreadyExists = false;
            foreach ($files as $f) {
                if ($f['relative'] === $relative) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (!$alreadyExists) {
                $files[] = [
                    'path' => base_path($relative),
                    'relative' => $relative,
                    'name' => $this->deriveNameFromFilename($filename),
                    'priority' => $priority++,
                ];
            }
        }

        return $files;
    }

    /**
     * Parse Laravel 10 RouteServiceProvider for route file registrations.
     */
    protected function parseRouteServiceProvider(string $content): array
    {
        $files = [];
        $priority = 0;

        preg_match_all("/group\(\s*base_path\(\s*'routes\/([^']+)'\s*\)/", $content, $matches);
        foreach ($matches[1] as $filename) {
            if ($filename === 'web.php' || $filename === 'console.php') {
                continue;
            }
            $files[] = [
                'path' => base_path('routes/' . $filename),
                'relative' => 'routes/' . $filename,
                'name' => $this->deriveNameFromFilename($filename),
                'priority' => $priority++,
            ];
        }

        return $files;
    }

    protected function deriveNameFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        return Str::title(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * Build controller-to-file AND uri-to-file mappings for route matching.
     */
    protected function buildMappings(): void
    {
        if ($this->controllerMap !== null) {
            return;
        }

        $this->controllerMap = [];
        $this->uriMap = [];

        $files = $this->detect();

        // Sort by priority (api.php first) so it gets precedence for shared controllers
        usort($files, fn($a, $b) => ($a['priority'] ?? 0) - ($b['priority'] ?? 0));

        foreach ($files as $file) {
            if (!file_exists($file['path'])) {
                continue;
            }

            $content = file_get_contents($file['path']);

            // Build controller map from use statements
            // Match: use App\Http\Controllers\Api\UserController;
            // Match: use App\Http\Controllers\Candidate\JobController;
            preg_match_all('/use\s+([\\\\\w]+\\\\(\w+Controller))\s*;/', $content, $useMatches);

            foreach ($useMatches[1] as $i => $fqcn) {
                // Only set if not already claimed by a higher-priority file
                if (!isset($this->controllerMap[$fqcn])) {
                    $this->controllerMap[$fqcn] = $file;
                }
                $shortName = $useMatches[2][$i];
                if (!isset($this->controllerMap[$shortName])) {
                    $this->controllerMap[$shortName] = $file;
                }
            }

            // Also match inline controller references: SomeController::class
            preg_match_all('/(\w+Controller)::class/', $content, $classRefs);
            foreach ($classRefs[1] as $shortName) {
                if (!isset($this->controllerMap[$shortName])) {
                    $this->controllerMap[$shortName] = $file;
                }
            }

            // Build URI map - extract route URI patterns from the file
            // Match: Route::get('users', ...) / Route::post('auth/login', ...)
            preg_match_all("/Route::\w+\(\s*['\"]([^'\"]+)['\"]/", $content, $routeMatches);
            foreach ($routeMatches[1] as $uri) {
                $this->uriMap[$uri] = $file;
            }

            // Match: Route::apiResource('users', ...)
            preg_match_all("/Route::(?:apiResource|resource)\(\s*['\"]([^'\"]+)['\"]/", $content, $resourceMatches);
            foreach ($resourceMatches[1] as $resource) {
                $this->uriMap[$resource] = $file;
            }

            // Match: Route::prefix('admin') to tag URIs starting with that prefix
            preg_match_all("/Route::prefix\(\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $prefixMatches);
            foreach ($prefixMatches[1] as $prefix) {
                // Store prefix patterns for URI matching
                $this->uriMap['__prefix__' . $prefix] = $file;
            }
        }
    }

    /**
     * Find which route file a route belongs to.
     * Uses controller matching first (most reliable), then URI pattern matching.
     */
    public function getSourceFile(?string $controllerClass, ?string $uri = null): ?array
    {
        $this->buildMappings();

        // 1. Try exact controller FQCN match
        if ($controllerClass && isset($this->controllerMap[$controllerClass])) {
            return $this->controllerMap[$controllerClass];
        }

        // 2. Try short controller name match
        if ($controllerClass) {
            $shortName = class_basename($controllerClass);
            if (isset($this->controllerMap[$shortName])) {
                return $this->controllerMap[$shortName];
            }
        }

        // 3. Try URI pattern matching
        if ($uri) {
            $cleanUri = trim($uri, '/');
            // Remove 'api/' prefix for matching
            $cleanUri = preg_replace('/^api\//', '', $cleanUri);

            // Direct URI match
            if (isset($this->uriMap[$cleanUri])) {
                return $this->uriMap[$cleanUri];
            }

            // Try matching against prefix patterns
            foreach ($this->uriMap as $pattern => $file) {
                if (str_starts_with($pattern, '__prefix__')) {
                    $prefix = substr($pattern, 10);
                    if (str_starts_with($cleanUri, $prefix . '/') || $cleanUri === $prefix) {
                        return $file;
                    }
                }
            }

            // Try partial URI match (first segment)
            $firstSegment = explode('/', $cleanUri)[0] ?? '';
            if ($firstSegment && isset($this->uriMap[$firstSegment])) {
                return $this->uriMap[$firstSegment];
            }
        }

        // 4. Fallback: return the primary api.php file if it exists
        $files = $this->detect();
        return $files[0] ?? null;
    }

    public function hasMultipleFiles(): bool
    {
        return count($this->detect()) > 1;
    }
}
