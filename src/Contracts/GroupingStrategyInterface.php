<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Contracts;

use Illuminate\Support\Collection;
use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;

interface GroupingStrategyInterface
{
    /**
     * Get the strategy name.
     */
    public function getName(): string;

    /**
     * Get the strategy description.
     */
    public function getDescription(): string;

    /**
     * Group routes according to the strategy.
     *
     * @param Collection<int, ParsedRoute> $routes
     * @return array<string, array<int, ParsedRoute>>
     */
    public function group(Collection $routes): array;

    /**
     * Get the folder name for a route group.
     */
    public function getFolderName(string $key, array $routes): string;
}
