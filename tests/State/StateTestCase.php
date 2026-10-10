<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Security\SecretSchemaResolver;
use Dmstr\ApiConfiguration\Service\ApiExtensionRegistry;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Service\ActingUserResolver;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Dmstr\Flowable\State\AbstractFlowableProcessor;
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

/**
 * Builds state providers/processors against a FlowableClientInterface double,
 * without an engine or a database.
 *
 * The (final) FlowableClientLocator is real: its configuration lookups are
 * stubbed and its per-configuration client cache is pre-seeded with the
 * double, so both explicit (apiConfiguration) and implicit resolution return
 * it. Explicit selectors are recorded in $requestedConfigurations.
 */
abstract class StateTestCase extends TestCase
{
    /** @var list<string> apiConfiguration selectors the locator was asked for */
    protected array $requestedConfigurations = [];

    protected function locator(FlowableClientInterface $client): FlowableClientLocator
    {
        $configuration = (new ApiConfiguration())
            ->setType('flowable')
            ->setConfigJson(['base_url' => 'http://flowable.invalid/flowable-rest', 'auth_type' => 'none']);

        $uuidResolver = $this->createStub(UuidResolver::class);
        $uuidResolver->method('findByPartialUuid')->willReturnCallback(
            function (string $class, string $id) use ($configuration): ApiConfiguration {
                $this->requestedConfigurations[] = $id;

                return $configuration;
            },
        );

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn([$configuration]);
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

        $locator = new FlowableClientLocator(new MockHttpClient(), $entityManager, $uuidResolver, $secrets);
        $cache = new \ReflectionProperty(FlowableClientLocator::class, 'cache');
        $cache->setValue($locator, [(string) $configuration->getId() => $client]);

        return $locator;
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
