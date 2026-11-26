<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:48:37 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Client\FlowableClient;
use Dmstr\Flowable\Exception\FlowableApiException;
use Dmstr\Flowable\Tests\Kernel\TestFlowableEngine;
use Dmstr\Flowable\Tests\Kernel\TestHeaderAuthenticator;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit entries of writes over the real transports (HTTP, MCP tools/call,
 * CLI): an attempt entry before the engine call, then a success or a failure
 * entry, each with the origin under "via", the MCP tool name under "tool"
 * (MCP only) and the actor; a failure of the engine carries the correlation
 * id of the flowable.upstream_error entry. A request rejected by our own
 * code (input, permission) writes no audit entry. Deployments carry sha256
 * hashes of what was uploaded and of the archive sent to the engine.
 */
final class AuditTrailTest extends ApiTestCase
{
    private const string ADMIN = 'ROLE_FLOWABLE_ADMIN';

    private const string USER = 'alice';

    private const string BPMN = '<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL" id="audit"/>';

    private const string DMN = '<definitions xmlns="https://www.omg.org/spec/DMN/20191111/MODEL/" id="audit"/>';

    private const string ID_PATTERN = '/correlation id: ([0-9a-f]{16})\)/';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
            if (str_starts_with(basename(\dirname($file)), 'audit-')) {
                @rmdir(\dirname($file));
            }
        }
        parent::tearDown();
    }

    // --- HTTP ----------------------------------------------------------------------

    /**
     * @return iterable<string, array{string, string, ?array<string, mixed>, string, array<string, mixed>, array<string, mixed>}>
     *   method, uri, body, action, attempt context, keys the success entry adds
     */
    public static function httpWrites(): iterable
    {
        $f = self::FLOWABLE;
        yield 'start process' => ['POST', $f . '/process_definitions/def-1/start', [], 'process_definition.start', ['definition' => 'def-1'], ['instance' => 'stub']];
        yield 'complete task' => ['POST', $f . '/tasks/t1/complete', [], 'task.complete', ['task' => 't1'], []];
        yield 'delete deployment' => ['DELETE', $f . '/deployments/d1', null, 'deployment.delete', ['deployment' => 'd1', 'cascade' => false], []];
        yield 'complete worker job' => ['POST', $f . '/external_worker_jobs/j1/complete', ['workerId' => 'w1'], 'external_worker.complete', ['job' => 'j1', 'worker' => 'w1'], []];
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $attempt
     * @param array<string, mixed> $success
     */
    #[DataProvider('httpWrites')]
    public function testHttpWriteRecordsAttemptAndSuccess(string $method, string $uri, ?array $body, string $action, array $attempt, array $success): void
    {
        $response = $this->requestAs($method, $uri, [self::ADMIN], $body, user: self::USER);

        self::assertLessThan(300, $response->getStatusCode(), (string) $response->getContent());
        $head = ['via' => 'http', 'actor' => self::USER];
        $this->assertTrail($action, [
            ['attempt', ['state' => 'attempt', ...$head, ...$attempt]],
            ['success', ['state' => 'success', ...$head, ...$attempt, ...$success]],
        ]);
    }

    /**
     * @return iterable<string, array{string, string, ?array<string, mixed>, string, string, int}>
     */
    public static function httpFailures(): iterable
    {
        foreach (self::httpWrites() as $name => [$method, $uri, $body, $action]) {
            yield $name . ', engine 500' => [$method, $uri, $body, $action, '500', 502];
            yield $name . ', timeout' => [$method, $uri, $body, $action, 'timeout', 504];
        }
    }

    /**
     * @param array<string, mixed>|null $body
     */
    #[DataProvider('httpFailures')]
    public function testHttpWriteRecordsAttemptAndFailureWithTheCorrelationId(string $method, string $uri, ?array $body, string $action, string $failure, int $status): void
    {
        $this->makeEngineFail($failure);

        $response = $this->requestAs($method, $uri, [self::ADMIN], $body, user: self::USER);

        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        $id = $this->upstreamCorrelationId();
        self::assertSame($id, $response->headers->get(FlowableApiException::CORRELATION_ID_HEADER));
        $records = $this->assertStates($action, ['attempt', 'failure']);
        $entry = $records[1]['context'];
        self::assertSame('warning', $records[1]['level']);
        self::assertSame('http', $entry['via']);
        self::assertArrayNotHasKey('tool', $entry);
        self::assertSame(self::USER, $entry['actor']);
        self::assertSame($status, $entry['status']);
        self::assertSame($id, $entry['correlationId']);
        self::assertSame(FlowableApiException::class, $entry['exception']);
    }

    public function testHttpUploadCarriesTheSha256OfTheUploadedFile(): void
    {
        $response = $this->upload('/deployments/upload', 'order.bpmn', self::BPMN);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $hash = hash('sha256', self::BPMN);
        $head = ['via' => 'http', 'actor' => TestHeaderAuthenticator::DEFAULT_USER];
        $this->assertTrail('deployment.upload', [
            ['attempt', ['state' => 'attempt', ...$head, 'file' => 'order.bpmn', 'sha256' => $hash]],
            ['success', ['state' => 'success', ...$head, 'file' => 'order.bpmn', 'sha256' => $hash, 'deployment' => 'stub']],
        ]);
        self::assertSame(self::BPMN, self::multipartFile((string) $this->engine()->bodies[0]), 'the engine received other bytes');
    }

    // --- MCP -----------------------------------------------------------------------

    /**
     * @return iterable<string, array{string, array<string, mixed>, string, array<string, mixed>, array<string, mixed>}>
     *   tool, arguments, action, attempt context, keys the success entry adds
     */
    public static function mcpWrites(): iterable
    {
        yield 'start process' => ['flowable_start_process', ['processDefinitionKey' => 'k'], 'process_instance.create', ['definition' => null, 'definitionKey' => 'k'], ['instance' => 'stub']];
        yield 'complete task' => ['flowable_complete_task', ['id' => 't1'], 'task.complete', ['task' => 't1'], []];
        yield 'deploy dmn' => ['flowable_dmn_deploy', ['name' => 'rates.dmn', 'content' => self::DMN], 'dmn.deployment.upload', ['file' => 'rates.dmn', 'sha256' => hash('sha256', self::DMN)], ['deployment' => 'stub']];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $attempt
     * @param array<string, mixed> $success
     */
    #[DataProvider('mcpWrites')]
    public function testMcpToolCallRecordsAttemptAndSuccessWithTheToolName(string $tool, array $arguments, string $action, array $attempt, array $success): void
    {
        $result = $this->mcpCallTool($tool, $arguments, [self::ADMIN]);

        self::assertFalse(isset($result['error']) || ($result['result']['isError'] ?? false), json_encode($result, JSON_THROW_ON_ERROR));
        $head = ['via' => 'mcp', 'tool' => $tool, 'actor' => TestHeaderAuthenticator::DEFAULT_USER];
        $records = $this->assertTrail($action, [
            ['attempt', ['state' => 'attempt', ...$head, ...$attempt]],
            ['success', ['state' => 'success', ...$head, ...$attempt, ...$success]],
        ]);
        foreach ($records as $record) {
            self::assertNotSame('http', $record['context']['via'], 'an MCP call was audited as HTTP');
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function failures(): iterable
    {
        yield 'engine 500' => ['500', 502];
        yield 'timeout' => ['timeout', 504];
    }

    #[DataProvider('failures')]
    public function testMcpToolCallRecordsAttemptAndFailureWithTheCorrelationId(string $failure, int $status): void
    {
        $this->makeEngineFail($failure);

        $result = $this->mcpCallTool('flowable_complete_task', ['id' => 't1'], [self::ADMIN]);

        $text = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $id = $this->upstreamCorrelationId();
        self::assertSame($id, self::correlationIdIn($text), 'the tool result names another id');
        $records = $this->assertStates('task.complete', ['attempt', 'failure']);
        $entry = $records[1]['context'];
        self::assertSame(['state', 'via', 'tool', 'actor', 'task', 'status', 'correlationId', 'exception'], array_keys($entry));
        self::assertSame('mcp', $entry['via']);
        self::assertSame('flowable_complete_task', $entry['tool']);
        self::assertSame($status, $entry['status']);
        self::assertSame($id, $entry['correlationId']);
    }

    public function testMcpBundleCarriesTheSha256OfEveryFileAndOfTheArchiveSent(): void
    {
        $files = [
            ['name' => 'order.bpmn', 'content' => self::BPMN],
            ['name' => 'order.form', 'content' => base64_encode('{"key":"order"}'), 'contentEncoding' => 'base64'],
        ];

        $result = $this->mcpCallTool('flowable_deploy_bundle', ['deploymentName' => 'order', 'files' => $files], [self::ADMIN]);

        self::assertFalse(isset($result['error']) || ($result['result']['isError'] ?? false), json_encode($result, JSON_THROW_ON_ERROR));
        $archive = self::multipartFile((string) $this->engine()->bodies[0]);
        $zip = $this->zipEntries($archive);
        self::assertSame(['order.bpmn' => self::BPMN, 'order.form' => '{"key":"order"}'], $zip);
        $attempt = [
            'files' => ['order.bpmn', 'order.form'],
            'fileSha256' => ['order.bpmn' => hash('sha256', self::BPMN), 'order.form' => hash('sha256', '{"key":"order"}')],
            'archive' => 'order.bar',
            'archiveSha256' => hash('sha256', $archive),
        ];
        $head = ['via' => 'mcp', 'tool' => 'flowable_deploy_bundle', 'actor' => TestHeaderAuthenticator::DEFAULT_USER];
        $this->assertTrail('deployment.bundle', [
            ['attempt', ['state' => 'attempt', ...$head, ...$attempt]],
            ['success', ['state' => 'success', ...$head, ...$attempt, 'deployment' => 'stub']],
        ]);
    }

    // --- rejected before the attempt -------------------------------------------------

    public function testInvalidHttpInputWritesNoAuditEntry(): void
    {
        $response = $this->requestAs('POST', self::FLOWABLE . '/external_worker_jobs/j1/complete', [self::ADMIN], ['variables' => 5]);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $this->assertNoAudit();
    }

    public function testMissingPermissionWritesNoAuditEntry(): void
    {
        $response = $this->requestAs('POST', self::FLOWABLE . '/tasks/t1/complete', ['ROLE_USER'], []);

        self::assertSame(403, $response->getStatusCode(), (string) $response->getContent());
        $this->assertNoAudit();
    }

    public function testRejectedMcpUploadWritesNoAuditEntry(): void
    {
        $result = $this->mcpCallTool('flowable_dmn_deploy', ['name' => '../rates.dmn', 'content' => self::DMN], [P::roleFor(P::DEPLOY)]);

        self::assertTrue(isset($result['error']) || ($result['result']['isError'] ?? false) === true);
        $this->assertNoAudit();
    }

    public function testCommandWithoutActingUserWritesNoAuditEntry(): void
    {
        $tester = $this->runCommand('flowable:tasks:complete', ['id' => 't1', '--input' => '{}']);

        self::assertSame(1, $tester->getStatusCode());
        $this->assertNoAudit();
    }

    // --- CLI -----------------------------------------------------------------------

    public function testCommandRecordsAttemptAndSuccessWithTheActingUser(): void
    {
        $tester = $this->runCommand('flowable:tasks:complete', ['id' => 't1', '--input' => '{}', '--acting-user' => self::USER]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $head = ['via' => 'cli', 'actor' => self::USER];
        $this->assertTrail('task.complete', [
            ['attempt', ['state' => 'attempt', ...$head, 'task' => 't1']],
            ['success', ['state' => 'success', ...$head, 'task' => 't1']],
        ]);
    }

    public function testStartCommandRecordsTheInstance(): void
    {
        $tester = $this->runCommand('flowable:process-instances:create', ['--input' => '{"processDefinitionKey":"k"}', '--acting-user' => self::USER]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $head = ['via' => 'cli', 'actor' => self::USER, 'definition' => null, 'definitionKey' => 'k'];
        $this->assertTrail('process_instance.create', [
            ['attempt', ['state' => 'attempt', ...$head]],
            ['success', ['state' => 'success', ...$head, 'instance' => 'stub']],
        ]);
    }

    public function testUploadCommandCarriesTheSha256OfTheFile(): void
    {
        $path = $this->tempFile('order.bpmn', self::BPMN);

        $tester = $this->runCommand('flowable:deployments:upload', ['--file' => $path, '--acting-user' => self::USER]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $hash = hash('sha256', self::BPMN);
        $head = ['via' => 'cli', 'actor' => self::USER, 'file' => 'order.bpmn', 'sha256' => $hash];
        $this->assertTrail('deployment.upload', [
            ['attempt', ['state' => 'attempt', ...$head]],
            ['success', ['state' => 'success', ...$head, 'deployment' => 'stub']],
        ]);
        self::assertSame(self::BPMN, self::multipartFile((string) $this->engine()->bodies[0]));
    }

    public function testWorkerJobCommandRecordsTheWorkerIdAsActor(): void
    {
        $tester = $this->runCommand('flowable:external-worker:complete', ['id' => 'j1', '--worker-id' => 'w1']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $head = ['via' => 'cli', 'actor' => 'w1', 'job' => 'j1', 'worker' => 'w1'];
        $this->assertTrail('external_worker.complete', [
            ['attempt', ['state' => 'attempt', ...$head]],
            ['success', ['state' => 'success', ...$head]],
        ]);
    }

    /**
     * Every write command, with valid input: command, input (a file option
     * value is a file name created with test content), action, actor.
     *
     * @return iterable<string, array{string, array<string, mixed>, string, string}>
     */
    public static function writeCommands(): iterable
    {
        $user = ['--acting-user' => self::USER];
        yield 'decision execute' => ['flowable:dmn:rule:execute', ['--input' => '{"decisionKey":"k"}', ...$user], 'decision.execute', self::USER];
        yield 'deployment upload' => ['flowable:deployments:upload', ['--file' => 'a.bpmn', ...$user], 'deployment.upload', self::USER];
        yield 'dmn upload' => ['flowable:dmn:deployments:upload', ['--file' => 'a.dmn', ...$user], 'dmn.deployment.upload', self::USER];
        yield 'event upload' => ['flowable:events:deployments:upload', ['--file' => 'a.event', ...$user], 'event_deployment.upload', self::USER];
        yield 'event instance' => ['flowable:events:instances:create', ['--input' => '{"eventDefinitionKey":"e","channelDefinitionKey":"c","eventPayload":{"a":1}}', ...$user], 'event_instance.create', self::USER];
        yield 'execution trigger' => ['flowable:executions:trigger', ['id' => 'x1', '--input' => '{}', ...$user], 'execution.trigger', self::USER];
        yield 'process instance' => ['flowable:process-instances:create', ['--input' => '{"processDefinitionKey":"k"}', ...$user], 'process_instance.create', self::USER];
        yield 'task complete' => ['flowable:tasks:complete', ['id' => 't1', '--input' => '{}', ...$user], 'task.complete', self::USER];
        yield 'worker complete' => ['flowable:external-worker:complete', ['id' => 'j1', '--worker-id' => 'w1'], 'external_worker.complete', 'w1'];
        yield 'worker fail' => ['flowable:external-worker:fail', ['id' => 'j1', '--worker-id' => 'w1'], 'external_worker.fail', 'w1'];
        yield 'worker bpmn error' => ['flowable:external-worker:bpmn-error', ['id' => 'j1', '--worker-id' => 'w1', '--error-code' => 'E1'], 'external_worker.bpmn_error', 'w1'];
        yield 'worker unacquire' => ['flowable:external-worker:unacquire', ['id' => 'j1', '--worker-id' => 'w1'], 'external_worker.unacquire', 'w1'];
        yield 'worker unacquire all' => ['flowable:external-worker:unacquire', ['--all' => true, '--worker-id' => 'w1'], 'external_worker.unacquire_all', 'w1'];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('writeCommands')]
    public function testEveryWriteCommandRecordsAttemptAndSuccessViaCli(string $command, array $input, string $action, string $actor): void
    {
        if (isset($input['--file'])) {
            $input['--file'] = $this->tempFile($input['--file'], self::BPMN);
        }

        $tester = $this->runCommand($command, $input);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        foreach ($this->assertStates($action, ['attempt', 'success']) as $record) {
            self::assertSame(['cli', $actor], [$record['context']['via'], $record['context']['actor']]);
            self::assertArrayNotHasKey('tool', $record['context']);
        }
    }

    #[DataProvider('failures')]
    public function testCommandRecordsAttemptAndFailureWithTheCorrelationId(string $failure, int $status): void
    {
        $this->makeEngineFail($failure);

        $tester = $this->runCommand('flowable:external-worker:unacquire', ['id' => 'j1', '--worker-id' => 'w1']);

        self::assertSame(1, $tester->getStatusCode());
        $id = $this->upstreamCorrelationId();
        self::assertSame($id, self::correlationIdIn((string) preg_replace('/\s+/', ' ', $tester->getDisplay())));
        $records = $this->assertStates('external_worker.unacquire', ['attempt', 'failure']);
        self::assertSame(
            ['state' => 'failure', 'via' => 'cli', 'actor' => 'w1', 'job' => 'j1', 'worker' => 'w1', 'status' => $status, 'correlationId' => $id, 'exception' => FlowableApiException::class],
            $records[1]['context'],
        );
    }

    // --- helpers ---------------------------------------------------------------------

    /**
     * The audit entries of $action in order, asserted to be exactly the given
     * states with exactly the given contexts (keys in order).
     *
     * @param list<array{string, array<string, mixed>}> $expected state, context
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private function assertTrail(string $action, array $expected): array
    {
        $records = $this->assertStates($action, array_column($expected, 0));
        foreach ($expected as $index => [, $context]) {
            self::assertSame($context, $records[$index]['context'], $records[$index]['message']);
            self::assertSame('info', $records[$index]['level']);
        }

        return $records;
    }

    /**
     * @param list<string> $states
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private function assertStates(string $action, array $states): array
    {
        $records = $this->auditRecords();
        $messages = array_map(
            static fn (string $state): string => 'flowable.' . $action . ($state === 'success' ? '' : '.' . $state),
            $states,
        );
        self::assertSame($messages, array_column($records, 'message'), implode(', ', $this->flowableLog()->messages()));
        self::assertSame($states, array_map(static fn (array $record): mixed => $record['context']['state'] ?? null, $records));

        return $records;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private function auditRecords(): array
    {
        return array_values(array_filter(
            $this->flowableLog()->records(),
            static fn (array $record): bool => isset($record['context']['state'], $record['context']['via']),
        ));
    }

    private function assertNoAudit(): void
    {
        self::assertSame([], $this->auditRecords());
        self::assertSame([], $this->engine()->requests, 'a rejected request reached the engine');
    }

    private function upstreamCorrelationId(): string
    {
        $records = $this->flowableLog()->recordsWithMessage(FlowableClient::LOG_UPSTREAM_ERROR);
        self::assertCount(1, $records);
        $id = $records[0]['context']['correlationId'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    private static function correlationIdIn(string $text): ?string
    {
        return preg_match(self::ID_PATTERN, $text, $match) === 1 ? $match[1] : null;
    }

    private function makeEngineFail(string $failure): void
    {
        match ($failure) {
            '500' => $this->engine()->failWith(500, TestFlowableEngine::errorBody('Internal server error')),
            'timeout' => $this->engine()->failWithTimeout(),
        };
    }

    /**
     * The content of the file part of a multipart body as FlowableClient
     * builds it (the file part is the last one).
     */
    private static function multipartFile(string $body): string
    {
        $boundary = substr($body, 2, (int) strpos($body, "\r\n") - 2);
        $parts = explode('--' . $boundary, $body);
        $file = $parts[\count($parts) - 2];
        self::assertStringContainsString('filename="', $file);

        return substr($file, (int) strpos($file, "\r\n\r\n") + 4, -2);
    }

    /**
     * @return array<string, string> entry name => content
     */
    private function zipEntries(string $archive): array
    {
        $path = $this->tempFile('bundle.bar', $archive);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = (string) $zip->getNameIndex($i);
            $entries[$name] = (string) $zip->getFromIndex($i);
        }
        $zip->close();

        return $entries;
    }

    private function tempFile(string $name, string $content): string
    {
        $dir = sys_get_temp_dir() . '/audit-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir . '/' . $name;
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function upload(string $uri, string $name, string $content): Response
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        $this->tempFiles[] = $path;
        file_put_contents($path, $content);

        $this->client->request(
            'POST',
            self::FLOWABLE . $uri,
            files: ['file' => new UploadedFile($path, $name, null, null, true)],
            server: [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_ROLES)) => P::roleFor(P::DEPLOY),
                'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_USER)) => TestHeaderAuthenticator::DEFAULT_USER,
            ],
        );

        return $this->client->getResponse();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(string $name, array $input): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel))->find($name));
        $tester->execute($input);

        return $tester;
    }
}
