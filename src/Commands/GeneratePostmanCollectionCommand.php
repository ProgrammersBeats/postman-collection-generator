<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ProgrammersBeats\PostmanGenerator\Contracts\CollectionGeneratorInterface;
use ProgrammersBeats\PostmanGenerator\DTOs\GeneratorOptions;
use ProgrammersBeats\PostmanGenerator\Services\CollectionGenerator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class GeneratePostmanCollectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postman:collection
                            {--Bearer : Include Bearer token authentication with Sanctum pre-scripts}
                            {--name= : Collection name}
                            {--output= : Output path for the collection file}
                            {--strategy= : Grouping strategy (prefix, controller, resource, name, middleware, tag)}
                            {--full : Generate full descriptive collection with all documentation}
                            {--minimal : Generate minimal collection without extra documentation}
                            {--no-tests : Disable auto-generated test scripts}
                            {--no-responses : Disable example response generation}
                            {--no-factory : Disable factory-based sample data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Postman collection from your Laravel routes with nested folder structure and Sanctum authentication support';

    /**
     * Available grouping strategies with descriptions.
     */
    protected array $strategies = [
        'prefix' => 'URL Prefix Based with Nested Folders (e.g., /api/auth/pin → "Auth > Pin")',
        'controller' => 'Controller Based (e.g., UserController → "User")',
        'resource' => 'Resource Controller (CRUD grouped together)',
        'name' => 'Route Name Based (e.g., api.users.index → "Users")',
        'middleware' => 'Middleware Based (e.g., auth:sanctum together)',
        'tag' => 'PHPDoc @tag Annotation Based',
    ];

    public function __construct(
        protected CollectionGeneratorInterface $generator,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayBanner();

        // Check if we should skip interaction
        $interactive = !$this->option('no-interaction');

        // Gather options
        $options = $interactive
            ? $this->gatherOptionsInteractively()
            : $this->gatherOptionsFromFlags();

        // Generate the collection
        $this->info('');

        $collection = spin(
            fn() => $this->generator->generate($options),
            'Generating Postman collection...'
        );

        // Determine output path with auto-versioning
        // Format: ProjectName-Year-vN (e.g., Neorecruits-2026-v1, Neorecruits-2026-v2)
        $outputPath = $options->outputPath ?? config('postman-generator.output_path', storage_path('postman'));
        $year = date('Y');
        $slugName = Str::slug($options->collectionName);
        $version = $this->getNextVersion($outputPath, $slugName, $year);

        $filename = "{$slugName}-{$year}-v{$version}.postman_collection.json";
        $fullPath = rtrim($outputPath, '/') . '/' . $filename;

        // Export the single collection file (includes everything - no env file needed)
        $exportedPath = spin(
            fn() => $this->generator->export($collection, $fullPath),
            'Exporting collection to file...'
        );

        $this->newLine();
        info("Collection exported to: {$exportedPath}");

        // Display summary
        $this->displaySummary($options, $exportedPath);

        return self::SUCCESS;
    }

    /**
     * Display the banner.
     */
    protected function displayBanner(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>+----------------------------------------------------------+</>');
        $this->line('<fg=cyan>|</>      <fg=white;options=bold>Laravel Postman Collection Generator</>              <fg=cyan>|</>');
        $this->line('<fg=cyan>|</>      <fg=gray>With Sanctum Auth & Nested Folder Support</>          <fg=cyan>|</>');
        $this->line('<fg=cyan>+----------------------------------------------------------+</>');
        $this->newLine();
    }

    /**
     * Gather options interactively from the user.
     */
    protected function gatherOptionsInteractively(): GeneratorOptions
    {
        // Collection type
        $collectionType = select(
            label: 'What type of collection would you like to generate?',
            options: [
                'full' => 'Full Descriptive - Complete documentation with parameters, middleware, and examples',
                'standard' => 'Standard - Basic documentation with route info and authentication',
                'minimal' => 'Minimal - Just the routes with no extra documentation',
            ],
            default: 'full',
        );

        // Grouping strategy
        $strategy = select(
            label: 'How would you like to group your routes?',
            options: $this->strategies,
            default: 'prefix',
        );

        // Collection name
        $defaultName = config('app.name', 'Laravel') . ' API';
        $collectionName = text(
            label: 'Collection name',
            placeholder: $defaultName,
            default: $this->option('name') ?? $defaultName,
            hint: 'This will be the name shown in Postman',
        );

        // Bearer token / Sanctum support
        $includeBearer = $this->option('Bearer') || confirm(
            label: 'Include Bearer token authentication with Sanctum pre-scripts?',
            default: true,
            hint: 'Automatically stores and uses tokens from login endpoints',
        );

        // Output path
        $defaultOutput = config('postman-generator.output_path', storage_path('postman'));
        $outputPath = text(
            label: 'Output directory',
            placeholder: $defaultOutput,
            default: $this->option('output') ?? $defaultOutput,
        );

        // Advanced options for full descriptive mode
        $includeMiddleware = true;
        $includeValidation = true;
        $includePHPDoc = true;
        $includeExamples = true;

        if ($collectionType === 'minimal') {
            $includeMiddleware = false;
            $includeValidation = false;
            $includePHPDoc = false;
            $includeExamples = false;
        } elseif ($collectionType === 'standard') {
            $includeExamples = false;
        } elseif ($collectionType === 'full') {
            // Ask about advanced options
            $advancedOptions = multiselect(
                label: 'Select documentation options to include',
                options: [
                    'middleware' => 'Middleware information',
                    'validation' => 'Validation rules as request body',
                    'phpdoc' => 'PHPDoc descriptions',
                    'examples' => 'Example request/response bodies',
                ],
                default: ['middleware', 'validation', 'phpdoc', 'examples'],
                hint: 'Use space to toggle, enter to confirm',
            );

            $includeMiddleware = in_array('middleware', $advancedOptions);
            $includeValidation = in_array('validation', $advancedOptions);
            $includePHPDoc = in_array('phpdoc', $advancedOptions);
            $includeExamples = in_array('examples', $advancedOptions);
        }

        return GeneratorOptions::fromArray([
            'collection_name' => $collectionName,
            'description' => config('postman-generator.description', ''),
            'grouping_strategy' => $strategy,
            'include_bearer' => $includeBearer,
            'full_descriptive' => $collectionType === 'full',
            'include_middleware' => $includeMiddleware,
            'include_validation_rules' => $includeValidation,
            'include_phpdoc' => $includePHPDoc,
            'include_examples' => $includeExamples,
            'output_path' => $outputPath,
            'include_test_scripts' => !$this->option('no-tests'),
            'include_example_responses' => !$this->option('no-responses'),
            'include_factory_data' => !$this->option('no-factory'),
        ]);
    }

    /**
     * Gather options from command flags (non-interactive mode).
     */
    protected function gatherOptionsFromFlags(): GeneratorOptions
    {
        $isMinimal = $this->option('minimal');
        $isFull = $this->option('full');

        return GeneratorOptions::fromArray([
            'collection_name' => $this->option('name') ?? config('app.name', 'Laravel') . ' API',
            'description' => config('postman-generator.description', ''),
            'grouping_strategy' => $this->option('strategy') ?? config('postman-generator.grouping.default', 'prefix'),
            'include_bearer' => $this->option('Bearer'),
            'full_descriptive' => $isFull || !$isMinimal,
            'include_middleware' => !$isMinimal,
            'include_validation_rules' => !$isMinimal,
            'include_phpdoc' => !$isMinimal,
            'include_examples' => $isFull,
            'output_path' => $this->option('output') ?? config('postman-generator.output_path'),
            'include_test_scripts' => !$this->option('no-tests'),
            'include_example_responses' => !$this->option('no-responses'),
            'include_factory_data' => !$this->option('no-factory'),
        ]);
    }

    /**
     * Get the next version number for auto-versioned filenames.
     * Scans existing files: project-2026-v1, project-2026-v2, etc.
     */
    protected function getNextVersion(string $outputPath, string $slugName, string $year): int
    {
        if (!File::isDirectory($outputPath)) {
            return 1;
        }

        $existing = File::glob($outputPath . "/{$slugName}-{$year}-v*.postman_collection.json");

        if (empty($existing)) {
            return 1;
        }

        $maxVersion = 0;
        foreach ($existing as $file) {
            $basename = pathinfo($file, PATHINFO_FILENAME);
            if (preg_match("/-v(\d+)\./", $basename, $match)) {
                $maxVersion = max($maxVersion, (int) $match[1]);
            }
        }

        return $maxVersion + 1;
    }

    /**
     * Display summary after generation.
     */
    protected function displaySummary(GeneratorOptions $options, string $path): void
    {
        $this->newLine();
        $this->line('<fg=green>+----------------------------------------------------------+</>');
        $this->line('<fg=green>|</>                  <fg=white;options=bold>Generation Complete!</>                   <fg=green>|</>');
        $this->line('<fg=green>+----------------------------------------------------------+</>');
        $this->newLine();

        $this->line('<fg=yellow>Summary:</>');
        $this->line("  Collection: <fg=white>{$options->collectionName}</>");
        $this->line("  File: <fg=white>" . basename($path) . "</>");
        $this->line("  Grouping: <fg=white>" . Str::title($options->groupingStrategy) . "</>");
        $this->line("  Bearer Auth: <fg=white>" . ($options->includeBearer ? 'Yes' : 'No') . "</>");
        $this->line("  Test Scripts: <fg=white>" . ($options->includeTestScripts ? 'Yes' : 'No') . "</>");
        $this->line("  Example Responses: <fg=white>" . ($options->includeExampleResponses ? 'Yes' : 'No') . "</>");
        $this->line("  Factory Data: <fg=white>" . ($options->includeFactoryData ? 'Yes' : 'No') . "</>");

        $this->newLine();
        $this->line('<fg=yellow>Zero-Config Setup:</>');
        $this->line("  1. Import the single collection file into Postman");
        $this->line("  2. <fg=green>Start making requests - everything is pre-configured!</>");
        $this->line("  3. No separate environment file needed");

        if (config('postman-generator.documentation.enabled', true)) {
            $docRoute = config('postman-generator.documentation.route', 'api-documentation');
            $this->newLine();
            $this->line('<fg=yellow>API Documentation:</>');
            $this->line("  View in browser: <fg=cyan>" . url($docRoute) . "</>");
        }

        if ($options->includeBearer) {
            $this->newLine();
            $this->line('<fg=yellow>Authentication:</>');
            $this->line("  Call a login endpoint first");
            $this->line("  Token will be automatically stored in <fg=cyan>Bearer</> variable");
            $this->line("  All authenticated requests will use this token automatically");
        }

        $this->newLine();
        $this->line('<fg=yellow>Other Commands:</>');
        $this->line("  <fg=cyan>php artisan postman:diff</>  - Compare routes with last collection (API changelog)");

        $this->newLine();
        outro('Happy API testing!');
    }
}
