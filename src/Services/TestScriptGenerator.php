<?php

declare(strict_types=1);

namespace ProgrammersBeats\PostmanGenerator\Services;

use ProgrammersBeats\PostmanGenerator\DTOs\ParsedRoute;

class TestScriptGenerator
{
    /**
     * Generate Postman test scripts for a route.
     *
     * @return array<string> Script lines
     */
    public function generate(ParsedRoute $route): array
    {
        $method = $route->getPrimaryMethod();
        $expectedStatus = $route->getExpectedStatusCode();

        $lines = [];
        $lines[] = '// Auto-generated test scripts by Laravel Postman Generator';
        $lines[] = '';

        // Status code test
        $lines[] = "pm.test('Status code is {$expectedStatus}', function () {";
        if ($method === 'POST') {
            $lines[] = '    pm.expect(pm.response.code).to.be.oneOf([200, 201]);';
        } elseif ($method === 'DELETE') {
            $lines[] = '    pm.expect(pm.response.code).to.be.oneOf([200, 204]);';
        } else {
            $lines[] = "    pm.response.to.have.status({$expectedStatus});";
        }
        $lines[] = '});';
        $lines[] = '';

        // Response time test
        $lines[] = "pm.test('Response time is acceptable', function () {";
        $lines[] = '    pm.expect(pm.response.responseTime).to.be.below(2000);';
        $lines[] = '});';
        $lines[] = '';

        // Content-Type test
        $lines[] = "pm.test('Content-Type is JSON', function () {";
        $lines[] = "    pm.response.to.have.header('Content-Type');";
        $lines[] = "    pm.expect(pm.response.headers.get('Content-Type')).to.include('application/json');";
        $lines[] = '});';
        $lines[] = '';

        // Response is valid JSON test
        $lines[] = "pm.test('Response is valid JSON', function () {";
        $lines[] = '    pm.response.to.be.json;';
        $lines[] = '    const json = pm.response.json();';
        $lines[] = "    pm.expect(json).to.be.an('object');";
        $lines[] = '});';

        // Response structure test for resource routes
        if ($route->isResourceRoute() && $method === 'GET') {
            $lines[] = '';
            if ($route->action === 'index') {
                $lines[] = "pm.test('Response has data array (paginated)', function () {";
                $lines[] = '    const json = pm.response.json();';
                $lines[] = '    if (json.data !== undefined) {';
                $lines[] = "        pm.expect(json.data).to.be.an('array');";
                $lines[] = '    }';
                $lines[] = '});';
            } else {
                $lines[] = "pm.test('Response has data object', function () {";
                $lines[] = '    const json = pm.response.json();';
                $lines[] = '    if (json.data !== undefined) {';
                $lines[] = "        pm.expect(json.data).to.be.an('object');";
                $lines[] = '    }';
                $lines[] = '});';
            }
        }

        // Validation error test for POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($route->validationRules)) {
            $lines[] = '';
            $lines[] = "pm.test('No validation errors', function () {";
            $lines[] = '    if (pm.response.code === 422) {';
            $lines[] = '        const json = pm.response.json();';
            $lines[] = "        pm.expect.fail('Validation failed: ' + JSON.stringify(json.errors || json.message));";
            $lines[] = '    }';
            $lines[] = '});';
        }

        // Auth test
        if ($route->requiresAuth) {
            $lines[] = '';
            $lines[] = "pm.test('Not unauthorized (token is valid)', function () {";
            $lines[] = '    pm.expect(pm.response.code).to.not.equal(401);';
            $lines[] = '});';
        }

        return $lines;
    }

    /**
     * Build Postman event entry for test scripts.
     */
    public function buildTestEvent(ParsedRoute $route): array
    {
        return [
            'listen' => 'test',
            'script' => [
                'type' => 'text/javascript',
                'exec' => $this->generate($route),
            ],
        ];
    }
}
