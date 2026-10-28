<?php
// file generated with AI assistance: Claude Code - 2026-10-28 21:15:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\Flowable\Tests\Kernel\TestFlowableEngine;
use Dmstr\Flowable\Tests\Kernel\TestHeaderAuthenticator;
use Dmstr\Flowable\Tests\Kernel\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional HTTP and MCP tests against {@see TestKernel}: every test gets a
 * fresh kernel with an empty in-memory SQLite schema and one active
 * `flowable` ApiConfiguration, so allowed requests reach the state providers
 * and are answered by {@see TestFlowableEngine}.
 *
 * Override {@see self::kernelEnvironment()} to run a test class against
 * another kernel variant, e.g. {@see TestKernel::ENV_NO_VOTER}.
 */
abstract class ApiTestCase extends WebTestCase
{
    public const string FLOWABLE = TestKernel::API_PREFIX . '/flowable';

    protected KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function kernelEnvironment(): string
    {
        return TestKernel::ENV_WITH_VOTER;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient(['environment' => static::kernelEnvironment()]);
        // A reboot would drop the in-memory database between requests
        $this->client->disableReboot();

        $entityManager = $this->entityManager();
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $this->createFlowableConfiguration();
    }

    /**
     * Sends a request as a user with the given roles.
     *
     * @param list<string> $roles
     * @param array<string, string> $headers extra headers, e.g. ['Accept' => 'text/html']
     */
    protected function requestAs(
        string $method,
        string $uri,
        array $roles,
        ?array $json = null,
        array $headers = [],
        string $user = TestHeaderAuthenticator::DEFAULT_USER,
    ): Response {
        return $this->request($method, $uri, $json, [
            TestHeaderAuthenticator::HEADER_ROLES => implode(',', $roles),
            TestHeaderAuthenticator::HEADER_USER => $user,
            ...$headers,
        ]);
    }

    /**
     * Sends a request without authentication.
     *
     * @param array<string, string> $headers
     */
    protected function requestAnonymous(string $method, string $uri, ?array $json = null, array $headers = []): Response
    {
        return $this->request($method, $uri, $json, [
            TestHeaderAuthenticator::HEADER_AUTH => TestHeaderAuthenticator::ANONYMOUS,
            ...$headers,
        ]);
    }

    /**
     * Sends a request with exactly the given headers. Defaults: JSON-LD
     * `Accept`, and a body content type matching the method.
     *
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $uri, ?array $json = null, array $headers = []): Response
    {
        $headers += ['Accept' => 'application/ld+json'];
        if ($json !== null) {
            $headers += ['Content-Type' => strtoupper($method) === 'PATCH'
                ? 'application/merge-patch+json'
                : 'application/ld+json'];
        }

        $server = [];
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_' . $key] = $value;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }

    /**
     * Opens an MCP session as a user with the given roles: sends
     * `initialize` and the `notifications/initialized` notification.
     *
     * @param list<string> $roles
     * @return string the session id to pass to {@see self::mcpRequest()}
     */
    protected function mcpInitialize(array $roles): string
    {
        $response = $this->mcpPost($roles, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ]);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $sessionId = (string) $response->headers->get('Mcp-Session-Id', '');
        self::assertNotSame('', $sessionId, 'initialize returned no Mcp-Session-Id');

        $this->mcpPost($roles, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $sessionId);

        return $sessionId;
    }

    /**
     * Sends one JSON-RPC request to the MCP endpoint and returns the decoded
     * JSON-RPC response (`result` or `error`). Opens a session first unless
     * one is given.
     *
     * @param list<string> $roles
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function mcpRequest(string $method, array $params, array $roles, ?string $sessionId = null): array
    {
        $sessionId ??= $this->mcpInitialize($roles);
        $response = $this->mcpPost($roles, [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => $method,
            'params' => $params === [] ? new \stdClass() : $params,
        ], $sessionId);

        return self::decodeMcp($response);
    }

    /**
     * Names of the tools `tools/list` shows to a user with the given roles.
     *
     * @param list<string> $roles
     * @return list<string> sorted
     */
    protected function mcpToolNames(array $roles): array
    {
        $result = $this->mcpRequest('tools/list', [], $roles);
        self::assertArrayHasKey('result', $result, json_encode($result, JSON_THROW_ON_ERROR));

        $names = array_map(static fn (array $tool): string => $tool['name'], $result['result']['tools']);
        sort($names);

        return $names;
    }

    /**
     * Calls a tool (`tools/call`) as a user with the given roles and returns
     * the JSON-RPC response: `result` (with `isError` set when the tool failed)
     * or `error`.
     *
     * @param list<string> $roles
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    protected function mcpCallTool(string $name, array $arguments, array $roles): array
    {
        return $this->mcpRequest('tools/call', [
            'name' => $name,
            'arguments' => $arguments === [] ? new \stdClass() : $arguments,
        ], $roles);
    }

    /**
     * @param list<string> $roles
     * @param array<string, mixed> $message
     */
    private function mcpPost(array $roles, array $message, ?string $sessionId = null): Response
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
        ];
        if ($sessionId !== null) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        return $this->requestAs('POST', TestKernel::MCP_PATH, $roles, $message, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeMcp(Response $response): array
    {
        $body = (string) $response->getContent();

        // The transport may answer with a server-sent event instead of plain JSON
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/event-stream')) {
            foreach (explode("\n", $body) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $body = trim(substr($line, 5));
                    break;
                }
            }
        }

        return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Stores the active `flowable` ApiConfiguration the locator resolves
     * implicitly (its password is encrypted by the bundle's Doctrine
     * listener and decrypted again by the locator) and detaches it.
     */
    protected function createFlowableConfiguration(): ApiConfiguration
    {
        $configuration = (new ApiConfiguration())
            ->setName('Flowable engine')
            ->setConfigJson([
                'flowable' => true,
                'type' => 'flowable',
                'endpoint_type' => 'rest',
                'base_url' => TestFlowableEngine::BASE_URL,
                'auth_type' => 'basic',
                'username' => 'rest-admin',
                'password' => 'test-password',
            ])
            ->setActive(true);

        $entityManager = $this->entityManager();
        $entityManager->persist($configuration);
        $entityManager->flush();
        $entityManager->clear();

        return $configuration;
    }

    /**
     * The stub that answers for the engine; its `requests` show what reached it.
     */
    protected function engine(): TestFlowableEngine
    {
        return static::getContainer()->get(TestFlowableEngine::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function json(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }
}
