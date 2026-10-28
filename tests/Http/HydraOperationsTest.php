<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:50:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The `hydra:operation` list of an item response is filtered by the same
 * `security` expressions: a user holding all 13 permissions sees every
 * operation the item had when `ROLE_FLOWABLE_ADMIN` guarded them (the sets
 * below), a pure reader sees the read operations only.
 */
final class HydraOperationsTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string, list<string>, list<string>}> item, all operations, read operations
     */
    public static function items(): iterable
    {
        yield 'process instance' => ['process_instances/p1', ['GET process_instances/p1', 'DELETE process_instances/p1'], ['GET process_instances/p1']];
        yield 'task' => [
            'tasks/t1',
            ['GET tasks/t1', 'POST tasks/t1/complete', 'GET tasks/t1/input_schema'],
            ['GET tasks/t1', 'GET tasks/t1/input_schema'],
        ];
        yield 'process definition' => [
            'process_definitions/d1',
            ['GET process_definitions/d1', 'POST process_definitions/d1/start', 'GET process_definitions/d1/input_schema'],
            ['GET process_definitions/d1', 'GET process_definitions/d1/input_schema'],
        ];
        yield 'deployment' => ['deployments/d1', ['GET deployments/d1', 'DELETE deployments/d1'], ['GET deployments/d1']];
        yield 'execution' => ['executions/x1', ['GET executions/x1', 'POST executions/x1/trigger'], ['GET executions/x1']];
        yield 'job' => ['jobs/j1', ['GET jobs/j1', 'GET jobs/j1/stacktrace'], ['GET jobs/j1']];
        yield 'external worker job' => [
            'external_worker_jobs/j1',
            [
                'GET external_worker_jobs/j1',
                'POST external_worker_jobs/j1/complete',
                'POST external_worker_jobs/j1/fail',
                'POST external_worker_jobs/j1/bpmnError',
                'POST external_worker_jobs/j1/unacquire',
            ],
            ['GET external_worker_jobs/j1'],
        ];
    }

    /**
     * @param list<string> $all
     * @param list<string> $reads
     */
    #[DataProvider('items')]
    public function testOperationsFollowThePermissions(string $item, array $all, array $reads): void
    {
        $allPermissions = array_map(P::roleFor(...), P::ALL);

        self::assertEqualsCanonicalizing($all, $this->operations($item, $allPermissions));
        self::assertEqualsCanonicalizing($all, $this->operations($item, ['ROLE_FLOWABLE_ADMIN']));
        self::assertEqualsCanonicalizing($reads, $this->operations($item, ['ROLE_USER']));
        self::assertEqualsCanonicalizing($reads, $this->operations($item, [P::roleFor(P::READ)]));
    }

    /**
     * @param list<string> $roles
     * @return list<string> "METHOD path below /api/flowable"
     */
    private function operations(string $item, array $roles): array
    {
        $response = $this->requestAs('GET', self::FLOWABLE . '/' . $item, $roles);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $operations = [];
        foreach (self::json($response)['hydra:operation'] ?? [] as $operation) {
            $operations[] = $operation['hydra:method'] . ' ' . substr($operation['@id'], strlen(self::FLOWABLE) + 1);
        }

        return $operations;
    }
}
