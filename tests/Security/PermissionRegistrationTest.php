<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:50:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Security;

use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use Dmstr\Flowable\Security\FlowablePermissions;
use Dmstr\Flowable\Tests\Http\PermissionCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * The bundle registers every permission attribute its operations and tools
 * check as a `za7_core.permission` tag, without depending on za7-core: the
 * tags are read from the bundle's own `config/services.yaml`, the checked
 * attributes from the API Platform metadata of `src/ApiResource`.
 */
final class PermissionRegistrationTest extends TestCase
{
    private const string TAG = 'za7_core.permission';
    private const string PERMISSION_PATTERN = '/^[a-z][a-z0-9]*:[a-z][a-z0-9_]*$/';
    private const string EXPRESSION_PATTERN = "/^is_granted\\('([^']+)'\\)$/";

    public function testTaggedPermissionsAreWellFormed(): void
    {
        $permissions = self::taggedPermissions();

        self::assertCount(13, $permissions);
        foreach ($permissions as $permission) {
            self::assertMatchesRegularExpression(self::PERMISSION_PATTERN, $permission);
        }
        self::assertSame(array_values(array_unique($permissions)), $permissions, 'a permission is tagged twice');
    }

    public function testTaggedPermissionsAreExactlyTheCheckedAttributes(): void
    {
        $tagged = self::taggedPermissions();
        $checked = self::checkedAttributes();
        sort($tagged);
        sort($checked);

        self::assertSame($checked, $tagged);
    }

    public function testConstantsMatchTheTags(): void
    {
        $tagged = self::taggedPermissions();
        $constants = FlowablePermissions::all();
        sort($tagged);
        sort($constants);

        self::assertSame($constants, $tagged);
        self::assertSame(
            array_values((new \ReflectionClass(FlowablePermissions::class))->getConstants()),
            FlowablePermissions::all(),
            'all() lists the constants in declaration order',
        );
    }

    public function testEveryExpressionChecksOneAttributeWithoutSubject(): void
    {
        // An `object` would make the MCP tool listing evaluate the expression
        // against a null subject and error instead of denying.
        foreach (self::expressions() as $where => $expression) {
            self::assertMatchesRegularExpression(self::EXPRESSION_PATTERN, $expression, $where);
            self::assertMatchesRegularExpression(self::PERMISSION_PATTERN, self::attribute($expression), $where);
        }
    }

    public function testNothingChecksARole(): void
    {
        foreach (self::expressions() as $where => $expression) {
            self::assertStringNotContainsString('ROLE_', $expression, $where);
        }
    }

    public function testEverySensitiveOperationAndToolStatesItsOwnPermission(): void
    {
        $httpCounts = [];
        $toolPermissions = [];
        foreach (self::operations() as $where => [$kind, $permission, $sensitive]) {
            if ($kind === 'tool') {
                $toolPermissions[substr($where, strlen('tool '))] = $permission;
            } elseif ($sensitive) {
                $httpCounts[$permission] = ($httpCounts[$permission] ?? 0) + 1;
            } else {
                self::assertSame(FlowablePermissions::READ, $permission, $where . ' is a read operation');
            }
        }

        $expectedCounts = [];
        foreach (PermissionCatalog::sensitiveOperations() as [, , $permission]) {
            $expectedCounts[$permission] = ($expectedCounts[$permission] ?? 0) + 1;
        }
        ksort($httpCounts);
        ksort($expectedCounts);
        self::assertSame(19, array_sum($httpCounts));
        self::assertSame($expectedCounts, $httpCounts);

        $expectedTools = array_map(static fn (array $tool): string => $tool[0], PermissionCatalog::tools());
        ksort($toolPermissions);
        ksort($expectedTools);
        self::assertCount(14, $toolPermissions);
        self::assertSame($expectedTools, $toolPermissions);
    }

    /**
     * @return list<string>
     */
    private static function taggedPermissions(): array
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('services.yaml');

        $permissions = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $tags) {
            foreach ($tags as $attributes) {
                self::assertArrayHasKey('permission', $attributes);
                $permissions[] = $attributes['permission'];
            }
        }

        return $permissions;
    }

    /**
     * Attributes of every `is_granted('…')` in the resource, operation and
     * tool security expressions.
     *
     * @return list<string>
     */
    private static function checkedAttributes(): array
    {
        $attributes = [];
        foreach (self::expressions() as $expression) {
            preg_match_all("/is_granted\\(\\s*'([^']+)'/", $expression, $matches);
            array_push($attributes, ...$matches[1]);
        }

        return array_values(array_unique($attributes));
    }

    /**
     * @return array<string, string> where => security expression: resource level, operations and tools
     */
    private static function expressions(): array
    {
        $expressions = [];
        foreach (self::resources() as $class => $resource) {
            $expressions[$class] = (string) $resource->getSecurity();
        }
        foreach (self::operations() as $where => [, , , $expression]) {
            $expressions[$where] = $expression;
        }

        return $expressions;
    }

    /**
     * HTTP operations and MCP tools of every resource, read from the real
     * attribute metadata.
     *
     * @return array<string, array{string, string, bool, string}> "http <name>" or "tool <name>" => [kind, permission, sensitive, expression]
     */
    private static function operations(): array
    {
        $result = [];
        foreach (self::resources() as $class => $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                $expression = (string) $operation->getSecurity();
                $permission = self::attribute($expression);
                // Every write operation, plus the stacktrace endpoint, which is a GET with its own permission
                $sensitive = $operation->getMethod() !== 'GET' || $permission === FlowablePermissions::READ_STACKTRACE;
                $result['http ' . $class . ' ' . $operation->getMethod() . ' ' . $operation->getUriTemplate()] = ['http', $permission, $sensitive, $expression];
            }
            foreach ($resource->getMcp() ?? [] as $tool) {
                $expression = (string) $tool->getSecurity();
                $result['tool ' . $tool->getName()] = ['tool', self::attribute($expression), true, $expression];
            }
        }

        return $result;
    }

    private static function attribute(string $expression): string
    {
        preg_match(self::EXPRESSION_PATTERN, $expression, $match);

        return $match[1] ?? '';
    }

    /**
     * @return array<string, \ApiPlatform\Metadata\ApiResource> first resource of each class of src/ApiResource
     */
    private static function resources(): array
    {
        $factory = new AttributesResourceMetadataCollectionFactory();
        $resources = [];
        foreach (glob(\dirname(__DIR__, 2) . '/src/ApiResource/*.php') ?: [] as $file) {
            $class = 'Dmstr\\Flowable\\ApiResource\\' . basename($file, '.php');
            $resources[$class] = $factory->create($class)[0];
        }

        return $resources;
    }
}
