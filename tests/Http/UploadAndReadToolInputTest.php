<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestHeaderAuthenticator;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Input rules of the upload tools and the read tools, seen through real
 * `tools/call` requests of a user holding exactly the tool's permission, and
 * through HTTP where the rule is shared or must stay as it was:
 *
 *  - one file name rule for every upload (State\UploadRule);
 *  - 1 MiB of decoded inline content per file, 20 files and 4 MiB in total
 *    for flowable_deploy_bundle, 255 characters for names and deployment
 *    fields;
 *  - flowable_dmn_deploy takes a single .dmn, archives only over HTTP;
 *  - read tools validate their arguments against their published schema,
 *    HTTP reads ignore what they did before.
 *
 * A rejected call never reaches the engine.
 */
final class UploadAndReadToolInputTest extends ApiTestCase
{
    /** 1 MiB, the decoded size limit of one inline file */
    private const int MIB = 1048576;

    private const string DMN = '<definitions xmlns="https://www.omg.org/spec/DMN/20191111/MODEL/"/>';

    private const string BPMN = '<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL"/>';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    // --- file names --------------------------------------------------------------

    /**
     * File names (stem) rejected over MCP; the tool's extension is appended.
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidStems(): iterable
    {
        yield 'double quote' => ['ra"tes'];
        yield 'CR LF' => ["rates\r\nX-Injected: 1"];
        yield 'dot-dot' => ['..rates'];
        yield 'slash' => ['../rates'];
        yield 'backslash' => ['a\\rates'];
        yield 'leading dot' => ['.rates'];
    }

    #[DataProvider('invalidStems')]
    public function testDmnToolRejectsAnInvalidFileName(string $stem): void
    {
        $result = $this->mcpCallTool('flowable_dmn_deploy', ['name' => $stem . '.dmn', 'content' => self::DMN], [P::roleFor(P::DEPLOY)]);

        $this->assertRejected($result, 'must be a plain file name');
    }

    #[DataProvider('invalidStems')]
    public function testBundleToolRejectsAnInvalidFileName(string $stem): void
    {
        $result = $this->mcpCallTool('flowable_deploy_bundle', [
            'deploymentName' => 'order',
            'files' => [['name' => $stem . '.bpmn', 'content' => self::BPMN]],
        ], [P::roleFor(P::DEPLOY)]);

        $this->assertRejected($result, 'files[0].name must be a plain file name');
    }

    public function testValidFileNamesStillDeploy(): void
    {
        $dmn = $this->mcpCallTool('flowable_dmn_deploy', ['name' => 'rates-2.dmn', 'content' => self::DMN], [P::roleFor(P::DEPLOY)]);
        $this->assertSucceeded($dmn);

        $bundle = $this->mcpCallTool('flowable_deploy_bundle', [
            'deploymentName' => 'order',
            'files' => [['name' => 'order.bpmn20.xml', 'content' => self::BPMN]],
        ], [P::roleFor(P::DEPLOY)]);
        $this->assertSucceeded($bundle);
    }

    /**
     * Over HTTP the client file name loses its path in UploadedFile, so only
     * quotes and line breaks are left to test there.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function invalidHttpUploadNames(): iterable
    {
        foreach (['/deployments/upload' => 'bpmn', '/dmn_deployments/upload' => 'dmn'] as $uri => $extension) {
            yield $uri . ' double quote' => [$uri, 'ra"tes.' . $extension];
            yield $uri . ' CR LF' => [$uri, "rates\r\n." . $extension];
        }
    }

    #[DataProvider('invalidHttpUploadNames')]
    public function testHttpUploadRejectsQuotesAndLineBreaks(string $uri, string $name): void
    {
        $response = $this->upload($uri, $name);

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('must be a plain file name', (string) $response->getContent());
        self::assertSame([], $this->engine()->requests, 'a rejected upload reached the engine');
    }

    // --- .dmn only over MCP --------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function dmnArchives(): iterable
    {
        yield '.bar' => ['rates.bar'];
        yield '.zip' => ['rates.zip'];
        yield '.DMN.zip' => ['rates.DMN.zip'];
    }

    #[DataProvider('dmnArchives')]
    public function testDmnToolRejectsArchives(string $name): void
    {
        $result = $this->mcpCallTool('flowable_dmn_deploy', [
            'name' => $name,
            'content' => base64_encode("PK\x03\x04"),
            'contentEncoding' => 'base64',
        ], [P::roleFor(P::DEPLOY)]);

        $this->assertRejected($result, '/name');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function httpDmnNames(): iterable
    {
        yield '.dmn' => ['rates.dmn'];
        yield '.bar' => ['rates.bar'];
        yield '.zip' => ['rates.zip'];
    }

    #[DataProvider('httpDmnNames')]
    public function testHttpDmnUploadStillTakesArchives(string $name): void
    {
        $response = $this->upload('/dmn_deployments/upload', $name);

        self::assertLessThan(300, $response->getStatusCode(), (string) $response->getContent());
        self::assertCount(1, $this->engine()->requests);
        self::assertStringEndsWith('/dmn-repository/deployments', $this->engine()->requests[0]['path']);
    }

    // --- size limits ---------------------------------------------------------------

    public function testDmnToolTakesExactly1Mib(): void
    {
        $content = str_repeat('a', self::MIB);

        $this->assertSucceeded($this->mcpCallTool('flowable_dmn_deploy', ['name' => 'rates.dmn', 'content' => $content], [P::roleFor(P::DEPLOY)]));
        $this->assertSucceeded($this->mcpCallTool('flowable_dmn_deploy', [
            'name' => 'rates.dmn',
            'content' => base64_encode($content),
            'contentEncoding' => 'base64',
        ], [P::roleFor(P::DEPLOY)]));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function oversizedContent(): iterable
    {
        $content = str_repeat('a', self::MIB + 1);
        yield 'text' => [['content' => $content]];
        yield 'base64' => [['content' => base64_encode($content), 'contentEncoding' => 'base64']];
        yield 'multibyte text' => [['content' => str_repeat('ä', intdiv(self::MIB, 2) + 1)]];
    }

    /**
     * @param array<string, string> $content
     */
    #[DataProvider('oversizedContent')]
    public function testDmnToolRejectsMoreThan1Mib(array $content): void
    {
        $result = $this->mcpCallTool('flowable_dmn_deploy', ['name' => 'rates.dmn'] + $content, [P::roleFor(P::DEPLOY)]);

        $this->assertRejected($result, 'content\\" exceeds 1048576 bytes');
    }

    /**
     * @param array<string, string> $content
     */
    #[DataProvider('oversizedContent')]
    public function testBundleToolRejectsAFileAbove1Mib(array $content): void
    {
        $result = $this->mcpCallTool('flowable_deploy_bundle', [
            'deploymentName' => 'order',
            'files' => [['name' => 'order.bpmn'] + $content],
        ], [P::roleFor(P::DEPLOY)]);

        $this->assertRejected($result, 'files[0].content exceeds 1048576 bytes');
    }

    public function testBundleToolRejects21Files(): void
    {
        $files = array_map(static fn (int $i): array => ['name' => "f$i.json", 'content' => '{}'], range(1, 21));

        $result = $this->mcpCallTool('flowable_deploy_bundle', ['deploymentName' => 'order', 'files' => $files], [P::roleFor(P::DEPLOY)]);

        $this->assertRejected($result, '/files');
    }

    public function testBundleToolTakesSeveralFilesOf1Mib(): void
    {
        $files = array_map(
            static fn (int $i): array => ['name' => "f$i.json", 'content' => str_repeat('a', self::MIB)],
            range(1, 3),
        );

        $this->assertSucceeded($this->mcpCallTool('flowable_deploy_bundle', ['deploymentName' => 'order', 'files' => $files], [P::roleFor(P::DEPLOY)]));
    }

    public function testBundleToolRejectsMoreThan4MibInTotal(): void
    {
        $files = array_map(
            static fn (int $i): array => ['name' => "f$i.json", 'content' => str_repeat('a', self::MIB)],
            range(1, 4),
        );
        $files[] = ['name' => 'f5.json', 'content' => 'a'];

        $result = $this->mcpCallTool('flowable_deploy_bundle', ['deploymentName' => 'order', 'files' => $files], [P::roleFor(P::DEPLOY)]);

        // With its default body limit (4194304 bytes) the MCP SDK's HTTP
        // transport already refuses such a request; the bundle's own total of
        // 4194304 decoded bytes (UploadProcessorInputTest) holds behind a
        // transport that allows more.
        $this->assertRejected($result, '4194304 bytes');
    }

    /**
     * Tool, field, other required arguments.
     *
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function limitedFields(): iterable
    {
        $dmn = ['name' => 'rates.dmn', 'content' => self::DMN];
        $bundle = ['deploymentName' => 'order', 'files' => [['name' => 'order.bpmn', 'content' => self::BPMN]]];
        foreach (['deploymentName', 'category', 'tenantId'] as $field) {
            yield 'flowable_dmn_deploy ' . $field => ['flowable_dmn_deploy', $field, $dmn];
        }
        foreach (['deploymentName', 'deploymentSource', 'category', 'tenantId'] as $field) {
            yield 'flowable_deploy_bundle ' . $field => ['flowable_deploy_bundle', $field, $bundle];
        }
        yield 'flowable_dmn_deploy name' => ['flowable_dmn_deploy', 'name', $dmn];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('limitedFields')]
    public function testFieldOf256CharactersIsRejected(string $tool, string $field, array $arguments): void
    {
        $value = $field === 'name' ? str_repeat('a', 252) . '.dmn' : str_repeat('a', 256);

        $result = $this->mcpCallTool($tool, [$field => $value] + $arguments, [P::roleFor(P::DEPLOY)]);

        $this->assertRejected($result, '/' . $field);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('limitedFields')]
    public function testFieldOf255CharactersPasses(string $tool, string $field, array $arguments): void
    {
        $value = $field === 'name' ? str_repeat('a', 251) . '.dmn' : str_repeat('a', 255);

        $this->assertSucceeded($this->mcpCallTool($tool, [$field => $value] + $arguments, [P::roleFor(P::DEPLOY)]));
    }

    public function testBundleFileNameOf256CharactersIsRejected(): void
    {
        $result = $this->mcpCallTool('flowable_deploy_bundle', [
            'deploymentName' => 'order',
            'files' => [['name' => str_repeat('a', 251) . '.bpmn', 'content' => self::BPMN]],
        ], [P::roleFor(P::DEPLOY)]);

        $this->assertRejected($result, '/files/0/name');
    }

    // --- read tools ----------------------------------------------------------------

    /**
     * Read tool, its required arguments, an invalid argument set and the
     * schema path the error names.
     *
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function invalidReadArguments(): iterable
    {
        foreach (['flowable_list_process_definitions', 'flowable_list_tasks', 'flowable_system_list_deadletter_jobs'] as $tool) {
            yield $tool . ' page as string' => [$tool, ['page' => '2'], '/page'];
            yield $tool . ' itemsPerPage 201' => [$tool, ['itemsPerPage' => 201], '/itemsPerPage'];
            yield $tool . ' unknown property' => [$tool, ['unknown' => 'x'], 'unknown'];
        }
        foreach (['flowable_get_task_form', 'flowable_get_process_status', 'flowable_history_get'] as $tool) {
            yield $tool . ' unknown property' => [$tool, ['id' => 'x1', 'unknown' => 'x'], 'unknown'];
            yield $tool . ' page' => [$tool, ['id' => 'x1', 'page' => 2], 'page'];
            yield $tool . ' id as number' => [$tool, ['id' => 5], '/id'];
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('invalidReadArguments')]
    public function testReadToolRejectsArgumentsOutsideItsSchema(string $tool, array $arguments, string $path): void
    {
        $result = $this->mcpCallTool($tool, $arguments, [P::roleFor(P::MCP_READ)]);

        $this->assertRejected($result, 'Input validation failed');
        self::assertStringContainsString($path, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function validReadArguments(): iterable
    {
        yield 'flowable_list_process_definitions' => ['flowable_list_process_definitions', ['page' => 2, 'itemsPerPage' => 200, 'latest' => true]];
        yield 'flowable_list_tasks' => ['flowable_list_tasks', ['page' => 1, 'itemsPerPage' => 1, 'order' => 'asc', 'assignee' => 'alice']];
        yield 'flowable_get_task_form' => ['flowable_get_task_form', ['id' => 't1']];
        yield 'flowable_get_process_status' => ['flowable_get_process_status', ['id' => 'p1']];
        yield 'flowable_history_get' => ['flowable_history_get', ['id' => 'p1']];
        yield 'flowable_system_list_deadletter_jobs' => ['flowable_system_list_deadletter_jobs', ['itemsPerPage' => 5, 'tenantId' => 'acme']];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('validReadArguments')]
    public function testReadToolAcceptsArgumentsOfItsSchema(string $tool, array $arguments): void
    {
        $this->assertSucceeded($this->mcpCallTool($tool, $arguments, [P::roleFor(P::MCP_READ)]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function httpReads(): iterable
    {
        yield 'process definitions' => ['/process_definitions'];
        yield 'tasks' => ['/tasks'];
        yield 'jobs' => ['/jobs?kind=deadletter'];
    }

    #[DataProvider('httpReads')]
    public function testHttpReadsIgnoreTheToolSchema(string $uri): void
    {
        $separator = str_contains($uri, '?') ? '&' : '?';
        $response = $this->requestAs('GET', self::FLOWABLE . $uri . $separator . 'page=2&itemsPerPage=201&unknown=x', [P::roleFor(P::READ)]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertCount(1, $this->engine()->requests);
    }

    // --- helpers -------------------------------------------------------------------

    private function upload(string $uri, string $name): Response
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        $this->tempFiles[] = $path;
        file_put_contents($path, self::DMN);

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
     * A failed tool call whose error contains $needle, and no engine request.
     *
     * @param array<string, mixed> $result
     */
    private function assertRejected(array $result, string $needle): void
    {
        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertTrue(isset($result['error']) || ($result['result']['isError'] ?? false) === true, $encoded);
        self::assertStringContainsString($needle, $encoded);
        self::assertSame([], $this->engine()->requests, 'a rejected call reached the engine');
    }

    /**
     * @param array<string, mixed> $result
     */
    private function assertSucceeded(array $result): void
    {
        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertArrayHasKey('result', $result, $encoded);
        self::assertNotTrue($result['result']['isError'] ?? false, $encoded);
        self::assertNotSame([], $this->engine()->requests);
    }
}
