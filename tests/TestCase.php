<?php

declare(strict_types=1);

namespace AmeerHamzaAH\PostmanGenerator\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use AmeerHamzaAH\PostmanGenerator\PostmanGeneratorServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PostmanGeneratorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('postman-generator.collection_name', 'Test API');
        $app['config']->set('postman-generator.output_path', sys_get_temp_dir());
    }

    protected function defineRoutes($router): void
    {
        // Define test routes
        $router->prefix('api')->group(function ($router) {
            // Auth routes
            $router->post('/login', function () {
                return response()->json(['token' => 'test-token']);
            })->name('login');

            $router->post('/logout', function () {
                return response()->json(['message' => 'Logged out']);
            })->name('logout')->middleware('auth:sanctum');

            // User routes
            $router->middleware('auth:sanctum')->prefix('users')->name('users.')->group(function ($router) {
                $router->get('/', function () {
                    return response()->json([]);
                })->name('index');

                $router->post('/', function () {
                    return response()->json([]);
                })->name('store');

                $router->get('/{user}', function ($user) {
                    return response()->json([]);
                })->name('show');

                $router->put('/{user}', function ($user) {
                    return response()->json([]);
                })->name('update');

                $router->delete('/{user}', function ($user) {
                    return response()->json([]);
                })->name('destroy');
            });

            // Posts routes (public)
            $router->prefix('posts')->name('posts.')->group(function ($router) {
                $router->get('/', function () {
                    return response()->json([]);
                })->name('index');

                $router->get('/{post}', function ($post) {
                    return response()->json([]);
                })->name('show');
            });
        });
    }
}
