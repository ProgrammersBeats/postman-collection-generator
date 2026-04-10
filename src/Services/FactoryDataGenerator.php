<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Services;

use Illuminate\Support\Str;
use ReflectionClass;

class FactoryDataGenerator
{
    /**
     * Faker method name to sample value mappings.
     */
    protected array $fakerSamples = [
        'name' => 'John Doe',
        'firstName' => 'John',
        'lastName' => 'Doe',
        'email' => 'user@example.com',
        'safeEmail' => 'user@example.com',
        'freeEmail' => 'user@example.com',
        'companyEmail' => 'admin@company.com',
        'userName' => 'johndoe',
        'password' => 'password123',
        'phone' => '+1234567890',
        'phoneNumber' => '+1234567890',
        'e164PhoneNumber' => '+12345678901',
        'address' => '123 Main St, New York, NY 10001',
        'streetAddress' => '123 Main St',
        'city' => 'New York',
        'state' => 'California',
        'country' => 'United States',
        'countryCode' => 'US',
        'postcode' => '10001',
        'zipcode' => '10001',
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'sentence' => 'This is a sample sentence.',
        'paragraph' => 'This is a sample paragraph with meaningful content for testing purposes.',
        'word' => 'sample',
        'words' => 'sample test data',
        'title' => 'Sample Title',
        'slug' => 'sample-slug',
        'url' => 'https://example.com',
        'imageUrl' => 'https://via.placeholder.com/640x480',
        'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'boolean' => true,
        'randomNumber' => 42,
        'numberBetween' => 50,
        'randomFloat' => 3.14,
        'randomDigit' => 7,
        'date' => '2026-01-15',
        'dateTime' => '2026-01-15T10:30:00',
        'dateTimeBetween' => '2026-01-15T10:30:00',
        'time' => '10:30:00',
        'unixTime' => 1768483800,
        'iso8601' => '2026-01-15T10:30:00+00:00',
        'year' => '2026',
        'month' => '01',
        'dayOfMonth' => '15',
        'ipv4' => '192.168.1.100',
        'ipv6' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        'macAddress' => '00:1B:44:11:3A:B7',
        'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'company' => 'Acme Corp',
        'jobTitle' => 'Software Engineer',
        'creditCardNumber' => '4111111111111111',
        'iban' => 'GB33BUKB20201555555555',
        'swiftBicNumber' => 'DEUTDEFF',
        'currencyCode' => 'USD',
        'colorName' => 'blue',
        'hexColor' => '#3498db',
        'rgbColor' => '52, 152, 219',
        'mimeType' => 'application/json',
        'fileExtension' => 'pdf',
        'domainName' => 'example.com',
        'safeColorName' => 'blue',
        'locale' => 'en_US',
        'countryISOAlpha3' => 'USA',
        'languageCode' => 'en',
        'emoji' => '😀',
        'realText' => 'The quick brown fox jumps over the lazy dog.',
    ];

    /**
     * Generate sample data from a Model Factory.
     *
     * @return array<string, mixed> Sample data
     */
    public function generate(?string $modelClass): array
    {
        if (!$modelClass) {
            return [];
        }

        $factoryClass = $this->resolveFactory($modelClass);

        if (!$factoryClass) {
            return [];
        }

        return $this->parseFactoryDefinition($factoryClass);
    }

    /**
     * Resolve the Factory class for a given Model.
     */
    protected function resolveFactory(string $modelClass): ?string
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        // Laravel convention: Database\Factories\{ModelName}Factory
        $modelBaseName = class_basename($modelClass);

        $candidates = [
            "Database\\Factories\\{$modelBaseName}Factory",
            "App\\Database\\Factories\\{$modelBaseName}Factory",
        ];

        // Try HasFactory trait
        if (method_exists($modelClass, 'factory')) {
            try {
                // Use reflection to get the factory class from newFactory()
                $reflection = new ReflectionClass($modelClass);

                if ($reflection->hasMethod('newFactory')) {
                    $method = $reflection->getMethod('newFactory');
                    $returnType = $method->getReturnType();

                    if ($returnType && !$returnType->isBuiltin()) {
                        $factoryName = $returnType->getName();
                        if (class_exists($factoryName)) {
                            return $factoryName;
                        }
                    }
                }
            } catch (\Throwable) {
                // Continue to fallback
            }
        }

        foreach ($candidates as $factory) {
            if (class_exists($factory)) {
                return $factory;
            }
        }

        return null;
    }

    /**
     * Parse a factory's definition() method to extract sample data.
     */
    protected function parseFactoryDefinition(string $factoryClass): array
    {
        try {
            $reflection = new ReflectionClass($factoryClass);

            if (!$reflection->hasMethod('definition')) {
                return [];
            }

            $method = $reflection->getMethod('definition');
            $filename = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            if (!$filename || !$startLine || !$endLine) {
                return [];
            }

            $source = file_get_contents($filename);
            $lines = explode("\n", $source);
            $methodSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

            return $this->parseDefinitionSource($methodSource);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Parse the definition() source code and extract field data.
     */
    protected function parseDefinitionSource(string $source): array
    {
        $data = [];

        // Match patterns like: 'field' => fake()->method() or $this->faker->method()
        preg_match_all(
            "/['\"](\w+)['\"]\s*=>\s*(?:fake\(\)|\\$this->faker)->\s*(?:unique\(\)->)?(\w+)/",
            $source,
            $fakerMatches,
            PREG_SET_ORDER
        );

        foreach ($fakerMatches as $match) {
            $field = $match[1];
            $fakerMethod = $match[2];
            $data[$field] = $this->fakerSamples[$fakerMethod] ?? $this->guessValueFromFieldName($field);
        }

        // Match patterns like: 'field' => 'literal_string'
        preg_match_all(
            "/['\"](\w+)['\"]\s*=>\s*'([^']+)'/",
            $source,
            $stringMatches,
            PREG_SET_ORDER
        );

        foreach ($stringMatches as $match) {
            if (!isset($data[$match[1]])) {
                $data[$match[1]] = $match[2];
            }
        }

        // Match patterns like: 'field' => true/false/null/number
        preg_match_all(
            "/['\"](\w+)['\"]\s*=>\s*(true|false|null|\d+(?:\.\d+)?)\s*[,\n]/",
            $source,
            $literalMatches,
            PREG_SET_ORDER
        );

        foreach ($literalMatches as $match) {
            if (!isset($data[$match[1]])) {
                $value = match ($match[2]) {
                    'true' => true,
                    'false' => false,
                    'null' => null,
                    default => is_numeric($match[2]) ? (str_contains($match[2], '.') ? (float) $match[2] : (int) $match[2]) : $match[2],
                };
                $data[$match[1]] = $value;
            }
        }

        // Match Hash/bcrypt patterns: 'password' => Hash::make('...')
        preg_match_all(
            "/['\"](\w+)['\"]\s*=>\s*(?:Hash::make|bcrypt)\(\s*'([^']+)'/",
            $source,
            $hashMatches,
            PREG_SET_ORDER
        );

        foreach ($hashMatches as $match) {
            if (!isset($data[$match[1]])) {
                $data[$match[1]] = $match[2];
            }
        }

        // Match now() / Carbon patterns
        preg_match_all(
            "/['\"](\w+)['\"]\s*=>\s*now\(\)/",
            $source,
            $nowMatches,
            PREG_SET_ORDER
        );

        foreach ($nowMatches as $match) {
            if (!isset($data[$match[1]])) {
                $data[$match[1]] = '2026-01-15T10:30:00.000000Z';
            }
        }

        // Remove password-related fields from sample data (security)
        foreach (['password', 'remember_token', 'api_token'] as $sensitive) {
            if (isset($data[$sensitive]) && $sensitive === 'password') {
                $data[$sensitive] = 'password123';
            } elseif (isset($data[$sensitive])) {
                unset($data[$sensitive]);
            }
        }

        return $data;
    }

    /**
     * Guess a sample value from the field name when faker method is unknown.
     */
    protected function guessValueFromFieldName(string $field): mixed
    {
        if (Str::contains($field, 'email')) return 'user@example.com';
        if (Str::contains($field, 'name')) return 'John Doe';
        if (Str::contains($field, 'phone')) return '+1234567890';
        if (Str::contains($field, 'password')) return 'password123';
        if (Str::contains($field, 'url') || Str::contains($field, 'link')) return 'https://example.com';
        if (Str::contains($field, 'image') || Str::contains($field, 'avatar')) return 'https://example.com/image.jpg';
        if (Str::endsWith($field, '_at')) return '2026-01-15T10:30:00.000000Z';
        if (Str::endsWith($field, '_id')) return 1;
        if (Str::startsWith($field, 'is_') || Str::startsWith($field, 'has_')) return true;
        if (Str::contains($field, 'price') || Str::contains($field, 'amount')) return 29.99;
        if (Str::contains($field, 'count') || Str::contains($field, 'quantity')) return 1;

        return 'sample_value';
    }
}
