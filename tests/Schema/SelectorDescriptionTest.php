<?php
// file generated with AI assistance: Claude Code - 2026-11-26 16:16:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Schema;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Schema\InputSchemaLoader;
use Dmstr\Flowable\Tests\Kernel\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps the description of the `apiConfiguration` selector in line with
 * what FlowableClientLocator accepts: the first 8 characters of the UUID or
 * the full UUID, lowercase. "Full or partial UUID" claimed that any prefix
 * works, which the strict selector rejects.
 */
final class SelectorDescriptionTest extends TestCase
{
    /** The misleading wording; "partial result" of the composites does not match. */
    private const string MISLEADING = '~full\s+or\s+partial|partial\s+uuid|uuid\s*\(\s*full\s+or\s+partial~i';

    public function testNoSourceFileCallsTheSelectorPartial(): void
    {
        $root = \dirname(__DIR__, 2).'/src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        $scanned = 0;
        $hits = [];
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            if (!\in_array($file->getExtension(), ['php', 'json'], true)) {
                continue;
            }
            ++$scanned;
            if (1 === preg_match(self::MISLEADING, (string) file_get_contents($file->getPathname()))) {
                $hits[] = substr($file->getPathname(), \strlen($root) + 1);
            }
        }

        self::assertGreaterThan(50, $scanned);
        self::assertSame([], $hits);
    }

    public function testSchemaDefinitionsUseTheSharedWording(): void
    {
        $text = 'Optional '.FlowableClientLocator::SELECTOR_DESCRIPTION.'.';

        self::assertSame($text, InputSchemaLoader::definition('apiConfiguration')['description']);
        self::assertSame(
            $text.' Omit to use the default engine connection.',
            InputSchemaLoader::definition('apiConfigurationOrDefault')['description'],
        );
    }

    /**
     * Every `apiConfiguration` query parameter and multipart field in the
     * generated OpenAPI document carries the one shared description.
     */
    public function testEveryOpenApiSelectorDescriptionIsTheSharedOne(): void
    {
        $kernel = new TestKernel(TestKernel::ENV_WITH_VOTER, false);
        $kernel->boot();
        try {
            $container = $kernel->getContainer()->get('test.service_container');
            \assert($container instanceof ContainerInterface);
            $factory = $container->get('api_platform.openapi.factory');
            \assert($factory instanceof OpenApiFactoryInterface);
            $openApi = $factory();
        } finally {
            $kernel->shutdown();
        }

        $descriptions = [];
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            foreach (['Post', 'Put', 'Patch', 'Get', 'Delete'] as $verb) {
                $operation = $pathItem->{'get'.$verb}();
                if (!$operation instanceof Operation) {
                    continue;
                }
                foreach ($operation->getParameters() ?? [] as $parameter) {
                    \assert($parameter instanceof Parameter);
                    if ('apiConfiguration' === $parameter->getName()) {
                        $descriptions['query '.$verb.' '.$path] = $parameter->getDescription();
                    }
                }
                $content = $operation->getRequestBody()?->getContent()?->getArrayCopy() ?? [];
                $field = $content['multipart/form-data']['schema']['properties']['apiConfiguration'] ?? null;
                if (null !== $field) {
                    $descriptions['multipart '.$verb.' '.$path] = $field['description'] ?? null;
                }
            }
        }

        self::assertGreaterThanOrEqual(21, \count($descriptions));
        self::assertCount(3, array_filter(array_keys($descriptions), static fn (string $key): bool => str_starts_with($key, 'multipart ')));
        self::assertSame(
            [FlowableClientLocator::SELECTOR_DESCRIPTION],
            array_values(array_unique($descriptions)),
        );
    }
}
