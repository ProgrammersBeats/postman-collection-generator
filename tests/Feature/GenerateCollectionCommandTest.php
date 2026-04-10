<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use ProgrammersBeats\PostmanGenerator\Tests\TestCase;

class GenerateCollectionCommandTest extends TestCase
{
    protected string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = sys_get_temp_dir() . '/postman-test-' . uniqid();
        File::makeDirectory($this->outputPath, 0755, true);

        config(['postman-generator.output_path' => $this->outputPath]);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (File::isDirectory($this->outputPath)) {
            File::deleteDirectory($this->outputPath);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_generate_a_collection_with_default_options(): void
    {
        $this->artisan('postman:collection', [
            '--no-interaction' => true,
            '--name' => 'Test Collection',
        ])
            ->assertSuccessful();

        $expectedFile = $this->outputPath . '/test-collection.postman_collection.json';
        $this->assertFileExists($expectedFile);

        $content = json_decode(File::get($expectedFile), true);
        $this->assertEquals('Test Collection', $content['info']['name']);
    }

    /** @test */
    public function it_includes_bearer_auth_when_flag_is_set(): void
    {
        $this->artisan('postman:collection', [
            '--no-interaction' => true,
            '--Bearer' => true,
            '--name' => 'Auth Test',
        ])
            ->assertSuccessful();

        $expectedFile = $this->outputPath . '/auth-test.postman_collection.json';
        $content = json_decode(File::get($expectedFile), true);

        $this->assertArrayHasKey('auth', $content);
        $this->assertEquals('bearer', $content['auth']['type']);
    }

    /** @test */
    public function it_generates_environment_file_when_requested(): void
    {
        $this->artisan('postman:collection', [
            '--no-interaction' => true,
            '--with-env' => true,
            '--name' => 'Env Test',
        ])
            ->assertSuccessful();

        $expectedEnvFile = $this->outputPath . '/env-test.postman_environment.json';
        $this->assertFileExists($expectedEnvFile);

        $content = json_decode(File::get($expectedEnvFile), true);
        $this->assertArrayHasKey('values', $content);
    }

    /** @test */
    public function it_groups_routes_by_prefix_strategy(): void
    {
        $this->artisan('postman:collection', [
            '--no-interaction' => true,
            '--strategy' => 'prefix',
            '--name' => 'Prefix Test',
        ])
            ->assertSuccessful();

        $expectedFile = $this->outputPath . '/prefix-test.postman_collection.json';
        $content = json_decode(File::get($expectedFile), true);

        // Check that items are grouped
        $this->assertNotEmpty($content['item']);
    }

    /** @test */
    public function it_groups_routes_by_controller_strategy(): void
    {
        $this->artisan('postman:collection', [
            '--no-interaction' => true,
            '--strategy' => 'controller',
            '--name' => 'Controller Test',
        ])
            ->assertSuccessful();

        $expectedFile = $this->outputPath . '/controller-test.postman_collection.json';
        $this->assertFileExists($expectedFile);
    }

    /** @test */
    public function it_groups_routes_by_resource_strategy(): void
    {
        $this->artisan('postman:collection', [
            '--no-interaction' => true,
            '--strategy' => 'resource',
            '--name' => 'Resource Test',
        ])
            ->assertSuccessful();

        $expectedFile = $this->outputPath . '/resource-test.postman_collection.json';
        $this->assertFileExists($expectedFile);
    }

    /** @test */
    public function it_can_generate_minimal_collection(): void
    {
        $this->artisan('postman:collection', [
            '--no-interaction' => true,
            '--minimal' => true,
            '--name' => 'Minimal Test',
        ])
            ->assertSuccessful();

        $expectedFile = $this->outputPath . '/minimal-test.postman_collection.json';
        $this->assertFileExists($expectedFile);
    }

    /** @test */
    public function it_can_generate_full_descriptive_collection(): void
    {
        $this->artisan('postman:collection', [
            '--no-interaction' => true,
            '--full' => true,
            '--name' => 'Full Test',
        ])
            ->assertSuccessful();

        $expectedFile = $this->outputPath . '/full-test.postman_collection.json';
        $this->assertFileExists($expectedFile);
    }

    /** @test */
    public function it_uses_custom_output_path(): void
    {
        $customPath = sys_get_temp_dir() . '/custom-postman-' . uniqid();
        File::makeDirectory($customPath, 0755, true);

        try {
            $this->artisan('postman:collection', [
                '--no-interaction' => true,
                '--output' => $customPath,
                '--name' => 'Custom Path Test',
            ])
                ->assertSuccessful();

            $expectedFile = $customPath . '/custom-path-test.postman_collection.json';
            $this->assertFileExists($expectedFile);
        } finally {
            File::deleteDirectory($customPath);
        }
    }
}
