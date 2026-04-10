<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\DTOs;

class GeneratorOptions
{
    public function __construct(
        public readonly string $collectionName,
        public readonly string $description,
        public readonly string $groupingStrategy,
        public readonly bool $includeBearer,
        public readonly bool $fullDescriptive,
        public readonly bool $includeEnvironment,
        public readonly bool $includeMiddlewareInfo,
        public readonly bool $includeValidationRules,
        public readonly bool $includePHPDoc,
        public readonly bool $includeExamples,
        public readonly ?string $baseUrl,
        public readonly ?string $outputPath,
        public readonly array $authMiddleware = ['auth', 'auth:sanctum', 'auth:api', 'auth:web'],
    ) {}

    /**
     * Create options from array of values.
     */
    public static function fromArray(array $options): self
    {
        return new self(
            collectionName: $options['collection_name'] ?? config('postman-generator.collection_name', 'API Collection'),
            description: $options['description'] ?? config('postman-generator.description', ''),
            groupingStrategy: $options['grouping_strategy'] ?? config('postman-generator.grouping.default', 'prefix'),
            includeBearer: $options['include_bearer'] ?? true,
            fullDescriptive: $options['full_descriptive'] ?? true,
            includeEnvironment: $options['include_environment'] ?? true,
            includeMiddlewareInfo: $options['include_middleware'] ?? config('postman-generator.documentation.include_middleware', true),
            includeValidationRules: $options['include_validation_rules'] ?? config('postman-generator.documentation.include_validation_rules', true),
            includePHPDoc: $options['include_phpdoc'] ?? config('postman-generator.documentation.include_phpdoc', true),
            includeExamples: $options['include_examples'] ?? config('postman-generator.documentation.include_examples', true),
            baseUrl: $options['base_url'] ?? config('postman-generator.base_url'),
            outputPath: $options['output_path'] ?? config('postman-generator.output_path'),
            authMiddleware: $options['auth_middleware'] ?? ['auth', 'auth:sanctum', 'auth:api', 'auth:web'],
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'collection_name' => $this->collectionName,
            'description' => $this->description,
            'grouping_strategy' => $this->groupingStrategy,
            'include_bearer' => $this->includeBearer,
            'full_descriptive' => $this->fullDescriptive,
            'include_environment' => $this->includeEnvironment,
            'include_middleware' => $this->includeMiddlewareInfo,
            'include_validation_rules' => $this->includeValidationRules,
            'include_phpdoc' => $this->includePHPDoc,
            'include_examples' => $this->includeExamples,
            'base_url' => $this->baseUrl,
            'output_path' => $this->outputPath,
            'auth_middleware' => $this->authMiddleware,
        ];
    }
}
