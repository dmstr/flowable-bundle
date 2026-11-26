<?php
// file generated with AI assistance: Claude Code - 2026-11-26 20:57:21 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestKernel;

/**
 * Pins which operations of this bundle the Hydra documentation
 * (`GET /api/docs.jsonld`) presents as custom actions: the operations marked
 * with `extraProperties: ['custom_action' => true]`, which the utils bundle's
 * `CustomOperationHydraFactory` decorates with `@type` `schema:Action`, a
 * `hydra:title` equal to the operation name and the URL in `@id` (no
 * placeholder) or `hydra:uriTemplate` (with a placeholder).
 *
 * API Platform lists an operation in one of two places: GET item operations
 * (e.g. `/jobs/{id}/stacktrace`) in the `supportedOperation` of their class,
 * every POST (including `{id}` paths such as `/tasks/{id}/complete`) only in
 * the `supportedOperation` of the resource's entrypoint property. The walker
 * collects both, and the comparison is a list, not a set: in the current
 * document every operation appears in exactly one of the two places, so a
 * duplicate would be a change worth noticing.
 *
 * Only actions below the flowable prefix are compared; other bundles in the
 * kernel (api-configuration marks its own `/api/admin/...` actions) are not
 * pinned here.
 */
final class CustomActionHydraDocumentationTest extends ApiTestCase
{
    private const string DOCS = TestKernel::API_PREFIX . '/docs.jsonld';

    /**
     * hydra:title (operation name), method, URL carrier, path below the
     * flowable prefix.
     *
     * @var list<array{string, string, string, string}>
     */
    private const array EXPECTED = [
        ['flow_decision_execute', 'POST', '@id', '/decisions/execute'],
        ['flow_deployment_upload', 'POST', '@id', '/deployments/upload'],
        ['flow_dmn_deployment_upload', 'POST', '@id', '/dmn_deployments/upload'],
        ['flow_event_deployment_upload', 'POST', '@id', '/event_deployments/upload'],
        ['flow_execution_trigger', 'POST', 'hydra:uriTemplate', '/executions/{id}/trigger'],
        ['flow_external_worker_job_acquire', 'POST', '@id', '/external_worker_jobs/acquire'],
        ['flow_external_worker_job_complete', 'POST', 'hydra:uriTemplate', '/external_worker_jobs/{id}/complete'],
        ['flow_external_worker_job_fail', 'POST', 'hydra:uriTemplate', '/external_worker_jobs/{id}/fail'],
        ['flow_external_worker_job_bpmnError', 'POST', 'hydra:uriTemplate', '/external_worker_jobs/{id}/bpmnError'],
        ['flow_external_worker_job_unacquire', 'POST', 'hydra:uriTemplate', '/external_worker_jobs/{id}/unacquire'],
        ['flow_process_definition_start', 'POST', 'hydra:uriTemplate', '/process_definitions/{id}/start'],
        ['flow_task_complete', 'POST', 'hydra:uriTemplate', '/tasks/{id}/complete'],
        ['flow_job_stacktrace', 'GET', 'hydra:uriTemplate', '/jobs/{id}/stacktrace'],
        ['flow_event_instance_create', 'POST', '@id', '/event_instances'],
    ];

    public function testTheFlowableCustomActionsAreExactlyTheMarkedOperations(): void
    {
        $expected = array_map(
            static fn (array $e): string => self::describe($e[0], $e[1], $e[2], self::FLOWABLE . $e[3]),
            self::EXPECTED,
        );

        $actual = [];
        foreach ($this->flowableActions() as $action) {
            $carrier = isset($action['hydra:uriTemplate']) ? 'hydra:uriTemplate' : '@id';
            $actual[] = self::describe(
                (string) ($action['hydra:title'] ?? '(no hydra:title)'),
                (string) ($action['method'] ?? '(no method)'),
                $carrier,
                (string) ($action[$carrier] ?? '(no URL)'),
            );
        }

        self::assertEqualsCanonicalizing(
            $expected,
            $actual,
            \sprintf('%d Flowable custom actions found, %d expected', \count($actual), \count($expected)),
        );
    }

    public function testEveryFlowableCustomActionHasTheHydraShape(): void
    {
        $actions = $this->flowableActions();
        self::assertCount(\count(self::EXPECTED), $actions, 'Flowable custom actions in the Hydra documentation');

        $names = array_column(self::EXPECTED, 0);
        foreach ($actions as $action) {
            $label = json_encode($action, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            self::assertContains('schema:Action', (array) $action['@type'], $label);
            self::assertContains($action['hydra:title'] ?? null, $names, 'hydra:title is the operation name: ' . $label);

            $url = $action['hydra:uriTemplate'] ?? $action['@id'] ?? '';
            if (str_contains($url, '{')) {
                self::assertArrayHasKey('hydra:uriTemplate', $action, $label);
                self::assertArrayNotHasKey('@id', $action, $label);
            } else {
                self::assertArrayHasKey('@id', $action, $label);
                self::assertArrayNotHasKey('hydra:uriTemplate', $action, $label);
            }
        }
    }

    /**
     * The custom actions whose URL is below the flowable prefix, or whose
     * name says they belong to this bundle (so an action without URL carrier
     * is not silently dropped).
     *
     * @return list<array<string, mixed>>
     */
    private function flowableActions(): array
    {
        $actions = [];
        foreach ($this->operations() as $operation) {
            if (!\in_array('schema:Action', (array) ($operation['@type'] ?? []), true)) {
                continue;
            }
            $url = (string) ($operation['hydra:uriTemplate'] ?? $operation['@id'] ?? '');
            if (str_starts_with($url, self::FLOWABLE . '/') || str_starts_with((string) ($operation['hydra:title'] ?? ''), 'flow_')) {
                $actions[] = $operation;
            }
        }

        return $actions;
    }

    /**
     * Every operation node of the Hydra documentation: the `supportedOperation`
     * of each class and of each property of a class (the entrypoint lists a
     * resource's collection and POST operations on its property).
     *
     * @return list<array<string, mixed>>
     */
    private function operations(): array
    {
        $response = $this->requestAs('GET', self::DOCS, []);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $operations = [];
        foreach (self::json($response)['supportedClass'] ?? [] as $class) {
            array_push($operations, ...self::nodes($class['supportedOperation'] ?? []));
            foreach ($class['supportedProperty'] ?? [] as $property) {
                if (\is_array($property['property'] ?? null)) {
                    array_push($operations, ...self::nodes($property['property']['supportedOperation'] ?? []));
                }
            }
        }

        return $operations;
    }

    /**
     * JSON-LD writes a single value without the list around it (the
     * entrypoint's own `index` operation is one object).
     *
     * @param array<mixed> $value
     * @return list<array<string, mixed>>
     */
    private static function nodes(array $value): array
    {
        return array_is_list($value) ? $value : [$value];
    }

    private static function describe(string $title, string $method, string $carrier, string $url): string
    {
        return \sprintf('%s: %s %s=%s', $title, $method, $carrier, $url);
    }
}
