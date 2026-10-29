<?php
// file generated with AI assistance: Claude Code - 2026-10-28 21:15:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Dmstr\ApiConfiguration\ApiConfigurationBundle;
use Dmstr\ApiPlatformUtils\ApiPlatformUtilsBundle;
use Dmstr\Flowable\FlowableBundle;
use Dmstr\OpenApiJsonSchema\OpenApiJsonSchemaBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
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
 * - Two variants, selected by the kernel environment:
 *   {@see self::ENV_WITH_VOTER} registers {@see TestPermissionVoter},
 *   {@see self::ENV_NO_VOTER} registers no custom voter at all.
 *
 * Cache and logs live under the system temp directory, one directory per
 * variant, so a test run leaves no files in the repository.
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
        return $this->environment !== self::ENV_NO_VOTER;
    }

    private function tmpDir(): string
    {
        return sys_get_temp_dir() . '/dmstr-flowable-bundle-tests/' . $this->environment;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $projectDir = $this->getProjectDir();
        $apiConfigurationEntities = $this->apiConfigurationDir() . '/src/Entity';

        $container->parameters()
            ->set('app.oauth.redirect_uri', self::OAUTH_REDIRECT_URI);

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
        $container->extension('api_platform', [
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
            'mcp' => ['enabled' => true],
        ]);

        $container->extension('dmstr_api_platform_utils', [
            'credential_encryption' => ['key' => self::CREDENTIALS_KEY],
            // The default, set explicitly because the tests rely on it
            'hydra_operations' => ['filter_operations_by_security' => true],
        ]);

        // All switches on: otherwise the bundle removes its MCP tools
        $container->extension('dmstr_flowable', [
            'mcp' => ['read' => true, 'write' => true, 'deploy' => true],
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
        // FlowableBundle binds its audit logger to the monolog channel
        $services->set('monolog.logger.flowable', NullLogger::class);

        // The engine: every Flowable REST call is answered by the stub.
        // Public so that tests can read the recorded requests.
        $services->set(TestFlowableEngine::class)->public();
        $services->set('http_client', MockHttpClient::class)
            ->args([service(TestFlowableEngine::class)]);

        $services->set(TestHeaderAuthenticator::class);

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
