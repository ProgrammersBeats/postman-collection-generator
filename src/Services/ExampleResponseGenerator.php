<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Services;

use Illuminate\Support\Str;
use ReflectionClass;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;

class ExampleResponseGenerator
{
    protected FactoryDataGenerator $factoryGenerator;

    public function __construct()
    {
        $this->factoryGenerator = new FactoryDataGenerator();
    }

    /**
     * Generate an example response for a route.
     * Tries multiple sources: API Resource > Factory > Validation Rules > Model inference
     */
    public function generate(ParsedRoute $route): array
    {
        // Try from API Resource class first (most accurate)
        if ($route->responseResourceClass) {
            $example = $this->generateFromResource($route->responseResourceClass, $route->action);
            if (!empty($example)) {
                return $example;
            }
        }

        // Generate from route context using all available data
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

            // Extract all field keys from the return array
            preg_match_all("/['\"](\w+)['\"]\s*=>/", $methodSource, $allKeys);

            $fields = [];
            if (!empty($allKeys[1])) {
                foreach ($allKeys[1] as $key) {
                    $fields[$key] = $this->getSampleResponseValue($key);
                }
            }

            if (empty($fields)) {
                return [];
            }

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
     * Generate example response using all available data sources:
     * 1. Factory data (most realistic)
     * 2. Validation rules fields
     * 3. Model/controller name inference
     */
    protected function generateFromRouteContext(ParsedRoute $route): array
    {
        $method = $route->getPrimaryMethod();
        $action = $route->action;

        // Login response
        if ($route->isLoginRoute) {
            return [
                'status' => true,
                'message' => 'Login successful.',
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
            return ['status' => true, 'message' => 'Successfully logged out.'];
        }

        // Build the response fields from ALL available sources
        $fields = $this->buildRichFields($route);

        // DELETE response
        if ($method === 'DELETE') {
            return ['status' => true, 'message' => 'Resource deleted successfully.'];
        }

        // POST create response
        if ($method === 'POST' && !in_array($action, ['index', 'login', 'verify', 'check'])) {
            return [
                'status' => true,
                'message' => 'Resource created successfully.',
                'data' => $fields,
            ];
        }

        // PUT/PATCH update response
        if (in_array($method, ['PUT', 'PATCH'])) {
            return [
                'status' => true,
                'message' => 'Resource updated successfully.',
                'data' => $fields,
            ];
        }

        // GET index (list) - paginated
        if ($method === 'GET' && in_array($action, ['index', 'list', 'all'])) {
            $secondItem = $fields;
            if (isset($secondItem['id'])) $secondItem['id'] = 2;
            if (isset($secondItem['name'])) $secondItem['name'] = 'Jane Smith';
            if (isset($secondItem['email'])) $secondItem['email'] = 'jane@example.com';

            return [
                'data' => [$fields, $secondItem],
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

        // GET show (single resource)
        if ($method === 'GET') {
            return [
                'status' => true,
                'data' => $fields,
            ];
        }

        // Default
        return ['status' => true, 'message' => 'Success', 'data' => $fields];
    }

    /**
     * Build response fields from all available sources.
     * Priority: Factory data > Validation rules > Model name inference
     */
    protected function buildRichFields(ParsedRoute $route): array
    {
        $fields = ['id' => 1];

        // Source 1: Factory data (most realistic)
        if ($route->modelClass) {
            $factoryData = $this->factoryGenerator->generate($route->modelClass);
            if (!empty($factoryData)) {
                $fields = array_merge($fields, $factoryData);
            }
        }

        // Source 2: Validation rules (field names that the API accepts)
        if (!empty($route->validationRules)) {
            foreach ($route->validationRules as $field => $rule) {
                if (Str::contains($field, '.') || isset($fields[$field])) {
                    continue;
                }
                $ruleString = is_array($rule) ? implode('|', array_map(fn($r) => is_string($r) ? $r : '', $rule)) : (string) $rule;
                $fields[$field] = $this->getSampleResponseValue($field, $ruleString);
            }
        }

        // Source 3: Infer common fields from controller/model name
        if (count($fields) <= 1) {
            $fields = array_merge($fields, $this->inferFieldsFromContext($route));
        }

        // Always add timestamps
        if (!isset($fields['created_at'])) {
            $fields['created_at'] = '2026-01-15T10:30:00.000000Z';
        }
        if (!isset($fields['updated_at'])) {
            $fields['updated_at'] = '2026-01-15T10:30:00.000000Z';
        }

        return $fields;
    }

    /**
     * Infer common response fields from the controller/model name.
     */
    protected function inferFieldsFromContext(ParsedRoute $route): array
    {
        $controllerName = $route->getControllerName();
        if (!$controllerName) {
            // Try to derive from URI
            $segments = explode('/', trim($route->uri, '/'));
            $filtered = array_filter($segments, fn($s) => !preg_match('/^(api|v\d+|\{.+\})$/', $s));
            $controllerName = end($filtered) ?: '';
            $controllerName = Str::studly(Str::singular($controllerName));
        }

        $baseName = strtolower(str_replace('Controller', '', $controllerName));

        // Common field sets by resource type
        $commonFields = [
            'user' => ['name' => 'John Doe', 'email' => 'user@example.com', 'phone' => '+1234567890', 'avatar' => 'https://example.com/avatar.jpg', 'role' => 'user', 'is_active' => true, 'email_verified_at' => '2026-01-15T10:30:00.000000Z'],
            'profile' => ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'user@example.com', 'phone' => '+1234567890', 'bio' => 'Software developer', 'avatar' => 'https://example.com/avatar.jpg'],
            'post' => ['title' => 'Sample Post Title', 'slug' => 'sample-post-title', 'body' => 'This is the post content.', 'status' => 'published', 'author_id' => 1],
            'comment' => ['body' => 'This is a comment.', 'user_id' => 1, 'post_id' => 1, 'is_approved' => true],
            'category' => ['name' => 'Technology', 'slug' => 'technology', 'description' => 'Tech related content', 'parent_id' => null],
            'tag' => ['name' => 'Laravel', 'slug' => 'laravel'],
            'product' => ['name' => 'Sample Product', 'slug' => 'sample-product', 'description' => 'Product description', 'price' => 29.99, 'quantity' => 100, 'sku' => 'PROD-001', 'is_active' => true],
            'order' => ['order_number' => 'ORD-2026-001', 'user_id' => 1, 'total' => 99.99, 'status' => 'pending', 'payment_method' => 'card'],
            'payment' => ['amount' => 99.99, 'currency' => 'USD', 'status' => 'completed', 'transaction_id' => 'txn_123456', 'payment_method' => 'card'],
            'notification' => ['title' => 'New notification', 'message' => 'You have a new message', 'type' => 'info', 'is_read' => false],
            'message' => ['subject' => 'Hello', 'body' => 'This is a message.', 'sender_id' => 1, 'receiver_id' => 2, 'is_read' => false],
            'job' => ['title' => 'Software Engineer', 'description' => 'We are looking for a developer.', 'company' => 'Acme Corp', 'location' => 'Remote', 'salary' => 80000, 'type' => 'full-time', 'status' => 'active'],
            'candidate' => ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'candidate@example.com', 'phone' => '+1234567890', 'resume_url' => 'https://example.com/resume.pdf', 'status' => 'applied'],
            'application' => ['job_id' => 1, 'candidate_id' => 1, 'status' => 'pending', 'cover_letter' => 'I am interested in this position.'],
            'role' => ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Full access'],
            'permission' => ['name' => 'edit-posts', 'display_name' => 'Edit Posts', 'description' => 'Can edit posts'],
            'setting' => ['key' => 'app_name', 'value' => 'My Application', 'group' => 'general'],
            'file' => ['name' => 'document.pdf', 'path' => 'uploads/document.pdf', 'size' => 1024, 'mime_type' => 'application/pdf', 'url' => 'https://example.com/uploads/document.pdf'],
            'address' => ['street' => '123 Main St', 'city' => 'New York', 'state' => 'NY', 'zip' => '10001', 'country' => 'US'],
            'pin' => ['pin' => '1234', 'is_active' => true],
            'twofactor' => ['is_enabled' => true, 'method' => 'app'],
            '2fa' => ['is_enabled' => true, 'method' => 'authenticator'],
            'auth' => ['token' => 'sample_token', 'expires_in' => 3600],
        ];

        // Try exact match, then partial match
        if (isset($commonFields[$baseName])) {
            return $commonFields[$baseName];
        }

        // Partial match
        foreach ($commonFields as $key => $fields) {
            if (Str::contains($baseName, $key) || Str::contains($key, $baseName)) {
                return $fields;
            }
        }

        // Generic fallback with meaningful fields
        return [
            'name' => 'Sample ' . Str::title($baseName),
            'status' => 'active',
        ];
    }

    /**
     * Get a sample response value based on the field name and optional rules.
     */
    protected function getSampleResponseValue(string $field, string $rules = ''): mixed
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
            'photo' => 'https://example.com/photo.jpg',
            'title' => 'Sample Title',
            'description' => 'This is a sample description.',
            'body' => 'Sample body content.',
            'content' => 'Sample content text.',
            'slug' => 'sample-slug',
            'status' => 'active',
            'type' => 'default',
            'role' => 'user',
            'bio' => 'Software developer and tech enthusiast.',
            'website' => 'https://example.com',
            'company' => 'Acme Corp',
            'position' => 'Software Engineer',
            'address' => '123 Main St',
            'street' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'zip' => '10001',
            'lat' => 40.7128,
            'lng' => -74.0060,
            'price' => 29.99,
            'amount' => 100.00,
            'total' => 150.00,
            'quantity' => 1,
            'count' => 10,
            'salary' => 80000,
            'currency' => 'USD',
            'url' => 'https://example.com',
            'link' => 'https://example.com',
            'is_active' => true,
            'is_verified' => true,
            'is_read' => false,
            'is_enabled' => true,
            'token' => 'sample_token_value',
            'password' => null,
            'pin' => '1234',
            'code' => '123456',
            'otp' => '123456',
            'email_verified_at' => '2026-01-15T10:30:00.000000Z',
            'created_at' => '2026-01-15T10:30:00.000000Z',
            'updated_at' => '2026-01-15T10:30:00.000000Z',
            'deleted_at' => null,
        ];

        if (isset($samples[$field])) {
            return $samples[$field];
        }

        // Rule-based inference
        if (Str::contains($rules, 'integer') || Str::contains($rules, 'numeric')) return 42;
        if (Str::contains($rules, 'boolean')) return true;
        if (Str::contains($rules, 'array')) return [];
        if (Str::contains($rules, 'date')) return '2026-01-15';
        if (Str::contains($rules, 'email')) return 'user@example.com';
        if (Str::contains($rules, 'url')) return 'https://example.com';

        // Suffix/prefix patterns
        if (Str::endsWith($field, '_id')) return 1;
        if (Str::endsWith($field, '_at')) return '2026-01-15T10:30:00.000000Z';
        if (Str::endsWith($field, '_count')) return 0;
        if (Str::endsWith($field, '_url')) return 'https://example.com';
        if (Str::endsWith($field, '_path')) return 'uploads/file.pdf';
        if (Str::endsWith($field, '_type')) return 'default';
        if (Str::endsWith($field, '_name')) return 'Sample Name';
        if (Str::endsWith($field, '_date')) return '2026-01-15';
        if (Str::endsWith($field, '_number')) return 'NUM-001';
        if (Str::startsWith($field, 'is_') || Str::startsWith($field, 'has_') || Str::startsWith($field, 'can_')) return true;
        if (Str::contains($field, 'email')) return 'user@example.com';
        if (Str::contains($field, 'phone')) return '+1234567890';
        if (Str::contains($field, 'name')) return 'Sample Name';
        if (Str::contains($field, 'password') || Str::contains($field, 'secret')) return null;

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
