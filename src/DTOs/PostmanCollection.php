<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\DTOs;

use Illuminate\Support\Str;

class PostmanCollection
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $info,
        public readonly array $items,
        public readonly array $auth,
        public readonly array $event,
        public readonly array $variable,
    ) {}

    /**
     * Create a new PostmanCollection with default structure.
     */
    public static function create(
        string $name,
        string $description = '',
        array $items = [],
        bool $includeBearer = true,
    ): self {
        $collectionId = (string) Str::uuid();

        $info = [
            '_postman_id' => $collectionId,
            'name' => $name,
            'description' => $description,
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        ];

        $auth = $includeBearer ? [
            'type' => 'bearer',
            'bearer' => [
                [
                    'key' => 'token',
                    'value' => '{{Bearer}}',
                    'type' => 'string',
                ],
            ],
        ] : [];

        $event = [];
        if ($includeBearer) {
            $event[] = [
                'listen' => 'prerequest',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => self::getCollectionPreRequestScript(),
                ],
            ];
        }

        // Embed all environment variables directly in the collection
        // No separate environment file needed - everything in one file
        $appUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');

        $variable = [
            [
                'key' => 'base_url',
                'value' => $appUrl . '/api',
                'type' => 'string',
            ],
            [
                'key' => 'app_url',
                'value' => $appUrl,
                'type' => 'string',
            ],
            [
                'key' => 'Bearer',
                'value' => '',
                'type' => 'string',
            ],
            [
                'key' => 'token_expiry',
                'value' => '',
                'type' => 'string',
            ],
        ];

        return new self(
            name: $name,
            description: $description,
            info: $info,
            items: $items,
            auth: $auth,
            event: $event,
            variable: $variable,
        );
    }

    /**
     * Get the collection-level pre-request script for Bearer token handling.
     *
     * @return array<string>
     */
    protected static function getCollectionPreRequestScript(): array
    {
        return [
            '// Laravel Postman Generator - Zero-Config Pre-Request Script',
            '// Works with collection variables - no separate environment file needed',
            '',
            '(function() {',
            '    // Try collection variable first, then environment variable',
            '    const token = pm.collectionVariables.get("Bearer") || pm.environment.get("Bearer");',
            '    ',
            '    if (token) {',
            '        pm.request.headers.upsert({',
            '            key: "Authorization",',
            '            value: "Bearer " + token',
            '        });',
            '        ',
            '        const tokenExpiry = pm.collectionVariables.get("token_expiry") || pm.environment.get("token_expiry");',
            '        if (tokenExpiry) {',
            '            const expiryDate = new Date(tokenExpiry);',
            '            const now = new Date();',
            '            if (now > expiryDate) {',
            '                console.warn("Token appears to be expired. Please re-authenticate.");',
            '            } else {',
            '                const minutesLeft = Math.round((expiryDate - now) / 60000);',
            '                if (minutesLeft < 10) {',
            '                    console.warn("Token expires in " + minutesLeft + " minutes");',
            '                }',
            '            }',
            '        }',
            '    }',
            '})();',
        ];
    }

    /**
     * Add items (folders/requests) to the collection.
     */
    public function withItems(array $items): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            info: $this->info,
            items: $items,
            auth: $this->auth,
            event: $this->event,
            variable: $this->variable,
        );
    }

    /**
     * Convert to Postman Collection v2.1 format.
     */
    public function toArray(): array
    {
        $collection = [
            'info' => $this->info,
            'item' => $this->items,
        ];

        if (!empty($this->auth)) {
            $collection['auth'] = $this->auth;
        }

        if (!empty($this->event)) {
            $collection['event'] = $this->event;
        }

        if (!empty($this->variable)) {
            $collection['variable'] = $this->variable;
        }

        return $collection;
    }

    /**
     * Convert to JSON string.
     */
    public function toJson(bool $prettyPrint = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->toArray(), $flags);
    }
}
