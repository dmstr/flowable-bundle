<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:55:54 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;

/**
 * The cap of the composite tool flowable_history_get over the real MCP
 * transport, against an engine stub that sorts and pages its rows as the
 * engine does: of more than 200 activities and failed decision executions
 * the newest 200 come back, oldest first, with the engine's total.
 */
final class ProcessHistoryCapTest extends ApiTestCase
{
    private const string TOOL = 'flowable_history_get';

    public function testMoreThan200RowsReturnTheNewest200OldestFirst(): void
    {
        $this->engine()
            ->withList('/historic-activity-instances', self::rows('a', 450, []))
            ->withList('/historic-decision-executions', self::rows('d', 250, ['failed' => true]));

        $result = $this->mcpCallTool(self::TOOL, ['id' => 'pi-1'], [P::roleFor(P::MCP_READ)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertNotTrue($result['result']['isError'] ?? true, $encoded);
        $history = $result['result']['structuredContent'] ?? null;
        self::assertIsArray($history, $encoded);

        self::assertSame(self::ids('a', 251, 450), array_column($history['activities'], 'id'));
        self::assertSame(450, $history['activitiesTotal']);
        self::assertSame(self::ids('d', 51, 250), array_column($history['failedDecisionExecutions'], 'id'));
        self::assertSame(250, $history['failedDecisionExecutionsTotal']);

        $queries = $this->queriesFor('/historic-activity-instances');
        self::assertCount(1, $queries);
        self::assertSame(['start' => '0', 'size' => '200', 'sort' => 'startTime', 'order' => 'desc'], array_intersect_key($queries[0], array_flip(['start', 'size', 'sort', 'order'])));
        self::assertSame('pi-1', $queries[0]['processInstanceId'] ?? null);
    }

    /**
     * Rows "<prefix>-1" .. "<prefix>-<count>" with ascending start times,
     * stored shuffled so that only the requested sort puts them in order.
     *
     * @param array<string, mixed> $extra
     * @return list<array<string, mixed>>
     */
    private static function rows(string $prefix, int $count, array $extra): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; ++$i) {
            $rows[] = ['id' => sprintf('%s-%d', $prefix, $i), 'startTime' => sprintf('2026-01-01T00:00:00.%03dZ', $i)] + $extra;
        }
        mt_srand(42);
        shuffle($rows);

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function ids(string $prefix, int $from, int $to): array
    {
        return array_map(static fn (int $i): string => sprintf('%s-%d', $prefix, $i), range($from, $to));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queriesFor(string $pathSuffix): array
    {
        $engine = $this->engine();
        $queries = [];
        foreach ($engine->requests as $index => $request) {
            if (str_ends_with($request['path'], $pathSuffix)) {
                $queries[] = $engine->queries[$index];
            }
        }

        return $queries;
    }
}
