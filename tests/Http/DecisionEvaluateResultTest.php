<?php
// file generated with AI assistance: Claude Code - 2026-11-26 16:02:34 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The result shape of a DMN evaluation (POST /decisions/execute and the MCP
 * tool flowable_dmn_evaluate), pinned as it is today: `result` is a list of
 * rows and every row is what the engine returned for one matched rule, a
 * list of result variables `{name, type, value}`, passed on unchanged. It is
 * NOT a map of output name to value. The single-result evaluation returns
 * one flat list of result variables, which becomes the only row.
 *
 * The engine answers below are the shapes the processor expects
 * (`resultVariables`); they are not verified against a real engine here.
 */
final class DecisionEvaluateResultTest extends ApiTestCase
{
    private const array ROW_GOLD = [
        ['name' => 'discount', 'type' => 'double', 'value' => 0.1],
        ['name' => 'label', 'type' => 'string', 'value' => 'gold'],
    ];

    private const array ROW_SILVER = [
        ['name' => 'discount', 'type' => 'double', 'value' => 0.05],
        ['name' => 'label', 'type' => 'string', 'value' => 'silver'],
    ];

    /**
     * @return iterable<string, array{bool, string, array<string, mixed>, list<mixed>}>
     *   singleResult, engine path, engine answer, expected result
     */
    public static function evaluations(): iterable
    {
        yield 'several results' => [
            false,
            '/dmn-api/dmn-rule/execute',
            ['resultVariables' => [self::ROW_GOLD, self::ROW_SILVER]],
            [self::ROW_GOLD, self::ROW_SILVER],
        ];
        yield 'single result' => [
            true,
            '/dmn-api/dmn-rule/execute/single-result',
            ['resultVariables' => self::ROW_GOLD],
            [self::ROW_GOLD],
        ];
        yield 'no rule matched' => [
            false,
            '/dmn-api/dmn-rule/execute',
            ['resultVariables' => []],
            [],
        ];
        yield 'no rule matched, single result' => [
            true,
            '/dmn-api/dmn-rule/execute/single-result',
            ['resultVariables' => []],
            [],
        ];
    }

    /**
     * @param array<string, mixed> $answer
     * @param list<mixed> $expected
     */
    #[DataProvider('evaluations')]
    public function testHttpResultIsTheListOfEngineRows(bool $singleResult, string $path, array $answer, array $expected): void
    {
        $this->engine()->withResponse($path, $answer, 'POST');

        $response = $this->requestAs('POST', self::FLOWABLE . '/decisions/execute', [P::roleFor(P::DECISION_EVALUATE)], [
            'decisionKey' => 'rates',
            'inputVariables' => ['amount' => 100],
            'singleResult' => $singleResult,
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $body = self::json($response);
        self::assertSame('rates', $body['id'] ?? null);
        self::assertSame('rates', $body['key'] ?? null);
        self::assertSame($expected, $body['result'] ?? null);
        $this->assertEngineCall($path);
    }

    /**
     * @param array<string, mixed> $answer
     * @param list<mixed> $expected
     */
    #[DataProvider('evaluations')]
    public function testToolResultIsTheListOfEngineRows(bool $singleResult, string $path, array $answer, array $expected): void
    {
        $this->engine()->withResponse($path, $answer, 'POST');

        $result = $this->mcpCallTool('flowable_dmn_evaluate', [
            'decisionKey' => 'rates',
            'inputVariables' => ['amount' => 100],
            'singleResult' => $singleResult,
        ], [P::roleFor(P::DECISION_EVALUATE)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertArrayHasKey('result', $result, $encoded);
        self::assertNotTrue($result['result']['isError'] ?? false, $encoded);
        $decision = $result['result']['structuredContent'] ?? null;
        self::assertIsArray($decision, $encoded);
        self::assertSame('rates', $decision['key'] ?? null);
        self::assertSame($expected, $decision['result'] ?? null);
        $this->assertEngineCall($path);
    }

    private function assertEngineCall(string $path): void
    {
        $engine = $this->engine();
        self::assertSame([['method' => 'POST', 'path' => '/flowable-rest' . $path]], $engine->requests);
        self::assertSame(
            ['decisionKey' => 'rates', 'inputVariables' => [['name' => 'amount', 'value' => 100, 'type' => 'integer']]],
            json_decode((string) $engine->bodies[0], true, flags: JSON_THROW_ON_ERROR),
        );
    }
}
