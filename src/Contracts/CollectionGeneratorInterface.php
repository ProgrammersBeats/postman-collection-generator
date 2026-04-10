<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Contracts;

use Illuminate\Support\Collection;
use ProgrammersBeats\PostmanGenerator\DTOs\GeneratorOptions;
use ProgrammersBeats\PostmanGenerator\DTOs\PostmanCollection;

interface CollectionGeneratorInterface
{
    /**
     * Generate a Postman collection from parsed routes.
     */
    public function generate(GeneratorOptions $options): PostmanCollection;

    /**
     * Export the collection to a JSON file.
     */
    public function export(PostmanCollection $collection, string $path): string;

    /**
     * Generate an environment file for the collection.
     */
    public function generateEnvironment(string $name): array;
}
