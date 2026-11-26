<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use ApiPlatform\Metadata\McpTool;
use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Security\SecretSchemaResolver;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Service\ActingUserResolver;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Dmstr\Flowable\State\AbstractFlowableProcessor;
use Dmstr\Flowable\Tests\Mcp\BundleMcpTools;
use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Uid\Uuid;

/**
 * Builds state providers/processors against a FlowableClientInterface double,
 * without an engine or a database.
 *
 * The (final) FlowableClientLocator is real: its repository lookup is
 * stubbed and its per-configuration client cache is pre-seeded with the
 * double, so both explicit (apiConfiguration {@see self::SELECTOR}) and
 * implicit resolution return it. A test that must prove the explicit
 * selection calls {@see self::expectExplicitSelection()} first.
 */
abstract class StateTestCase extends TestCase
{
    /** Selector (first 8 characters) of the configuration the double answers for */
    protected const string SELECTOR = '0a1b2c3d';

    private const string CONFIGURATION_ID = self::SELECTOR.'-0000-4000-8000-000000000001';

    /** A second active configuration, known only after expectExplicitSelection() */
    private const string DECOY_ID = 'ffffffff-0000-4000-8000-000000000002';

    private bool $explicitSelection = false;

    /**
     * Locators built afterwards also know a second active "flowable"
     * configuration (selector ffffffff) whose client fails the test on any
     * call. Implicit resolution then ends in a 400, so the double is reached
     * only through the explicit selector {@see self::SELECTOR}.
     */
    protected function expectExplicitSelection(): void
    {
        $this->explicitSelection = true;
    }

    protected function locator(FlowableClientInterface $client): FlowableClientLocator
    {
        $configurations = [self::CONFIGURATION_ID => $client];
        if ($this->explicitSelection) {
            $decoy = $this->createMock(FlowableClientInterface::class);
            $decoy->expects($this->never())->method($this->anything());
            $configurations[self::DECOY_ID] = $decoy;
        }

        $active = array_map(self::configuration(...), array_keys($configurations));
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($active);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        // The pre-seeded cache means no client is built, so nothing is
        // decrypted; the cipher fails loudly if that ever changes.
        $secrets = new ConfigSecrets(
            new SecretSchemaResolver(
                new SchemaRegistry($this->createStub(CacheItemPoolInterface::class), new NullLogger()),
                new ApiExtensionRegistry(),
            ),
            static fn () => throw new \LogicException('StateTestCase does not decrypt secrets.'),
        );

        $locator = new FlowableClientLocator(new MockHttpClient(), $entityManager, $secrets);
        $cache = new \ReflectionProperty(FlowableClientLocator::class, 'cache');
        $cache->setValue($locator, $configurations);

        return $locator;
    }

    /** An active "flowable" configuration with the given id (no setter: set by reflection). */
    private static function configuration(string $id): ApiConfiguration
    {
        $configuration = (new ApiConfiguration())
            ->setType('flowable')
            ->setConfigJson(['base_url' => 'http://flowable.invalid/flowable-rest', 'auth_type' => 'none']);
        (new \ReflectionProperty(ApiConfiguration::class, 'id'))->setValue($configuration, Uuid::fromString($id));

        return $configuration;
    }

    protected function requestStack(?Request $request = null): RequestStack
    {
        $stack = new RequestStack();
        if ($request !== null) {
            $stack->push($request);
        }

        return $stack;
    }

    /**
     * @template T of AbstractFlowableProcessor
     * @param class-string<T> $class
     * @return T
     */
    protected function processor(string $class, FlowableClientInterface $client, ?Request $request = null): AbstractFlowableProcessor
    {
        $container = new Container();
        $container->set('security.token_storage', new TokenStorage());

        return new $class(
            $this->locator($client),
            $this->requestStack($request),
            new InputSchemaValidator(),
            new FlowableVariableMapper(),
            new ActingUserResolver(new Security($container)),
            new NullLogger(),
        );
    }

    /**
     * All McpTool operations of the bundle's resources, read from the real
     * attribute metadata of src/ApiResource, by tool name (sorted), see
     * {@see BundleMcpTools::all()}.
     *
     * @return array<string, McpTool>
     */
    protected static function mcpTools(): array
    {
        return BundleMcpTools::all();
    }

    /**
     * One McpTool of the bundle as API Platform's MCP handler receives it, so
     * a provider validates the arguments against the tool's real schema.
     */
    protected static function mcpTool(string $name): McpTool
    {
        $tools = self::mcpTools();
        self::assertArrayHasKey($name, $tools, 'unknown MCP tool');

        return $tools[$name];
    }

    /**
     * Operation context as API Platform's MCP handler builds it: the tool
     * arguments in mcp_data and the URI variables copied out of them.
     *
     * @param array<string,mixed> $arguments
     * @param list<string> $uriVariableNames
     * @return array<string,mixed>
     */
    protected static function mcpContext(array $arguments, array $uriVariableNames = []): array
    {
        return [
            'mcp_data' => $arguments,
            'uri_variables' => array_intersect_key($arguments, array_flip($uriVariableNames)),
        ];
    }
}
// - revised 2026-11-26 (locator without UuidResolver, explicit selection through a decoy configuration)
// - revised 2026-11-26 (real McpTool operations for MCP provider tests)
// - revised 2026-11-26 (tool discovery moved to Tests\Mcp\BundleMcpTools)
