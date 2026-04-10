<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Services;

use Illuminate\Support\Str;

class RouteFileDetector
{
    protected ?array $detectedFiles = null;
    protected ?array $controllerMap = null;

    /**
     * Auto-detect all registered route files from bootstrap/app.php or RouteServiceProvider.
     *
     * @return array<int, array{path: string, relative: string, name: string}>
     */
    public function detect(): array
    {
        if ($this->detectedFiles !== null) {
            return $this->detectedFiles;
        }

        $files = [];

        // Try Laravel 11+ bootstrap/app.php
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
     * Parse Laravel 11+ bootstrap/app.php for route file registrations.
     */
    protected function parseBootstrapApp(string $content): array
    {
        $files = [];

        // Match: api: __DIR__.'/../routes/api.php'
        if (preg_match("/api:\s*__DIR__\s*\.\s*'[^']*\/routes\/([^']+)'/", $content, $match)) {
            $filename = $match[1];
            $files[] = [
                'path' => base_path('routes/' . $filename),
                'relative' => 'routes/' . $filename,
                'name' => $this->deriveNameFromFilename($filename),
            ];
        }

        // Match: web: __DIR__.'/../routes/web.php' (skip web routes, not API)

        // Match: ->group(base_path('routes/candidate.php'))
        preg_match_all("/group\(\s*base_path\(\s*'routes\/([^']+)'\s*\)/", $content, $matches);
        foreach ($matches[1] as $filename) {
            // Skip if already detected (e.g., api.php)
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

        // Match: ->group(base_path('routes/api.php'))
        preg_match_all("/group\(\s*base_path\(\s*'routes\/([^']+)'\s*\)/", $content, $matches);
        foreach ($matches[1] as $filename) {
            // Skip web routes
            if ($filename === 'web.php') {
                continue;
            }
            $files[] = [
                'path' => base_path('routes/' . $filename),
                'relative' => 'routes/' . $filename,
                'name' => $this->deriveNameFromFilename($filename),
            ];
        }

        return $files;
    }

    /**
     * Derive a human-readable folder name from a route filename.
     * api.php -> "Api", candidate.php -> "Candidate", public.php -> "Public"
     */
    protected function deriveNameFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        return Str::title(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * Build a mapping of controller class names to their source route file.
     * Reads each route file, extracts controller references, maps them.
     *
     * @return array<string, array{path: string, relative: string, name: string}>
     */
    public function buildControllerMap(): array
    {
        if ($this->controllerMap !== null) {
            return $this->controllerMap;
        }

        $map = [];
        $files = $this->detect();

        foreach ($files as $file) {
            if (!file_exists($file['path'])) {
                continue;
            }

            $content = file_get_contents($file['path']);

            // Extract FQCN from use statements: use App\Http\Controllers\UserController;
            preg_match_all('/use\s+([\\\A-Za-z0-9_]+\\\\(\w+Controller))\s*;/', $content, $useMatches);

            foreach ($useMatches[1] as $fqcn) {
                $map[$fqcn] = $file;
            }

            // Also map short names: [UserController::class, 'method']
            foreach ($useMatches[2] as $i => $shortName) {
                $map[$shortName] = $file;
            }

            // Match controller references without use statements (invokable etc.)
            preg_match_all('/(\w+Controller)::class/', $content, $classMatches);
            foreach ($classMatches[1] as $shortName) {
                if (!isset($map[$shortName])) {
                    $map[$shortName] = $file;
                }
            }
        }

        $this->controllerMap = $map;
        return $map;
    }

    /**
     * Find which route file a controller belongs to.
     *
     * @return array{path: string, relative: string, name: string}|null
     */
    public function getSourceFile(?string $controllerClass): ?array
    {
        if (!$controllerClass) {
            return null;
        }

        $map = $this->buildControllerMap();

        // Try full class name
        if (isset($map[$controllerClass])) {
            return $map[$controllerClass];
        }

        // Try short name
        $shortName = class_basename($controllerClass);
        if (isset($map[$shortName])) {
            return $map[$shortName];
        }

        return null;
    }

    /**
     * Check if multiple route files are registered (enables multi-file grouping).
     */
    public function hasMultipleFiles(): bool
    {
        return count($this->detect()) > 1;
    }
}
