<?php
// file generated with AI assistance: Claude Code - 2026-10-28 21:15:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Dmstr\ApiConfiguration\ApiConfigurationBundle;
use Dmstr\ApiPlatformUtils\ApiPlatformUtilsBundle;
use Dmstr\Flowable\FlowableBundle;
use Dmstr\Flowable\State\FlowProcessDefinitionProvider;
use Dmstr\Flowable\Tests\Fixtures\ForeignMcp\ForeignMcpToolProvider;
use Dmstr\Flowable\Worker\ExternalWorkerHandlerRegistry;
use Dmstr\OpenApiJsonSchema\OpenApiJsonSchemaBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Minimal application kernel for the HTTP and MCP tests of the bundle.
 *
 * - SQLite in memory: every booted kernel starts with an empty database;
 *   the test case creates the schema and the `flowable` ApiConfiguration
 *   ({@see \Dmstr\Flowable\Tests\Http\ApiTestCase}).
 * - The engine is faked at the HTTP layer: `http_client` is a MockHttpClient
 *   answering with {@see TestFlowableEngine}, so the real FlowableClientLocator
 *   and FlowableClient run unchanged.
 * - A stateless firewall over every path, authenticated from request headers
 *   by {@see TestHeaderAuthenticator}.
 * - API Platform routes below {@see self::API_PREFIX}, the MCP endpoint at
 *   {@see self::MCP_PATH}. All three `dmstr_flowable.mcp` switches are on, as
 *   the bundle removes its MCP tools otherwise.
 * - The bundle's `flowable` logger channel is a {@see RecordingLogger}
 *   (public service {@see self::FLOWABLE_LOGGER}); the general `logger`
 *   stays silent.
 * - Two variants, selected by the kernel environment:
 *   {@see self::ENV_WITH_VOTER} registers {@see TestPermissionVoter},
 *   {@see self::ENV_NO_VOTER} registers no custom voter at all.
 * - Further variants through the constructor's $options, on top of either
 *   environment:
 *   - `mcp`: the `dmstr_flowable.mcp` extension config, passed as given
 *     instead of the three switches on; values may be env placeholders such
 *     as `'%env(bool:FLOWABLE_MCP_READ)%'`;
 *   - `parameters`: extra container parameters set by the "application",
 *     e.g. `['dmstr_flowable.mcp.read' => false]`;
 *   - `use_symfony_listeners`: API Platform's switch of that name (not set
 *     otherwise, API Platform's default is false);
 *   - `api_platform_mcp`: false turns API Platform's MCP support off
 *     (`api_platform.mcp.enabled`), so there is no MCP handler and no tool;
 *     symfony/mcp-bundle stays registered;
 *   - `mcp_fixtures`: true replaces the bundle's FlowProcessDefinitionProvider
 *     by {@see NonValidatingProcessDefinitionProvider} (as an application
 *     would, for HTTP and the tool flowable_list_process_definitions alike)
 *     and adds the application tool
 *     {@see \Dmstr\Flowable\Tests\Fixtures\ForeignMcp\ForeignMcpTool};
 *     both providers are public so that tests can read their calls;
 *   - `external_worker_handlers`: classes registered as plain services
 *     (autowired and autoconfigured like an application's, no tag, no
 *     attribute), and the public alias {@see self::WORKER_HANDLER_REGISTRY}
 *     of the handler registry, so a test can read the topics it collected.
 *   A kernel with options gets its own environment name, the given one plus
 *   a hash of the options, so every configuration compiles its own
 *   container in its own cache directory.
 *
 * Cache and logs live under the system temp directory, one directory per
 * environment name, so a test run leaves no files in the repository. A
 * kernel with `$debug = false` gets a directory of its own, which is emptied
 * once per PHP process: without debug, Symfony loads a cached container
 * without checking it for freshness, so a container compiled from older code
 * would survive.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string ENV_WITH_VOTER = 'test';
    public const string ENV_NO_VOTER = 'test_no_voter';

    /** API Platform routes are imported below this prefix, as in an application */
    public const string API_PREFIX = '/api';

    public const string MCP_SERVER = 'flowable';
    public const string MCP_PATH = '/mcp';

    /** Needed by the autowired controllers of dmstr/api-configuration-bundle */
    public const string OAUTH_REDIRECT_URI = 'https://app.example.com/api/oauth/callback';

    /** 32 raw bytes, as CredentialEncryption expects them */
    public const string CREDENTIALS_KEY = '0123456789abcdef0123456789abcdef';

    /** The service id the bundle binds its audit and error logger to */
    public const string FLOWABLE_LOGGER = 'monolog.logger.flowable';

    /** Public alias of the handler registry, set by the `external_worker_handlers` option */
    public const string WORKER_HANDLER_REGISTRY = 'test.flowable.external_worker_handler_registry';

    private const array OPTIONS = ['mcp', 'parameters', 'use_symfony_listeners', 'api_platform_mcp', 'mcp_fixtures', 'external_worker_handlers'];

    /** The environment given to the constructor, without the options hash */
    private readonly string $variantOf;

    /** @var array<string, true> non-debug directories emptied in this process */
    private static array $clearedNonDebugDirs = [];

    /**
     * @param array{mcp?: array<string, mixed>, parameters?: array<string, mixed>, use_symfony_listeners?: bool, api_platform_mcp?: bool, mcp_fixtures?: bool, external_worker_handlers?: list<class-string>} $options
     *        see the class docblock; [] is the plain kernel of the environment
     */
    public function __construct(string $environment, bool $debug, private readonly array $options = [])
    {
        $unknown = array_diff(array_keys($options), self::OPTIONS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown TestKernel option(s) "%s". Allowed: %s.',
                implode('", "', $unknown),
                implode(', ', self::OPTIONS),
            ));
        }

        $this->variantOf = $environment;
        parent::__construct(
            $options === [] ? $environment : $environment . '_' . substr(hash('xxh128', serialize($options)), 0, 12),
            $debug,
        );

        if (!$debug && !isset(self::$clearedNonDebugDirs[$this->tmpDir()])) {
            self::$clearedNonDebugDirs[$this->tmpDir()] = true;
            (new Filesystem())->remove($this->tmpDir());
        }
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        // Required: dmstr/api-configuration-bundle prepends `doctrine_migrations` configuration
        yield new DoctrineMigrationsBundle();
        yield new ApiPlatformBundle();
        yield new ApiPlatformUtilsBundle();
        yield new OpenApiJsonSchemaBundle();
        yield new ApiConfigurationBundle();
        yield new McpBundle();
        yield new FlowableBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->tmpDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->tmpDir() . '/log';
    }

    /**
     * Not the repository's `config/` (the bundle's service definitions):
     * in debug mode FrameworkBundle 7.4+ dumps `reference.php` into an
     * existing config directory. This one does not exist, so nothing is
     * written; the kernel reads no files from it ({@see self::configureContainer()}
     * configures everything in code).
     */
    private function getConfigDir(): string
    {
        return $this->tmpDir() . '/config';
    }

    public function hasPermissionVoter(): bool
    {
        return $this->variantOf !== self::ENV_NO_VOTER;
    }

    private function tmpDir(): string
    {
        return sys_get_temp_dir() . '/dmstr-flowable-bundle-tests/' . $this->environment . ($this->debug ? '' : '_no_debug');
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $projectDir = $this->getProjectDir();
        $apiConfigurationEntities = $this->apiConfigurationDir() . '/src/Entity';

        $parameters = $container->parameters()
            ->set('app.oauth.redirect_uri', self::OAUTH_REDIRECT_URI);
        foreach ($this->options['parameters'] ?? [] as $name => $value) {
            $parameters->set($name, $value);
        }

        $container->extension('framework', [
            'test' => true,
            'secret' => 'test-secret',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'cache' => ['app' => 'cache.adapter.array'],
        ]);

        $container->extension('security', [
            'providers' => [
                'test_users' => ['memory' => ['users' => []]],
            ],
            'firewalls' => [
                'main' => [
                    'pattern' => '^/',
                    'stateless' => true,
                    'provider' => 'test_users',
                    'custom_authenticators' => [TestHeaderAuthenticator::class],
                ],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'url' => 'sqlite:///:memory:',
            ],
            'orm' => [
                'enable_native_lazy_objects' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'mappings' => [
                    'ApiConfiguration' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $apiConfigurationEntities,
                        'prefix' => 'Dmstr\\ApiConfiguration\\Entity',
                    ],
                ],
            ],
        ]);

        // The bundle's ApiResource classes under src/ApiResource are found by
        // API Platform on its own (every bundle's src/ApiResource is scanned).
        $apiPlatform = [
            'title' => 'flowable-bundle tests',
            'formats' => [
                'jsonld' => ['application/ld+json'],
                'json' => ['application/json'],
            ],
            'patch_formats' => [
                'json' => ['application/merge-patch+json'],
            ],
            'enable_swagger_ui' => false,
            'enable_re_doc' => false,
            'mcp' => ['enabled' => $this->options['api_platform_mcp'] ?? true],
        ];
        if ($this->options['mcp_fixtures'] ?? false) {
            $apiPlatform['mapping'] = ['paths' => [$projectDir . '/tests/Fixtures/ForeignMcp']];
        }
        if (\array_key_exists('use_symfony_listeners', $this->options)) {
            $apiPlatform['use_symfony_listeners'] = $this->options['use_symfony_listeners'];
        }
        $container->extension('api_platform', $apiPlatform);

        $container->extension('dmstr_api_platform_utils', [
            'credential_encryption' => ['key' => self::CREDENTIALS_KEY],
            // The default, set explicitly because the tests rely on it
            'hydra_operations' => ['filter_operations_by_security' => true],
        ]);

        // All switches on unless a variant says otherwise: the bundle removes
        // its MCP tools when a switch is off
        $container->extension('dmstr_flowable', [
            'mcp' => $this->options['mcp'] ?? ['read' => true, 'write' => true, 'deploy' => true],
        ]);

        $container->extension('mcp', [
            'servers' => [
                self::MCP_SERVER => [
                    'registry' => ['tools' => ['*']],
                    'http' => ['path' => self::MCP_PATH],
                ],
            ],
        ]);

        $services = $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();

        // Keep the test output free of framework log lines
        $services->set('logger', NullLogger::class);
        // FlowableBundle binds its audit logger to the monolog channel.
        // Public so that tests can read the records.
        $services->set(self::FLOWABLE_LOGGER, RecordingLogger::class)->public();

        // The engine: every Flowable REST call is answered by the stub.
        // Public so that tests can read the recorded requests.
        $services->set(TestFlowableEngine::class)->public();
        $services->set('http_client', MockHttpClient::class)
            ->args([service(TestFlowableEngine::class)]);

        $services->set(TestHeaderAuthenticator::class);

        if ($this->options['mcp_fixtures'] ?? false) {
            // Replaces the bundle's provider without calling it
            $services->set(NonValidatingProcessDefinitionProvider::class)
                ->decorate(FlowProcessDefinitionProvider::class)
                ->public();
            $services->set(ForeignMcpToolProvider::class)->public();
        }

        if (\array_key_exists('external_worker_handlers', $this->options)) {
            foreach ($this->options['external_worker_handlers'] as $handler) {
                $services->set($handler);
            }
            $services->alias(self::WORKER_HANDLER_REGISTRY, ExternalWorkerHandlerRegistry::class)->public();
        }

        if ($this->hasPermissionVoter()) {
            // Autoconfiguration adds the `security.voter` tag; public so that
            // tests can tell the two variants apart instead of finding an
            // inlined service missing in both
            $services->set(TestPermissionVoter::class)->public();
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'api_platform')->prefix(self::API_PREFIX);
        $routes->import('.', 'mcp');
    }

    private function apiConfigurationDir(): string
    {
        return \dirname((new \ReflectionClass(ApiConfigurationBundle::class))->getFileName(), 2);
    }
}
// - revised 2026-11-26 (kernel options for MCP switches, parameters, use_symfony_listeners; recording flowable logger)
// - revised 2026-11-26 (own, freshly emptied cache directory for a non-debug kernel)
// - revised 2026-11-26 (options api_platform_mcp and mcp_fixtures)
// - revised 2026-11-27 (option external_worker_handlers with a public registry alias)
