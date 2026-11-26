<?php
// file generated with AI assistance: Claude Code - 2026-11-26 20:56:33 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Metadata;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use PHPUnit\Framework\TestCase;

/**
 * Pins the set of HTTP operations marked as custom actions
 * (`extraProperties['custom_action'] === true`), read from the real attribute
 * metadata of src/ApiResource without a container. The attributes factory
 * merges resource and operation extraProperties, so the resource `label` must
 * survive next to the marker. MCP tools are not looked at.
 */
final class CustomActionMarkerTest extends TestCase
{
    private const MARKER = 'custom_action';

    /**
     * The custom actions, keyed as `METHOD uriTemplate` (uriTemplate without
     * the resource's route prefix, as the attributes factory leaves it).
     */
    private const EXPECTED = [
        'POST /decisions/execute',
        'POST /deployments/upload',
        'POST /dmn_deployments/upload',
        'POST /event_deployments/upload',
        'POST /executions/{id}/trigger',
        'POST /external_worker_jobs/acquire',
        'POST /external_worker_jobs/{id}/complete',
        'POST /external_worker_jobs/{id}/fail',
        'POST /external_worker_jobs/{id}/bpmnError',
        'POST /external_worker_jobs/{id}/unacquire',
        'POST /process_definitions/{id}/start',
        'POST /tasks/{id}/complete',
        'GET /jobs/{id}/stacktrace',
        'POST /event_instances',
    ];

    /**
     * Operations that look like actions but must stay unmarked.
     */
    private const UNMARKED = [
        'GET /process_definitions/{id}/input_schema',
        'GET /tasks/{id}/input_schema',
        'POST /process_instances',
    ];

    public function testExactlyTheCustomActionsCarryTheMarker(): void
    {
        $marked = [];
        foreach (self::operations() as [$key, $operation]) {
            if (true === ($operation->getExtraProperties()[self::MARKER] ?? null)) {
                $marked[] = $key;
            }
        }

        self::assertEqualsCanonicalizing(self::EXPECTED, $marked);
    }

    public function testNoOperationCarriesANonTrueMarkerValue(): void
    {
        $invalid = [];
        foreach (self::operations() as [$key, $operation]) {
            $extra = $operation->getExtraProperties();
            if (\array_key_exists(self::MARKER, $extra) && true !== $extra[self::MARKER]) {
                $invalid[$key] = $extra[self::MARKER];
            }
        }

        self::assertSame([], $invalid, 'custom_action must be exactly true where it is set.');
    }

    public function testMarkedOperationsKeepTheResourceLabel(): void
    {
        $checked = 0;
        foreach (self::operations() as [$key, $operation]) {
            $extra = $operation->getExtraProperties();
            if (true !== ($extra[self::MARKER] ?? null)) {
                continue;
            }
            self::assertIsString($extra['label'] ?? null, $key.' lost the resource label.');
            self::assertNotSame('', $extra['label'], $key.' has an empty label.');
            ++$checked;
        }

        self::assertSame(\count(self::EXPECTED), $checked);
    }

    public function testInputSchemaAndProcessInstanceCreateStayUnmarked(): void
    {
        $byKey = [];
        foreach (self::operations() as [$key, $operation]) {
            $byKey[$key][] = $operation;
        }

        foreach (self::UNMARKED as $key) {
            self::assertArrayHasKey($key, $byKey, $key.' does not exist.');
            foreach ($byKey[$key] as $operation) {
                self::assertArrayNotHasKey(self::MARKER, $operation->getExtraProperties(), $key.' must not be marked.');
            }
        }
    }

    /**
     * All HTTP operations (not MCP tools) of the bundle's resources, as
     * `[METHOD uriTemplate, operation]` pairs; a list, so duplicates stay.
     *
     * @return list<array{string, HttpOperation}>
     */
    private static function operations(): array
    {
        $factory = new AttributesResourceMetadataCollectionFactory();
        $operations = [];
        foreach (glob(\dirname(__DIR__, 2).'/src/ApiResource/*.php') ?: [] as $file) {
            $class = 'Dmstr\\Flowable\\ApiResource\\'.basename($file, '.php');
            foreach ($factory->create($class) as $resource) {
                foreach ($resource->getOperations() ?? [] as $operation) {
                    if ($operation instanceof HttpOperation) {
                        $operations[] = [$operation->getMethod().' '.$operation->getUriTemplate(), $operation];
                    }
                }
            }
        }

        return $operations;
    }
}
