<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:48:28 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestHeaderAuthenticator;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * The full-suffix rule of the uploads (State\UploadRule::rejectedSuffix())
 * and the file name rule of the CLI upload commands, over the real
 * transports:
 *
 *  - flowable_deploy_bundle refuses ".dmn", ".dmn.xml", ".cmmn" and
 *    ".cmmn.xml" in any case, and still takes ".bpmn20.xml", ".xml",
 *    ".bpmn", ".form", ".json" (the bundle has no HTTP operation, it is an
 *    MCP tool only);
 *  - the HTTP process upload refuses ".cmmn.xml" like ".cmmn" and still
 *    takes ".dmn.xml" (".dmn" is allowed there);
 *  - the three CLI upload commands apply the same file name rule and the
 *    same extensions as their HTTP upload, with the same messages.
 *
 * A rejected upload reaches no engine and writes no audit entry.
 */
final class UploadSuffixRuleTest extends ApiTestCase
{
    private const string USER = 'alice';

    private const string BPMN = '<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL"/>';

    private const string NAME_RULE = 'The file name must be a plain file name: at most 255 characters, no path, no "..", not starting with ".", no quotes or control characters.';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
            @rmdir(\dirname($file));
        }
        parent::tearDown();
    }

    // --- flowable_deploy_bundle ------------------------------------------------------

    /**
     * @return iterable<string, array{string, string}> file name, the suffix the error names
     */
    public static function bundleRejectedNames(): iterable
    {
        yield 'a.dmn.xml' => ['a.dmn.xml', '.dmn.xml'];
        yield 'a.cmmn.xml' => ['a.cmmn.xml', '.cmmn.xml'];
        yield 'a.DMN' => ['a.DMN', '.dmn'];
        yield 'a.cmmn' => ['a.cmmn', '.cmmn'];
        yield 'A.Dmn.Xml' => ['A.Dmn.Xml', '.dmn.xml'];
    }

    #[DataProvider('bundleRejectedNames')]
    public function testBundleToolRejectsDmnAndCmmnByTheirFullSuffix(string $name, string $suffix): void
    {
        $result = $this->mcpCallTool('flowable_deploy_bundle', [
            'deploymentName' => 'order',
            'files' => [['name' => 'order.bpmn20.xml', 'content' => self::BPMN], ['name' => $name, 'content' => '<definitions/>']],
        ], [P::roleFor(P::DEPLOY)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertTrue(isset($result['error']) || ($result['result']['isError'] ?? false) === true, $encoded);
        self::assertStringContainsString(
            'files[1]: unsupported extension \\"' . $suffix . '\\". Allowed: bpmn, xml, form, json (deploy .dmn with flowable_dmn_deploy).',
            $encoded,
        );
        $this->assertNothingSent();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bundleAcceptedNames(): iterable
    {
        foreach (['a.bpmn20.xml', 'a.xml', 'a.bpmn', 'a.form', 'a.json'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('bundleAcceptedNames')]
    public function testBundleToolDeploysTheOtherSuffixes(string $name): void
    {
        $result = $this->mcpCallTool('flowable_deploy_bundle', [
            'deploymentName' => 'order',
            'files' => [['name' => $name, 'content' => self::BPMN]],
        ], [P::roleFor(P::DEPLOY)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertNotTrue(isset($result['error']) || ($result['result']['isError'] ?? false) === true, $encoded);
        self::assertCount(1, $this->engine()->requests);
        self::assertStringContainsString($name, (string) $this->engine()->bodies[0], 'the file is packed into the archive');
        self::assertNotSame([], $this->auditRecords());
    }

    // --- HTTP single upload ------------------------------------------------------------

    /**
     * @return iterable<string, array{string, string, string}> uri, file name, the suffix the error names
     */
    public static function httpRejectedNames(): iterable
    {
        yield 'process a.cmmn.xml' => ['/deployments/upload', 'a.cmmn.xml', '.cmmn.xml'];
        yield 'process A.Cmmn.Xml' => ['/deployments/upload', 'A.Cmmn.Xml', '.cmmn.xml'];
        yield 'process a.cmmn' => ['/deployments/upload', 'a.cmmn', '.cmmn'];
        yield 'dmn a.dmn.xml' => ['/dmn_deployments/upload', 'a.dmn.xml', '.xml'];
        yield 'event a.event.xml' => ['/event_deployments/upload', 'a.event.xml', '.xml'];
    }

    #[DataProvider('httpRejectedNames')]
    public function testHttpUploadRejectsTheFullSuffix(string $uri, string $name, string $suffix): void
    {
        $response = $this->upload($uri, $name);

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringStartsWith('Unsupported resource extension "' . $suffix . '". Allowed: ', (string) (self::json($response)['detail'] ?? ''));
        $this->assertNothingSent();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function httpProcessAcceptedNames(): iterable
    {
        foreach (['a.bpmn20.xml', 'a.xml', 'a.dmn.xml', 'A.DMN.XML', 'a.dmn', 'a.bpmn'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('httpProcessAcceptedNames')]
    public function testHttpProcessUploadTakesTheOtherSuffixes(string $name): void
    {
        $response = $this->upload('/deployments/upload', $name);

        self::assertLessThan(300, $response->getStatusCode(), (string) $response->getContent());
        self::assertCount(1, $this->engine()->requests);
    }

    // --- CLI upload commands -------------------------------------------------------------

    /**
     * Command, an accepted extension for the invalid names.
     *
     * @return array<string, string>
     */
    private static function commands(): array
    {
        return [
            'flowable:deployments:upload' => 'bpmn',
            'flowable:dmn:deployments:upload' => 'dmn',
            'flowable:events:deployments:upload' => 'event',
        ];
    }

    /**
     * File names that exist on disk but break the file name rule. A name
     * above 255 characters is not in the list: the file system (NAME_MAX,
     * 255 bytes) cannot hold one, UploadRuleTest covers the limit.
     *
     * @return iterable<string, array{string, string}> command, file name
     */
    public static function cliInvalidNames(): iterable
    {
        foreach (self::commands() as $command => $extension) {
            yield $command . ' double quote' => [$command, 'ra"tes.' . $extension];
            yield $command . ' single quote' => [$command, "ra'tes." . $extension];
            yield $command . ' leading dot' => [$command, '.rates.' . $extension];
            yield $command . ' line feed' => [$command, "rates\n." . $extension];
        }
    }

    #[DataProvider('cliInvalidNames')]
    public function testUploadCommandRejectsAnInvalidFileName(string $command, string $name): void
    {
        $tester = $this->runCommand($command, $this->tempFile($name));

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(self::NAME_RULE, self::display($tester));
        $this->assertNothingSent();
    }

    /**
     * @return iterable<string, array{string, string, string}> command, file name, the error
     */
    public static function cliRejectedSuffixes(): iterable
    {
        $process = 'Allowed: bpmn, xml, dmn, form, json, bar, zip.';
        yield 'process .txt' => ['flowable:deployments:upload', 'a.txt', 'Unsupported resource extension ".txt". ' . $process];
        yield 'process .cmmn' => ['flowable:deployments:upload', 'a.cmmn', 'Unsupported resource extension ".cmmn". ' . $process];
        yield 'process .cmmn.xml' => ['flowable:deployments:upload', 'a.cmmn.xml', 'Unsupported resource extension ".cmmn.xml". ' . $process];
        yield 'process A.Cmmn.Xml' => ['flowable:deployments:upload', 'A.Cmmn.Xml', 'Unsupported resource extension ".cmmn.xml". ' . $process];
        $dmn = 'Allowed: dmn, bar, zip.';
        yield 'dmn .dmn.xml' => ['flowable:dmn:deployments:upload', 'a.dmn.xml', 'Unsupported resource extension ".xml". ' . $dmn];
        yield 'dmn .bpmn' => ['flowable:dmn:deployments:upload', 'a.bpmn', 'Unsupported resource extension ".bpmn". ' . $dmn];
        $event = 'Allowed: event, channel (the event registry accepts no .bar/.zip bundles).';
        yield 'event .bar' => ['flowable:events:deployments:upload', 'a.bar', 'Unsupported resource extension ".bar". ' . $event];
        yield 'event .event.xml' => ['flowable:events:deployments:upload', 'a.event.xml', 'Unsupported resource extension ".xml". ' . $event];
    }

    #[DataProvider('cliRejectedSuffixes')]
    public function testUploadCommandRejectsTheSuffixesOfItsHttpUpload(string $command, string $name, string $error): void
    {
        $tester = $this->runCommand($command, $this->tempFile($name));

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString($error, self::display($tester));
        $this->assertNothingSent();
    }

    /**
     * @return iterable<string, array{string, string, string}> command, file name, engine path suffix
     */
    public static function cliAcceptedNames(): iterable
    {
        foreach (['a.bpmn20.xml', 'a.xml', 'a.dmn.xml', 'a.bpmn', 'a.form', 'a.json', 'a.bar'] as $name) {
            yield 'process ' . $name => ['flowable:deployments:upload', $name, '/repository/deployments'];
        }
        foreach (['a.dmn', 'A.DMN', 'a.zip'] as $name) {
            yield 'dmn ' . $name => ['flowable:dmn:deployments:upload', $name, '/dmn-repository/deployments'];
        }
        foreach (['a.event', 'a.channel'] as $name) {
            yield 'event ' . $name => ['flowable:events:deployments:upload', $name, '/event-registry-repository/deployments'];
        }
    }

    #[DataProvider('cliAcceptedNames')]
    public function testUploadCommandDeploysAValidFile(string $command, string $name, string $path): void
    {
        $tester = $this->runCommand($command, $this->tempFile($name));

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertCount(1, $this->engine()->requests);
        self::assertStringEndsWith($path, $this->engine()->requests[0]['path']);
        self::assertStringContainsString('filename="' . $name . '"', (string) $this->engine()->bodies[0]);
        self::assertNotSame([], $this->auditRecords());
    }

    // --- helpers -------------------------------------------------------------------

    private function assertNothingSent(): void
    {
        self::assertSame([], $this->engine()->requests, 'a rejected upload reached the engine');
        self::assertSame([], $this->auditRecords(), 'a rejected upload was audited');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditRecords(): array
    {
        return array_values(array_filter(
            $this->flowableLog()->records(),
            static fn (array $record): bool => isset($record['context']['state'], $record['context']['via']),
        ));
    }

    /** A file named $name with BPMN content, alone in a new directory. */
    private function tempFile(string $name): string
    {
        $dir = sys_get_temp_dir() . '/suffix-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir . '/' . $name;
        file_put_contents($path, self::BPMN);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function upload(string $uri, string $name): Response
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        $this->tempFiles[] = $path;
        file_put_contents($path, self::BPMN);

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

    private function runCommand(string $name, string $file): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel))->find($name));
        $tester->execute(['--file' => $file, '--acting-user' => self::USER]);

        return $tester;
    }

    /** The command output with SymfonyStyle's line wrapping undone. */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
