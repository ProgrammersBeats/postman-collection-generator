<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Services;

use Illuminate\Support\Str;
use ReflectionClass;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;

class ExampleResponseGenerator
{
    /**
     * Generate an example response for a route.
     */
    public function generate(ParsedRoute $route): array
    {
        // Try from API Resource class
        if ($route->responseResourceClass) {
            $example = $this->generateFromResource($route->responseResourceClass, $route->action);
            if (!empty($example)) {
                return $example;
            }
        }

        // Fallback: generate based on route type and action
        return $this->generateFromRouteContext($route);
    }

    /**
     * Parse an API Resource class to generate example response fields.
     */
    protected function generateFromResource(string $resourceClass, ?string $action): array
    {
        if (!class_exists($resourceClass)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($resourceClass);

            if (!$reflection->hasMethod('toArray')) {
                return [];
            }

            $method = $reflection->getMethod('toArray');
            $filename = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            if (!$filename || !$startLine || !$endLine) {
                return [];
            }

            $source = file_get_contents($filename);
            $lines = explode("\n", $source);
            $methodSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

            // Extract field names from $this->field patterns
            preg_match_all("/['\"](\w+)['\"]\s*=>\s*\\\$this->(\w+)/", $methodSource, $matches);

            $fields = [];
            if (!empty($matches[1])) {
                foreach ($matches[1] as $i => $fieldName) {
                    $fields[$fieldName] = $this->getSampleResponseValue($fieldName);
                }
            }

            // Also extract fields from $this->when, $this->whenLoaded patterns
            preg_match_all("/['\"](\w+)['\"]\s*=>/", $methodSource, $allKeys);
            if (!empty($allKeys[1])) {
                foreach ($allKeys[1] as $key) {
                    if (!isset($fields[$key])) {
                        $fields[$key] = $this->getSampleResponseValue($key);
                    }
                }
            }

            if (empty($fields)) {
                return [];
            }

            // Check if it's a collection endpoint
            $isCollection = is_subclass_of($resourceClass, \Illuminate\Http\Resources\Json\ResourceCollection::class);

            if ($isCollection || $action === 'index') {
                return [
                    'data' => [$fields, $fields],
                    'links' => [
                        'first' => '{{base_url}}?page=1',
                        'last' => '{{base_url}}?page=1',
                        'prev' => null,
                        'next' => null,
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 2,
                    ],
                ];
            }

            return ['data' => $fields];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Generate example response based on route context.
     */
    protected function generateFromRouteContext(ParsedRoute $route): array
    {
        $method = $route->getPrimaryMethod();
        $action = $route->action;

        // Login response
        if ($route->isLoginRoute) {
            return [
                'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'user' => [
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'user@example.com',
                ],
            ];
        }

        // Logout response
        if ($route->isLogoutRoute) {
            return ['message' => 'Successfully logged out.'];
        }

        // DELETE response
        if ($method === 'DELETE') {
            return ['message' => 'Resource deleted successfully.'];
        }

        // POST create response
        if ($method === 'POST' && $action !== 'index') {
            $fields = $this->buildFieldsFromValidation($route->validationRules);
            $fields['id'] = 1;
            $fields['created_at'] = '2026-01-15T10:30:00.000000Z';
            $fields['updated_at'] = '2026-01-15T10:30:00.000000Z';
            return ['data' => $fields, 'message' => 'Resource created successfully.'];
        }

        // PUT/PATCH update response
        if (in_array($method, ['PUT', 'PATCH'])) {
            $fields = $this->buildFieldsFromValidation($route->validationRules);
            $fields['id'] = 1;
            $fields['updated_at'] = '2026-01-15T10:30:00.000000Z';
            return ['data' => $fields, 'message' => 'Resource updated successfully.'];
        }

        // GET index (list)
        if ($method === 'GET' && $action === 'index') {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ],
            ];
        }

        // GET show (single)
        if ($method === 'GET') {
            return ['data' => ['id' => 1]];
        }

        return ['message' => 'Success'];
    }

    /**
     * Build response fields from validation rules.
     */
    protected function buildFieldsFromValidation(array $rules): array
    {
        $fields = [];

        foreach ($rules as $field => $rule) {
            if (Str::contains($field, '.')) {
                continue;
            }
            $fields[$field] = $this->getSampleResponseValue($field);
        }

        return $fields;
    }

    /**
     * Get a sample response value based on the field name.
     */
    protected function getSampleResponseValue(string $field): mixed
    {
        $samples = [
            'id' => 1,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'user@example.com',
            'phone' => '+1234567890',
            'avatar' => 'https://example.com/avatar.jpg',
            'image' => 'https://example.com/image.jpg',
            'title' => 'Sample Title',
            'description' => 'This is a sample description.',
            'body' => 'Sample body content.',
            'content' => 'Sample content text.',
            'slug' => 'sample-slug',
            'status' => 'active',
            'type' => 'default',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
            'email_verified_at' => '2026-01-15T10:30:00.000000Z',
            'created_at' => '2026-01-15T10:30:00.000000Z',
            'updated_at' => '2026-01-15T10:30:00.000000Z',
            'deleted_at' => null,
            'price' => 29.99,
            'amount' => 100.00,
            'quantity' => 1,
            'count' => 10,
            'total' => 150.00,
            'url' => 'https://example.com',
            'website' => 'https://example.com',
            'address' => '123 Main St',
            'city' => 'New York',
            'country' => 'US',
            'zip' => '10001',
            'lat' => 40.7128,
            'lng' => -74.0060,
            'token' => 'sample_token_value',
            'password' => null,
        ];

        if (isset($samples[$field])) {
            return $samples[$field];
        }

        // Guess from suffix patterns
        if (Str::endsWith($field, '_id')) return 1;
        if (Str::endsWith($field, '_at')) return '2026-01-15T10:30:00.000000Z';
        if (Str::endsWith($field, '_count')) return 0;
        if (Str::startsWith($field, 'is_') || Str::startsWith($field, 'has_')) return true;
        if (Str::endsWith($field, '_url')) return 'https://example.com';

        return 'sample_value';
    }

    /**
     * Build a Postman example response entry.
     */
    public function buildPostmanExample(ParsedRoute $route): array
    {
        $responseData = $this->generate($route);
        $statusCode = $route->getExpectedStatusCode();

        return [
            'name' => $route->getDisplayName() . ' - Success',
            'originalRequest' => [
                'method' => $route->getPrimaryMethod(),
                'url' => [
                    'raw' => '{{base_url}}/' . ltrim($route->getPostmanUri(), '/'),
                ],
            ],
            'status' => $this->getStatusText($statusCode),
            'code' => $statusCode,
            '_postman_previewlanguage' => 'json',
            'header' => [
                ['key' => 'Content-Type', 'value' => 'application/json'],
            ],
            'body' => json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    protected function getStatusText(int $code): string
    {
        return match ($code) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            default => 'OK',
        };
    }
}
